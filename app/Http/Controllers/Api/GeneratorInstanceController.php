<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneratorInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GeneratorInstanceController extends Controller
{
    /**
     * Display a listing of generator instances.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $instances = GeneratorInstance::orderBy('created_at', 'desc')->get();

        return response()->json($instances);
    }

    /**
     * Store a newly created generator instance.
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

        $instance = GeneratorInstance::create($validated);

        return response()->json($instance, 201);
    }

    /**
     * Display the specified generator instance.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $instance = GeneratorInstance::findOrFail($id);

        return response()->json($instance);
    }

    /**
     * Update the specified generator instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $instance = GeneratorInstance::findOrFail($id);

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
     * Remove the specified generator instance.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $instance = GeneratorInstance::findOrFail($id);
        $instance->delete();

        return response()->json(['message' => 'Generator instance deleted successfully'], 200);
    }

    /**
     * Toggle the enabled status of the specified generator instance.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggle(int $id): JsonResponse
    {
        $instance = GeneratorInstance::findOrFail($id);
        $instance->enabled = !$instance->enabled;
        $instance->save();

        return response()->json($instance);
    }
}
