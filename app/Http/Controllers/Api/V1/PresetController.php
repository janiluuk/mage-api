<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Preset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for preset management
 */
class PresetController extends Controller
{
    /**
     * List all presets accessible to the authenticated user
     * GET /api/v1/presets
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        
        $query = Preset::accessibleByUser($userId)
            ->orderBy('is_favorite', 'desc')
            ->orderBy('last_used_at', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by category if provided
        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by favorites only
        if ($request->boolean('favorites_only')) {
            $query->where('user_id', $userId)->favorite();
        }

        // Filter by own presets only
        if ($request->boolean('own_only')) {
            $query->where('user_id', $userId);
        }

        $presets = $query->paginate($request->input('per_page', 15));

        return response()->json($presets);
    }

    /**
     * Create a new preset
     * POST /api/v1/presets
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'type' => 'required|string|in:video,image,animation',
            'settings' => 'required|array',
            'is_public' => 'boolean',
            'is_favorite' => 'boolean',
        ]);

        $preset = Preset::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'type' => $validated['type'],
            'settings' => $validated['settings'],
            'is_public' => $validated['is_public'] ?? false,
            'is_favorite' => $validated['is_favorite'] ?? false,
        ]);

        Log::info('Preset created', [
            'preset_id' => $preset->id,
            'user_id' => $request->user()->id,
            'name' => $preset->name
        ]);

        return response()->json([
            'message' => 'Preset created successfully',
            'preset' => $preset,
        ], 201);
    }

    /**
     * Get a specific preset
     * GET /api/v1/presets/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        
        $preset = Preset::accessibleByUser($userId)->findOrFail($id);

        return response()->json($preset);
    }

    /**
     * Update a preset
     * PUT /api/v1/presets/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $preset = Preset::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'category' => 'sometimes|nullable|string|max:50',
            'type' => 'sometimes|string|in:video,image,animation',
            'settings' => 'sometimes|array',
            'is_public' => 'sometimes|boolean',
            'is_favorite' => 'sometimes|boolean',
        ]);

        $preset->update($validated);

        Log::info('Preset updated', [
            'preset_id' => $preset->id,
            'user_id' => $request->user()->id
        ]);

        return response()->json([
            'message' => 'Preset updated successfully',
            'preset' => $preset,
        ]);
    }

    /**
     * Delete a preset
     * DELETE /api/v1/presets/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $preset = Preset::where('user_id', $request->user()->id)->findOrFail($id);

        $preset->delete();

        Log::info('Preset deleted', [
            'preset_id' => $id,
            'user_id' => $request->user()->id
        ]);

        return response()->json([
            'message' => 'Preset deleted successfully',
        ]);
    }

    /**
     * Mark preset as used (increment usage count)
     * POST /api/v1/presets/{id}/use
     */
    public function markAsUsed(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        
        $preset = Preset::accessibleByUser($userId)->findOrFail($id);

        // Only increment if it's the user's own preset
        if ($preset->user_id === $userId) {
            $preset->markAsUsed();
        }

        return response()->json([
            'message' => 'Preset marked as used',
            'preset' => $preset,
        ]);
    }

    /**
     * Toggle favorite status
     * POST /api/v1/presets/{id}/favorite
     */
    public function toggleFavorite(Request $request, int $id): JsonResponse
    {
        $preset = Preset::where('user_id', $request->user()->id)->findOrFail($id);

        $preset->is_favorite = !$preset->is_favorite;
        $preset->save();

        return response()->json([
            'message' => $preset->is_favorite ? 'Preset marked as favorite' : 'Preset removed from favorites',
            'preset' => $preset,
        ]);
    }

    /**
     * Duplicate a preset (copy to user's own presets)
     * POST /api/v1/presets/{id}/duplicate
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        
        $originalPreset = Preset::accessibleByUser($userId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $newPreset = Preset::create([
            'user_id' => $userId,
            'name' => $validated['name'] ?? ($originalPreset->name . ' (Copy)'),
            'description' => $originalPreset->description,
            'category' => $originalPreset->category,
            'type' => $originalPreset->type,
            'settings' => $originalPreset->settings,
            'is_public' => false, // Duplicates are private by default
            'is_favorite' => false,
        ]);

        Log::info('Preset duplicated', [
            'original_id' => $originalPreset->id,
            'new_id' => $newPreset->id,
            'user_id' => $userId
        ]);

        return response()->json([
            'message' => 'Preset duplicated successfully',
            'preset' => $newPreset,
        ], 201);
    }

    /**
     * Get preset categories
     * GET /api/v1/presets/categories
     */
    public function categories(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        
        $categories = Preset::accessibleByUser($userId)
            ->whereNotNull('category')
            ->distinct('category')
            ->pluck('category');

        return response()->json([
            'categories' => $categories,
        ]);
    }
}
