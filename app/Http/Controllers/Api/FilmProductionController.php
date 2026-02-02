<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Actions\FilmProduction\GetFilmProductionsAction;
use App\Actions\FilmProduction\GetFilmProductionByIdAction;
use App\Actions\FilmProduction\GetFilmProductionByIdRequest;
use App\Actions\FilmProduction\AddFilmProductionAction;
use App\Actions\FilmProduction\AddFilmProductionRequest;
use App\Actions\FilmProduction\UpdateFilmProductionAction;
use App\Actions\FilmProduction\UpdateFilmProductionRequest;
use App\Actions\FilmProduction\DeleteFilmProductionAction;
use App\Actions\FilmProduction\DeleteFilmProductionRequest;
use App\Repositories\Sequence\SequenceRepositoryInterface;
use App\Repositories\Shot\ShotRepositoryInterface;
use App\Models\Sequence;
use App\Models\Shot;
use App\Services\AI\LocalAIService;
use Illuminate\Support\Facades\Log;

class FilmProductionController extends ApiController
{
    private SequenceRepositoryInterface $sequenceRepository;
    private ShotRepositoryInterface $shotRepository;
    private LocalAIService $aiService;

    public function __construct(
        SequenceRepositoryInterface $sequenceRepository,
        ShotRepositoryInterface $shotRepository,
        LocalAIService $aiService
    ) {
        $this->sequenceRepository = $sequenceRepository;
        $this->shotRepository = $shotRepository;
        $this->aiService = $aiService;
    }

    // Productions
    public function index(GetFilmProductionsAction $action): JsonResponse
    {
        try {
            $productions = $action->execute()->getResponse();
            return $this->successResponse($productions->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(GetFilmProductionByIdAction $action, int $id): JsonResponse
    {
        try {
            $production = $action->execute(new GetFilmProductionByIdRequest($id))->getResponse();
            return $this->successResponse($production->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function store(AddFilmProductionAction $action, Request $request): JsonResponse
    {
        try {
            $production = $action->execute(new AddFilmProductionRequest(
                $request->input('name'),
                $request->input('description'),
                $request->input('status'),
                $request->input('script'),
                $request->input('thumbnail'),
                $request->input('metadata')
            ))->getResponse();

            return $this->successResponse($production->toArray(), 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function update(UpdateFilmProductionAction $action, Request $request, int $id): JsonResponse
    {
        try {
            $production = $action->execute(new UpdateFilmProductionRequest(
                $id,
                $request->input('name'),
                $request->input('description'),
                $request->input('status'),
                $request->input('script'),
                $request->input('thumbnail'),
                $request->input('metadata')
            ))->getResponse();

            return $this->successResponse($production->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function destroy(DeleteFilmProductionAction $action, int $id): JsonResponse
    {
        try {
            $action->execute(new DeleteFilmProductionRequest($id));
            return $this->emptyResponse(204);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    // Sequences
    public function getSequences(int $productionId): JsonResponse
    {
        try {
            $sequences = $this->sequenceRepository->getAllByProductionId($productionId);
            return $this->successResponse($sequences->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getSequence(int $productionId, int $sequenceId): JsonResponse
    {
        try {
            $sequence = $this->sequenceRepository->getById($sequenceId);
            if (!$sequence || $sequence->film_production_id != $productionId) {
                return $this->errorResponse('Sequence not found', 404);
            }
            return $this->successResponse($sequence->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createSequence(Request $request, int $productionId): JsonResponse
    {
        try {
            $sequence = new Sequence();
            $sequence->film_production_id = $productionId;
            $sequence->name = $request->input('name');
            $sequence->description = $request->input('description');
            $sequence->script = $request->input('script');
            $sequence->order = $request->input('order', 1);
            $sequence->metadata = $request->input('metadata');

            $this->sequenceRepository->save($sequence);
            return $this->successResponse($sequence->toArray(), 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function updateSequence(Request $request, int $productionId, int $sequenceId): JsonResponse
    {
        try {
            $sequence = $this->sequenceRepository->getById($sequenceId);
            if (!$sequence || $sequence->film_production_id != $productionId) {
                return $this->errorResponse('Sequence not found', 404);
            }

            if ($request->has('name')) $sequence->name = $request->input('name');
            if ($request->has('description')) $sequence->description = $request->input('description');
            if ($request->has('script')) $sequence->script = $request->input('script');
            if ($request->has('order')) $sequence->order = $request->input('order');
            if ($request->has('metadata')) $sequence->metadata = $request->input('metadata');

            $this->sequenceRepository->update($sequence);
            return $this->successResponse($sequence->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function deleteSequence(int $productionId, int $sequenceId): JsonResponse
    {
        try {
            $sequence = $this->sequenceRepository->getById($sequenceId);
            if (!$sequence || $sequence->film_production_id != $productionId) {
                return $this->errorResponse('Sequence not found', 404);
            }

            $this->sequenceRepository->delete($sequence);
            return $this->emptyResponse(204);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    // Shots
    public function getShots(int $productionId, int $sequenceId): JsonResponse
    {
        try {
            $sequence = $this->sequenceRepository->getById($sequenceId);
            if (!$sequence || $sequence->film_production_id != $productionId) {
                return $this->errorResponse('Sequence not found', 404);
            }

            $shots = $this->shotRepository->getAllBySequenceId($sequenceId);
            return $this->successResponse($shots->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getShot(int $productionId, int $sequenceId, int $shotId): JsonResponse
    {
        try {
            $shot = $this->shotRepository->getById($shotId);
            if (!$shot || $shot->film_production_id != $productionId || $shot->sequence_id != $sequenceId) {
                return $this->errorResponse('Shot not found', 404);
            }
            return $this->successResponse($shot->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createShot(Request $request, int $productionId, int $sequenceId): JsonResponse
    {
        try {
            $sequence = $this->sequenceRepository->getById($sequenceId);
            if (!$sequence || $sequence->film_production_id != $productionId) {
                return $this->errorResponse('Sequence not found', 404);
            }

            $shot = new Shot();
            $shot->film_production_id = $productionId;
            $shot->sequence_id = $sequenceId;
            $shot->name = $request->input('name');
            $shot->description = $request->input('description');
            $shot->duration = $request->input('duration');
            $shot->order = $request->input('order', 1);
            $shot->scene_data = $request->input('scene_data');
            $shot->metadata = $request->input('metadata');

            $this->shotRepository->save($shot);
            return $this->successResponse($shot->toArray(), 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function updateShot(Request $request, int $productionId, int $sequenceId, int $shotId): JsonResponse
    {
        try {
            $shot = $this->shotRepository->getById($shotId);
            if (!$shot || $shot->film_production_id != $productionId || $shot->sequence_id != $sequenceId) {
                return $this->errorResponse('Shot not found', 404);
            }

            if ($request->has('name')) $shot->name = $request->input('name');
            if ($request->has('description')) $shot->description = $request->input('description');
            if ($request->has('duration')) $shot->duration = $request->input('duration');
            if ($request->has('order')) $shot->order = $request->input('order');
            if ($request->has('scene_data')) $shot->scene_data = $request->input('scene_data');
            if ($request->has('metadata')) $shot->metadata = $request->input('metadata');

            $this->shotRepository->update($shot);
            return $this->successResponse($shot->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function deleteShot(int $productionId, int $sequenceId, int $shotId): JsonResponse
    {
        try {
            $shot = $this->shotRepository->getById($shotId);
            if (!$shot || $shot->film_production_id != $productionId || $shot->sequence_id != $sequenceId) {
                return $this->errorResponse('Shot not found', 404);
            }

            $this->shotRepository->delete($shot);
            return $this->emptyResponse(204);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    // AI Generation
    public function getAvailableModels(): JsonResponse
    {
        try {
            $models = $this->aiService->getAvailableModels();
            return $this->successResponse([
                'models' => $models,
                'default_model' => config('services.local_ai.default_model', 'qwen-8b'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch available models: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch available models: ' . $e->getMessage(), 500);
        }
    }

    public function generateScript(Request $request, int $productionId): JsonResponse
    {
        try {
            $prompt = $request->input('prompt');
            $options = $request->input('options', []);

            if (!$prompt) {
                return $this->errorResponse('Prompt is required', 400);
            }

            // Generate script using AI service
            $script = $this->aiService->generateScript($prompt, $options);

            // Update production with generated script
            $getProductionAction = app(GetFilmProductionByIdAction::class);
            $production = $getProductionAction->execute(new GetFilmProductionByIdRequest($productionId))->getResponse();

            $updateAction = app(UpdateFilmProductionAction::class);
            $updateAction->execute(new UpdateFilmProductionRequest(
                $productionId,
                null,
                null,
                null,
                $script,
                null,
                null
            ));

            return $this->successResponse([
                'script' => $script,
                'production_id' => $productionId,
                'model' => $options['model'] ?? config('services.local_ai.default_model', 'qwen-8b'),
            ]);
        } catch (\Exception $e) {
            Log::error('Script generation error: ' . $e->getMessage());
            return $this->errorResponse('Failed to generate script: ' . $e->getMessage(), 500);
        }
    }

    public function generateScene(Request $request, int $productionId, int $sequenceId, int $shotId): JsonResponse
    {
        try {
            $prompt = $request->input('prompt');
            $options = $request->input('options', []);

            if (!$prompt) {
                return $this->errorResponse('Prompt is required', 400);
            }

            $shot = $this->shotRepository->getById($shotId);
            if (!$shot || $shot->film_production_id != $productionId || $shot->sequence_id != $sequenceId) {
                return $this->errorResponse('Shot not found', 404);
            }

            // Generate scene description using AI service
            $sceneDescription = $this->aiService->generateSceneDescription($prompt, $options);

            // Integrate with video generation service (similar to StoryController)
            // This creates a video job for the scene
            $style = $options['style'] ?? 'cinematic';
            $resolution = $options['resolution'] ?? '1080p';

            try {
                // Create a video job for scene generation
                // This integrates with the existing video generation system
                $videoJob = \App\Models\Videojob::create([
                    'user_id' => auth('api')->id(),
                    'filename' => "scene_{$shot->id}_" . time(),
                    'original_filename' => "scene_{$shot->name}.mp4",
                    'prompt' => $sceneDescription['description'],
                    'generator' => $options['generator'] ?? 'deforum', // or 'vid2vid' based on options
                    'status' => \App\Models\Videojob::STATUS_PENDING,
                    'generation_parameters' => array_merge($options, [
                        'shot_id' => $shot->id,
                        'production_id' => $shot->film_production_id,
                        'sequence_id' => $shot->sequence_id,
                        'original_prompt' => $prompt,
                    ]),
                ]);

                $sceneData = [
                    'description' => $sceneDescription['description'],
                    'prompt' => $prompt,
                    'style' => $style,
                    'resolution' => $resolution,
                    'model' => $sceneDescription['model'],
                    'video_job_id' => $videoJob->id,
                    'status' => 'pending',
                    'generated_at' => $sceneDescription['generated_at'],
                ];
            } catch (\Exception $e) {
                Log::error('Video job creation error: ' . $e->getMessage());
                // Return scene description even if video job creation fails
                $sceneData = array_merge($sceneDescription, [
                    'style' => $style,
                    'resolution' => $resolution,
                    'status' => 'description_generated',
                    'video_job_error' => $e->getMessage(),
                ]);
            }

            // Update shot with generated scene data
            $shot->scene_data = $sceneData;
            $this->shotRepository->update($shot);

            return $this->successResponse([
                'scene_data' => $sceneData,
                'shot_id' => $shotId,
            ]);
        } catch (\Exception $e) {
            Log::error('Scene generation error: ' . $e->getMessage());
            return $this->errorResponse('Failed to generate scene: ' . $e->getMessage(), 500);
        }
    }
}

