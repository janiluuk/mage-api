<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Actions\FilmProject\GetFilmProjectsAction;
use App\Actions\FilmProject\GetFilmProjectByIdAction;
use App\Actions\FilmProject\GetFilmProjectByIdRequest;
use App\Actions\FilmProject\AddFilmProjectAction;
use App\Actions\FilmProject\AddFilmProjectRequest;
use App\Actions\FilmProject\UpdateFilmProjectAction;
use App\Actions\FilmProject\UpdateFilmProjectRequest;
use App\Actions\FilmProject\DeleteFilmProjectAction;
use App\Actions\FilmProject\DeleteFilmProjectRequest;
use App\Repositories\Sequence\SequenceRepositoryInterface;
use App\Repositories\Shot\ShotRepositoryInterface;
use App\Models\Sequence;
use App\Models\Shot;
use App\Services\AI\ScriptGenerationService;
use App\Services\AI\SceneGenerationService;
use App\Services\AI\ScriptParsingService;
use App\Services\AI\SceneParsingService;
use Illuminate\Support\Facades\Log;

/**
 * Film Project API Controller
 * 
 * Handles all film project-related API endpoints.
 * Namespace: /api/film-projects
 */
class FilmProjectController extends ApiController
{
    private SequenceRepositoryInterface $sequenceRepository;
    private ShotRepositoryInterface $shotRepository;
    private ScriptGenerationService $scriptService;
    private SceneGenerationService $sceneService;
    private ScriptParsingService $scriptParsingService;
    private SceneParsingService $sceneParsingService;

    public function __construct(
        SequenceRepositoryInterface $sequenceRepository,
        ShotRepositoryInterface $shotRepository,
        ScriptGenerationService $scriptService,
        SceneGenerationService $sceneService,
        ScriptParsingService $scriptParsingService,
        SceneParsingService $sceneParsingService
    ) {
        $this->sequenceRepository = $sequenceRepository;
        $this->shotRepository = $shotRepository;
        $this->scriptService = $scriptService;
        $this->sceneService = $sceneService;
        $this->scriptParsingService = $scriptParsingService;
        $this->sceneParsingService = $sceneParsingService;
    }

    // Projects
    public function index(GetFilmProjectsAction $action): JsonResponse
    {
        try {
            $projects = $action->execute()->getResponse();
            return $this->successResponse($projects->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(GetFilmProjectByIdAction $action, int $id): JsonResponse
    {
        try {
            $project = $action->execute(new GetFilmProjectByIdRequest($id))->getResponse();
            return $this->successResponse($project->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function store(AddFilmProjectAction $action, Request $request): JsonResponse
    {
        try {
            $project = $action->execute(new AddFilmProjectRequest(
                $request->input('name'),
                $request->input('description'),
                $request->input('status'),
                $request->input('script'),
                $request->input('thumbnail'),
                $request->input('metadata')
            ))->getResponse();

            return $this->successResponse($project->toArray(), 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function update(UpdateFilmProjectAction $action, Request $request, int $id): JsonResponse
    {
        try {
            $project = $action->execute(new UpdateFilmProjectRequest(
                $id,
                $request->input('name'),
                $request->input('description'),
                $request->input('status'),
                $request->input('script'),
                $request->input('thumbnail'),
                $request->input('metadata')
            ))->getResponse();

            return $this->successResponse($project->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function destroy(DeleteFilmProjectAction $action, int $id): JsonResponse
    {
        try {
            $action->execute(new DeleteFilmProjectRequest($id));
            return $this->emptyResponse(204);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    // Sequences
    public function getSequences(int $projectId): JsonResponse
    {
        try {
            $sequences = $this->sequenceRepository->getAllByProductionId($projectId);
            return $this->successResponse($sequences->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getSequence(int $projectId, int $sequenceId): JsonResponse
    {
        try {
            $sequence = $this->sequenceRepository->getById($sequenceId);
            if (!$sequence || $sequence->film_production_id != $projectId) {
                return $this->errorResponse('Sequence not found', 404);
            }
            return $this->successResponse($sequence->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createSequence(Request $request, int $projectId): JsonResponse
    {
        try {
            $sequence = new Sequence();
            $sequence->film_production_id = $projectId;
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

    public function updateSequence(Request $request, int $projectId, int $sequenceId): JsonResponse
    {
        try {
            $sequence = $this->sequenceRepository->getById($sequenceId);
            if (!$sequence || $sequence->film_production_id != $projectId) {
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

    public function deleteSequence(int $projectId, int $sequenceId): JsonResponse
    {
        try {
            $sequence = $this->sequenceRepository->getById($sequenceId);
            if (!$sequence || $sequence->film_production_id != $projectId) {
                return $this->errorResponse('Sequence not found', 404);
            }

            $this->sequenceRepository->delete($sequence);
            return $this->emptyResponse(204);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    // Shots
    public function getShots(int $projectId, int $sequenceId): JsonResponse
    {
        try {
            $sequence = $this->sequenceRepository->getById($sequenceId);
            if (!$sequence || $sequence->film_production_id != $projectId) {
                return $this->errorResponse('Sequence not found', 404);
            }

            $shots = $this->shotRepository->getAllBySequenceId($sequenceId);
            return $this->successResponse($shots->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getShot(int $projectId, int $sequenceId, int $shotId): JsonResponse
    {
        try {
            $shot = $this->shotRepository->getById($shotId);
            if (!$shot || $shot->film_production_id != $projectId || $shot->sequence_id != $sequenceId) {
                return $this->errorResponse('Shot not found', 404);
            }
            return $this->successResponse($shot->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createShot(Request $request, int $projectId, int $sequenceId): JsonResponse
    {
        try {
            $sequence = $this->sequenceRepository->getById($sequenceId);
            if (!$sequence || $sequence->film_production_id != $projectId) {
                return $this->errorResponse('Sequence not found', 404);
            }

            $shot = new Shot();
            $shot->film_production_id = $projectId;
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

    public function updateShot(Request $request, int $projectId, int $sequenceId, int $shotId): JsonResponse
    {
        try {
            $shot = $this->shotRepository->getById($shotId);
            if (!$shot || $shot->film_production_id != $projectId || $shot->sequence_id != $sequenceId) {
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

    public function deleteShot(int $projectId, int $sequenceId, int $shotId): JsonResponse
    {
        try {
            $shot = $this->shotRepository->getById($shotId);
            if (!$shot || $shot->film_production_id != $projectId || $shot->sequence_id != $sequenceId) {
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
            $models = $this->scriptService->getAvailableModels();
            return $this->successResponse([
                'models' => $models,
                'default_model' => config('services.local_ai.default_model', 'qwen3-18b'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch available models: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch available models: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate script for a film project
     * POST /api/film-projects/{id}/generate/script
     * 
     * Options:
     * - mode: 'manual' (user provides text) or 'generate' (AI generates)
     * - length: 'short', 'medium', 'long', or specific duration in minutes
     * - prompt: user's story text or generation prompt
     * - characters: array of character definitions for consistency
     * - model: AI model to use (defaults to qwen3-18b)
     */
    public function generateScript(Request $request, int $projectId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'mode' => 'required|string|in:manual,generate',
                'prompt' => 'required|string',
                'length' => 'nullable|string', // 'short', 'medium', 'long', or minutes like '5min'
                'characters' => 'nullable|array',
                'characters.*.name' => 'required_with:characters|string',
                'characters.*.description' => 'nullable|string',
                'characters.*.traits' => 'nullable|array',
                'model' => 'nullable|string',
                'options' => 'nullable|array',
            ]);

            $getProjectAction = app(GetFilmProjectByIdAction::class);
            $project = $getProjectAction->execute(new GetFilmProjectByIdRequest($projectId))->getResponse();

            $script = $this->scriptService->generateScript(
                $validated['mode'],
                $validated['prompt'],
                $validated['length'] ?? 'medium',
                $validated['characters'] ?? [],
                $validated['model'] ?? config('services.local_ai.default_model', 'qwen3-18b'),
                $validated['options'] ?? []
            );

            $updateAction = app(UpdateFilmProjectAction::class);
            $updateAction->execute(new UpdateFilmProjectRequest(
                $projectId,
                null,
                null,
                null,
                $script,
                null,
                array_merge($project->metadata ?? [], [
                    'generated_script' => true,
                    'script_model' => $validated['model'] ?? config('services.local_ai.default_model', 'qwen3-18b'),
                    'script_length' => $validated['length'] ?? 'medium',
                    'characters' => $validated['characters'] ?? [],
                ])
            ));

            return $this->successResponse([
                'script' => $script,
                'project_id' => $projectId,
                'model' => $validated['model'] ?? config('services.local_ai.default_model', 'qwen3-18b'),
            ]);
        } catch (\Exception $e) {
            Log::error('Script generation error: ' . $e->getMessage());
            return $this->errorResponse('Failed to generate script: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate scene for a shot
     * POST /api/film-projects/{projectId}/sequences/{sequenceId}/shots/{shotId}/generate/scene
     * 
     * Options:
     * - generate_reference_shots: boolean (generate first and last shot as reference)
     * - generator: 'comfyui' or 'deforum'
     * - workflow: 'ltx-2-i2v' or 'wan2.2-t2v' (for ComfyUI)
     * - prompt: scene description
     * - options: additional generation parameters
     */
    public function generateScene(Request $request, int $projectId, int $sequenceId, int $shotId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'prompt' => 'required|string',
                'generate_reference_shots' => 'nullable|boolean',
                'generator' => 'nullable|string|in:comfyui,deforum',
                'workflow' => 'nullable|string|in:ltx-2-i2v,wan2.2-t2v',
                'options' => 'nullable|array',
            ]);

            $shot = $this->shotRepository->getById($shotId);
            if (!$shot || $shot->film_production_id != $projectId || $shot->sequence_id != $sequenceId) {
                return $this->errorResponse('Shot not found', 404);
            }

            $generateReferenceShots = $validated['generate_reference_shots'] ?? false;
            $generator = $validated['generator'] ?? 'comfyui';
            $workflow = $validated['workflow'] ?? 'ltx-2-i2v';

            $sceneData = $this->sceneService->generateScene(
                $shot,
                $validated['prompt'],
                $generator,
                $workflow,
                $generateReferenceShots,
                $validated['options'] ?? []
            );

            // Update shot with generated scene data
            $shot->scene_data = $sceneData;
            $shot->metadata = array_merge($shot->metadata ?? [], [
                'generated_scene' => true,
                'generator' => $generator,
                'workflow' => $workflow,
                'generated_at' => now()->toISOString(),
            ]);
            $this->shotRepository->update($shot);

            return $this->successResponse([
                'scene_data' => $sceneData,
                'shot_id' => $shotId,
                'generator' => $generator,
                'workflow' => $workflow,
            ]);
        } catch (\Exception $e) {
            Log::error('Scene generation error: ' . $e->getMessage());
            return $this->errorResponse('Failed to generate scene: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Parse script into scenes
     * POST /api/film-projects/{id}/parse/script
     * 
     * This endpoint uses LLM to break down the project script into scenes (sequences)
     * and automatically creates Sequence records for each scene.
     */
    public function parseScriptIntoScenes(Request $request, int $projectId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'model' => 'nullable|string',
                'auto_create_sequences' => 'nullable|boolean', // Whether to auto-create Sequence records
                'options' => 'nullable|array',
            ]);

            $getProjectAction = app(GetFilmProjectByIdAction::class);
            $project = $getProjectAction->execute(new GetFilmProjectByIdRequest($projectId))->getResponse();

            // Parse script into scenes
            $scenes = $this->scriptParsingService->parseScriptIntoScenes(
                $project,
                $validated['model'] ?? null,
                $validated['options'] ?? []
            );

            $createdSequences = [];

            // Optionally auto-create Sequence records
            if ($validated['auto_create_sequences'] ?? true) {
                foreach ($scenes as $sceneData) {
                    $sequence = Sequence::create([
                        'film_production_id' => $projectId,
                        'name' => $sceneData['name'],
                        'description' => $sceneData['description'],
                        'script' => $sceneData['script_excerpt'],
                        'order' => $sceneData['scene_number'],
                        'metadata' => [
                            'location' => $sceneData['location'],
                            'time_of_day' => $sceneData['time_of_day'],
                            'estimated_duration' => $sceneData['estimated_duration'],
                            'parsed_from_script' => true,
                        ],
                    ]);

                    $createdSequences[] = $sequence->toArray();
                }

                Log::info('Auto-created sequences from script parsing', [
                    'project_id' => $projectId,
                    'sequences_count' => count($createdSequences),
                ]);
            }

            return $this->successResponse([
                'scenes' => $scenes,
                'sequences' => $createdSequences,
                'project_id' => $projectId,
            ]);

        } catch (\Exception $e) {
            Log::error('Error parsing script into scenes', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Failed to parse script into scenes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Parse scene (sequence) into shot descriptions
     * POST /api/film-projects/{projectId}/sequences/{sequenceId}/parse/shots
     * 
     * This endpoint uses LLM to break down a scene into shot descriptions
     * and optionally auto-creates Shot records for each shot.
     */
    public function parseSceneIntoShots(Request $request, int $projectId, int $sequenceId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'model' => 'nullable|string',
                'auto_create_shots' => 'nullable|boolean', // Whether to auto-create Shot records
                'options' => 'nullable|array',
            ]);

            $sequence = $this->sequenceRepository->getById($sequenceId);
            if (!$sequence || $sequence->film_production_id != $projectId) {
                return $this->errorResponse('Sequence not found', 404);
            }

            // Parse scene into shots
            $shots = $this->sceneParsingService->parseSceneIntoShots(
                $sequence,
                $validated['model'] ?? null,
                $validated['options'] ?? []
            );

            $createdShots = [];

            // Optionally auto-create Shot records
            if ($validated['auto_create_shots'] ?? true) {
                foreach ($shots as $shotData) {
                    $shot = Shot::create([
                        'film_production_id' => $projectId,
                        'sequence_id' => $sequenceId,
                        'name' => $shotData['name'],
                        'description' => $shotData['description'],
                        'duration' => $shotData['duration_estimate'],
                        'order' => $shotData['shot_number'],
                        'metadata' => [
                            'camera_angle' => $shotData['camera_angle'],
                            'camera_movement' => $shotData['camera_movement'],
                            'framing' => $shotData['framing'],
                            'key_elements' => $shotData['key_elements'],
                            'parsed_from_scene' => true,
                        ],
                    ]);

                    $createdShots[] = $shot->toArray();
                }

                Log::info('Auto-created shots from scene parsing', [
                    'sequence_id' => $sequenceId,
                    'shots_count' => count($createdShots),
                ]);
            }

            return $this->successResponse([
                'shots' => $shots,
                'created_shots' => $createdShots,
                'sequence_id' => $sequenceId,
            ]);

        } catch (\Exception $e) {
            Log::error('Error parsing scene into shots', [
                'sequence_id' => $sequenceId,
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Failed to parse scene into shots: ' . $e->getMessage(), 500);
        }
    }
}

