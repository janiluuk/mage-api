<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Videojob;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * Get video statistics for dashboard
     * GET /api/v1/stats
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'totalVideos' => 0,
                'processingJobs' => 0,
                'completedToday' => 0,
                'failedJobs' => 0,
            ]);
        }

        // Count total videos (all finished jobs)
        $totalVideos = Videojob::where('user_id', $user->id)
            ->where('status', Videojob::STATUS_FINISHED)
            ->count();

        // Count processing jobs (processing, preprocessing, postprocessing, approved, pending)
        $processingJobs = Videojob::where('user_id', $user->id)
            ->whereIn('status', [
                Videojob::STATUS_PROCESSING,
                Videojob::STATUS_PREPROCESSING,
                Videojob::STATUS_POST_PROCESSING,
                Videojob::STATUS_APPROVED,
                Videojob::STATUS_PENDING,
            ])
            ->count();

        // Count completed today
        $completedToday = Videojob::where('user_id', $user->id)
            ->where('status', Videojob::STATUS_FINISHED)
            ->whereDate('updated_at', Carbon::today())
            ->count();

        // Count failed jobs
        $failedJobs = Videojob::where('user_id', $user->id)
            ->where('status', Videojob::STATUS_ERROR)
            ->count();

        return response()->json([
            'totalVideos' => $totalVideos,
            'processingJobs' => $processingJobs,
            'completedToday' => $completedToday,
            'failedJobs' => $failedJobs,
        ]);
    }
}

