<?php

/**
 * Seeder to add placeholder videos to video jobs.
 */
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Videojob;
use Illuminate\Support\Facades\Storage;

/**
 * Seeder class for adding placeholder videos to video jobs.
 */
class AddPlaceholderVideosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates placeholder videos using ffmpeg (if available) or GD library,
     * then assigns them to existing finished or preview video jobs.
     *
     * @return void
     */
    public function run(): void
    {
        // Create directories if they don't exist
        Storage::disk('public')->makeDirectory('videos');
        Storage::disk('public')->makeDirectory('previews');

        $placeholderVideos = [];
        
        // Generate simple placeholder videos using ffmpeg if available,
        // otherwise create minimal files
        $colors = ['4A90E2', 'E74C3C', '27AE60', 'F39C12', '9B59B6'];
        
        for ($i = 1; $i <= 5; $i++) {
            $name = "placeholder_{$i}.mp4";
            $previewName = "placeholder_{$i}.jpg";
            $color = $colors[$i - 1];
            
            $videoPath = 'videos/' . $name;
            $previewPath = 'previews/' . $previewName;
            $fullVideoPath = storage_path('app/public/' . $videoPath);
            $fullPreviewPath = storage_path('app/public/' . $previewPath);
            
            // Try to use ffmpeg to create a simple test video
            $ffmpegPath = exec('which ffmpeg');
            if ($ffmpegPath) {
                // Create a 5-second colored video using ffmpeg
                $command = sprintf(
                    'ffmpeg -f lavfi -i color=c=%s:s=640x360:d=5 ' .
                    '-pix_fmt yuv420p -y %s 2>&1',
                    $color,
                    escapeshellarg($fullVideoPath)
                );
                exec($command, $output, $returnCode);
                
                if ($returnCode === 0 && file_exists($fullVideoPath)) {
                    // Extract first frame as preview
                    $previewCommand = sprintf(
                        'ffmpeg -i %s -vframes 1 -y %s 2>&1',
                        escapeshellarg($fullVideoPath),
                        escapeshellarg($fullPreviewPath)
                    );
                    exec($previewCommand);
                    
                    $placeholderVideos[] = [
                        'video_path' => $videoPath,
                        'video_url' => asset('storage/' . $videoPath),
                        'preview_path' => $previewPath,
                        'preview_url' => asset('storage/' . $previewPath),
                        'filename' => $name,
                    ];
                    echo "Created video: {$name}\n";
                } else {
                    echo "Failed to create video with ffmpeg: {$name}\n";
                }
            } else {
                // Fallback: Create placeholder files
                echo "ffmpeg not available, creating placeholder entries\n";
                // Just create the preview image using GD if available
                if (function_exists('imagecreatetruecolor')) {
                    $img = imagecreatetruecolor(640, 360);
                    // Parse hex color to RGB
                    $r = hexdec(substr($color, 0, 2));
                    $g = hexdec(substr($color, 2, 2));
                    $b = hexdec(substr($color, 4, 2));
                    $bgColor = imagecolorallocate($img, $r, $g, $b);
                    imagefill($img, 0, 0, $bgColor);
                    $textColor = imagecolorallocate($img, 255, 255, 255);
                    imagestring($img, 5, 250, 170, "Video {$i}", $textColor);
                    imagejpeg($img, $fullPreviewPath, 90);
                    imagedestroy($img);
                    
                    $placeholderVideos[] = [
                        'video_path' => $videoPath,
                        'video_url' => asset('storage/' . $videoPath),
                        'preview_path' => $previewPath,
                        'preview_url' => asset('storage/' . $previewPath),
                        'filename' => $name,
                    ];
                    echo "Created preview: {$previewName}\n";
                }
            }
        }

        if (empty($placeholderVideos)) {
            echo "No videos were created.\n";
            return;
        }

        // Assign videos to existing video jobs
        $videoJobs = Videojob::where('status', 'finished')
            ->orWhere('status', 'preview')
            ->limit(count($placeholderVideos))
            ->get();

        if ($videoJobs->isEmpty()) {
            echo "No video jobs found to assign videos to.\n";
            return;
        }

        foreach ($videoJobs as $index => $job) {
            if (isset($placeholderVideos[$index])) {
                $video = $placeholderVideos[$index];
                
                // Update the video job with the placeholder video and preview
                $job->url = $video['video_url'];
                $job->preview_url = $video['preview_url'];
                $job->preview_img = $video['preview_url'];
                $job->filename = $video['filename'];
                
                // Set some default metadata
                if (empty($job->width)) {
                    $job->width = 1280;
                }
                if (empty($job->height)) {
                    $job->height = 720;
                }
                if (empty($job->fps)) {
                    $job->fps = 30;
                }
                if (empty($job->length)) {
                    $job->length = 10; // 10 seconds default
                }
                
                $job->save();
                
                echo "Assigned video to job ID: {$job->id}\n";
            }
        }

        echo "Completed assigning placeholder videos to video jobs.\n";
    }
}
