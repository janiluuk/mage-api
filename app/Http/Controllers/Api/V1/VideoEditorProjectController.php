<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\VideoEditorProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class VideoEditorProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = VideoEditorProject::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc');
        
        // Optional pagination
        if ($request->has('per_page')) {
            $projects = $query->paginate($request->input('per_page', 15));
            return response()->json($projects);
        }
        
        $projects = $query->get();
        return response()->json([
            'data' => $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'type' => 'video-editor-project',
                    'attributes' => [
                        'name' => $project->name,
                        'description' => $project->description,
                        'video_type' => $project->video_type,
                        'video_id' => $project->video_id,
                        'created_at' => $project->created_at->toIso8601String(),
                        'updated_at' => $project->updated_at->toIso8601String(),
                    ],
                ];
            }),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'state' => 'required|array',
            'video_type' => 'nullable|string|in:file,job',
            'video_id' => 'nullable|integer',
        ]);

        $project = VideoEditorProject::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'state' => $validated['state'],
            'video_type' => $validated['video_type'] ?? null,
            'video_id' => $validated['video_id'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'id' => $project->id,
                'type' => 'video-editor-project',
                'attributes' => [
                    'name' => $project->name,
                    'description' => $project->description,
                    'state' => $project->state,
                    'video_type' => $project->video_type,
                    'video_id' => $project->video_id,
                    'created_at' => $project->created_at->toIso8601String(),
                    'updated_at' => $project->updated_at->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        $project = VideoEditorProject::where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $project->id,
                'type' => 'video-editor-project',
                'attributes' => [
                    'name' => $project->name,
                    'description' => $project->description,
                    'state' => $project->state,
                    'video_type' => $project->video_type,
                    'video_id' => $project->video_id,
                    'created_at' => $project->created_at->toIso8601String(),
                    'updated_at' => $project->updated_at->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $project = VideoEditorProject::where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'state' => 'sometimes|required|array',
            'video_type' => 'nullable|string|in:file,job',
            'video_id' => 'nullable|integer',
        ]);

        $project->update($validated);

        return response()->json([
            'data' => [
                'id' => $project->id,
                'type' => 'video-editor-project',
                'attributes' => [
                    'name' => $project->name,
                    'description' => $project->description,
                    'state' => $project->state,
                    'video_type' => $project->video_type,
                    'video_id' => $project->video_id,
                    'created_at' => $project->created_at->toIso8601String(),
                    'updated_at' => $project->updated_at->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $project = VideoEditorProject::where('user_id', Auth::id())
            ->findOrFail($id);

        $project->delete();

        return response()->json(null, 204);
    }
}
