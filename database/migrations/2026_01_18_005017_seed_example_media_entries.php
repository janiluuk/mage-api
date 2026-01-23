<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Get or create admin user
        $userId = DB::table('users')->where('email', 'admin@jsonapi.com')->value('id');
        
        if (!$userId) {
            $userData = [
                'name' => 'Admin User',
                'login' => 'admin',
                'email' => 'admin@jsonapi.com',
                'password' => Hash::make('secret'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            // Only include fields if the columns exist
            if (Schema::hasColumn('users', 'user_role_id')) {
                $userData['user_role_id'] = 1;
            }
            if (Schema::hasColumn('users', 'email_verified_at')) {
                $userData['email_verified_at'] = now();
            }
            if (Schema::hasColumn('users', 'online')) {
                $userData['online'] = 0;
            }
            if (Schema::hasColumn('users', 'confirm_send_email')) {
                $userData['confirm_send_email'] = 1;
            }
            if (Schema::hasColumn('users', 'password_reset_admin')) {
                $userData['password_reset_admin'] = false;
            }
            if (Schema::hasColumn('users', 'balance')) {
                $userData['balance'] = 0;
            }
            
            $userId = DB::table('users')->insertGetId($userData);
        }

        // Example project ID for grouping files
        $projectId = 'example-project-' . time();

        // Create example video files (boilerplate placeholders)
        $videoFiles = [
            [
                'user_id' => $userId,
                'project_id' => $projectId,
                'original_name' => 'example-video-1.mp4',
                'disk' => 'local',
                'path' => 'videos/example-video-1.mp4',
                'size' => 10485760, // 10 MB
                'mime_type' => 'video/mp4',
                'type' => 'video',
                'variant' => null,
                'parent_file_id' => null,
                'meta' => json_encode(['description' => 'Example video file 1']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'project_id' => $projectId,
                'original_name' => 'example-video-2.mp4',
                'disk' => 'local',
                'path' => 'videos/example-video-2.mp4',
                'size' => 12582912, // 12 MB
                'mime_type' => 'video/mp4',
                'type' => 'video',
                'variant' => null,
                'parent_file_id' => null,
                'meta' => json_encode(['description' => 'Example video file 2']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'project_id' => $projectId,
                'original_name' => 'example-video-3.mp4',
                'disk' => 'local',
                'path' => 'videos/example-video-3.mp4',
                'size' => 9437184, // 9 MB
                'mime_type' => 'video/mp4',
                'type' => 'video',
                'variant' => null,
                'parent_file_id' => null,
                'meta' => json_encode(['description' => 'Example video file 3']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'project_id' => $projectId,
                'original_name' => 'example-video-4.mp4',
                'disk' => 'local',
                'path' => 'videos/example-video-4.mp4',
                'size' => 11534336, // 11 MB
                'mime_type' => 'video/mp4',
                'type' => 'video',
                'variant' => null,
                'parent_file_id' => null,
                'meta' => json_encode(['description' => 'Example video file 4']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Create example audio file
        $audioFiles = [
            [
                'user_id' => $userId,
                'project_id' => $projectId,
                'original_name' => 'example-audio.wav',
                'disk' => 'local',
                'path' => 'audio/example-audio.wav',
                'size' => 5242880, // 5 MB
                'mime_type' => 'audio/wav',
                'type' => 'audio',
                'variant' => null,
                'parent_file_id' => null,
                'meta' => json_encode(['description' => 'Example audio file for beat matching']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Create example image cover files
        $imageFiles = [
            [
                'user_id' => $userId,
                'project_id' => $projectId,
                'original_name' => 'example-cover-1.jpg',
                'disk' => 'local',
                'path' => 'images/example-cover-1.jpg',
                'size' => 524288, // 512 KB
                'mime_type' => 'image/jpeg',
                'type' => 'image',
                'variant' => 'cover',
                'parent_file_id' => null,
                'meta' => json_encode(['description' => 'Example cover image 1', 'width' => 1920, 'height' => 1080]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'project_id' => $projectId,
                'original_name' => 'example-cover-2.jpg',
                'disk' => 'local',
                'path' => 'images/example-cover-2.jpg',
                'size' => 629145, // 614 KB
                'mime_type' => 'image/jpeg',
                'type' => 'image',
                'variant' => 'cover',
                'parent_file_id' => null,
                'meta' => json_encode(['description' => 'Example cover image 2', 'width' => 1920, 'height' => 1080]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insert all files (only if user_files table exists)
        if (Schema::hasTable('user_files')) {
            DB::table('user_files')->insert($videoFiles);
            DB::table('user_files')->insert($audioFiles);
            DB::table('user_files')->insert($imageFiles);
        }

        // Create example Videojob entry with boilerplate data
        $modelId = DB::table('model_files')->value('id');
        
        // Only create video job if model exists (model_id is required)
        if ($modelId) {
            try {
                DB::table('video_jobs')->insert([
                    'user_id' => $userId,
                    'model_id' => $modelId,
                    'filename' => 'example-beat-matched-video.mp4',
                    'original_filename' => 'example-beat-matched-video.mp4',
                    'status' => 'finished',
                    'url' => '/processed/example-beat-matched-video.mp4',
                    'preview_url' => '/preview/example-beat-matched-video-preview.mp4',
                    'preview_img' => '/preview/example-beat-matched-video-cover.jpg',
                    'thumbnail' => '/preview/example-beat-matched-video-thumb.jpg',
                    'prompt' => 'Example beat-matched music video with synchronized cuts',
                    'cfg_scale' => 7,
                    'width' => 1920,
                    'height' => 1080,
                    'fps' => 30,
                    'length' => 120, // 2 minutes
                    'frame_count' => 3600,
                    'progress' => 100,
                    'job_time' => 180, // 3 minutes processing time
                    'generation_parameters' => json_encode([
                        'job_type' => 'beat-match',
                        'cut_intensity' => 0.5,
                        'direction' => 'forward',
                        'speed_factor' => 1.0,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                // Skip if video_jobs table doesn't exist or other error
                // Migration should still succeed for user_files
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Get user ID
        $userId = DB::table('users')->where('email', 'admin@jsonapi.com')->value('id');
        
        if ($userId) {
            // Delete example files
            DB::table('user_files')
                ->where('user_id', $userId)
                ->where(function ($query) {
                    $query->where('original_name', 'like', 'example-%')
                        ->orWhere('path', 'like', '%example-%');
                })
                ->delete();

            // Delete example video jobs
            DB::table('video_jobs')
                ->where('user_id', $userId)
                ->where('filename', 'like', 'example-%')
                ->delete();
        }
    }
};
