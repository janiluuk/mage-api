<?php

namespace App\Services\VideoJobs;

use App\Models\Videojob;
use Illuminate\Http\JsonResponse;

class VideoJobGuard
{
    public function requireAuthenticated(): ?JsonResponse
    {
        if (! auth('api')->id()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        return null;
    }

    public function assertOwner(Videojob $videoJob): ?JsonResponse
    {
        if ($videoJob->user_id !== auth('api')->id()) {
            return response()->json(['error' => 'Unauthorized. Not your video.'], 403);
        }

        return null;
    }
}
