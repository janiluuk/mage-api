<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SdInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SdInstanceController extends Controller
{
    /**
     * Display a listing of SD instances.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $instances = SdInstance::orderBy('created_at', 'desc')->get();

        return response()->json($instances);
    }

    /**
     * Store a newly created SD instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|string|url|max:255',
            'type' => ['required', Rule::in(['stable_diffusion_forge', 'comfyui'])],
            'enabled' => 'boolean',
        ]);

        $instance = SdInstance::create($validated);

        return response()->json($instance, 201);
    }

    /**
     * Display the specified SD instance.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $instance = SdInstance::findOrFail($id);

        return response()->json($instance);
    }

    /**
     * Update the specified SD instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $instance = SdInstance::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|string|url|max:255',
            'type' => ['sometimes', Rule::in(['stable_diffusion_forge', 'comfyui'])],
            'enabled' => 'sometimes|boolean',
        ]);

        $instance->update($validated);

        return response()->json($instance);
    }

    /**
     * Remove the specified SD instance.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $instance = SdInstance::findOrFail($id);
        $instance->delete();

        return response()->json(['message' => 'SD instance deleted successfully'], 200);
    }

    /**
     * Toggle the enabled status of the specified SD instance.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggle(int $id): JsonResponse
    {
        $instance = SdInstance::findOrFail($id);
        $instance->enabled = !$instance->enabled;
        $instance->save();

        return response()->json($instance);
    }
}
