<?php

namespace App\Console\Commands;

use App\Models\ModelFile;
use App\Models\User;
use App\Models\UserFile;
use App\Models\Videojob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DemoContentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:content 
                            {--users=5 : Number of demo users to create}
                            {--jobs=20 : Number of video jobs to create}
                            {--files=30 : Number of user files to create}
                            {--clear : Clear existing demo content first}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate demo content with random images, videos, and video jobs for admin panel';

    /**
     * Placeholder image services
     */
    private $imageServices = [
        'unsplash' => 'https://picsum.photos/',
        'placeholder' => 'https://via.placeholder.com/',
    ];

    /**
     * Video job statuses
     */
    private $statuses = [
        Videojob::STATUS_FINISHED,
        Videojob::STATUS_PROCESSING,
        Videojob::STATUS_PENDING,
        Videojob::STATUS_APPROVED,
        Videojob::STATUS_PREPROCESSING,
        Videojob::STATUS_POST_PROCESSING,
        Videojob::STATUS_PREVIEW,
        Videojob::STATUS_ERROR,
    ];

    /**
     * Job types
     */
    private $jobTypes = [
        'beat-match',
        'audio-track-split',
        'video-generation',
        'video-editing',
        'image-to-video',
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🎬 Starting demo content generation...');

        // Check if required tables exist
        if (!Schema::hasTable('users')) {
            $this->error('❌ Users table does not exist. Please run migrations first.');
            return 1;
        }

        if ($this->option('clear')) {
            $this->warn('⚠️  Clearing existing demo content...');
            $this->clearDemoContent();
        }

        // Get or create admin user
        $adminUser = $this->getOrCreateAdminUser();

        // Get a model file for video jobs
        $modelFile = ModelFile::first();
        if (!$modelFile) {
            $this->error('❌ No model file found. Please seed model files first.');
            return 1;
        }

        // Generate users
        $users = $this->generateUsers($adminUser);
        $this->info("✅ Created {$users->count()} demo users");

        // Generate user files (only if table exists)
        $files = collect();
        if (Schema::hasTable('user_files')) {
            $files = $this->generateUserFiles($users, $adminUser);
            $this->info("✅ Created {$files->count()} demo files");
        } else {
            $this->warn('⚠️  user_files table does not exist. Skipping file generation.');
        }

        // Generate video jobs (only if table exists)
        $jobs = collect();
        if (Schema::hasTable('video_jobs')) {
            $jobs = $this->generateVideoJobs($users, $adminUser, $modelFile);
            $this->info("✅ Created {$jobs->count()} demo video jobs");
        } else {
            $this->warn('⚠️  video_jobs table does not exist. Skipping video job generation.');
        }

        $this->info('🎉 Demo content generation completed!');
        $this->info("   - Users: {$users->count()}");
        $this->info("   - Files: {$files->count()}");
        $this->info("   - Video Jobs: {$jobs->count()}");

        return 0;
    }

    /**
     * Get or create admin user
     */
    private function getOrCreateAdminUser()
    {
        $admin = User::where('email', 'admin@jsonapi.com')->first();
        
        if (!$admin) {
            $admin = User::create([
                'login' => 'admin',
                'email' => 'admin@jsonapi.com',
                'password' => Hash::make('secret'),
                'user_role_id' => 1, // Admin role
            ]);
            $this->info('✅ Created admin user');
        }

        return $admin;
    }

    /**
     * Generate demo users
     */
    private function generateUsers($adminUser)
    {
        $count = (int) $this->option('users');
        $users = collect([$adminUser]);

        for ($i = 0; $i < $count; $i++) {
            $email = 'demo_' . Str::random(8) . '@example.com';
            // Check if user already exists
            $existingUser = User::where('email', $email)->first();
            if ($existingUser) {
                $users->push($existingUser);
                continue;
            }
            
            $user = new User();
            $user->login = 'demo_' . Str::random(8);
            $user->email = $email;
            $user->password = Hash::make('password');
            $user->user_role_id = rand(2, 3); // Regular user roles
            $user->created_at = now()->subMonths(rand(1, 6));
            $user->save();
            $users->push($user);
        }

        return $users;
    }

    /**
     * Generate demo user files
     */
    private function generateUserFiles($users, $adminUser)
    {
        $count = (int) $this->option('files');
        $files = collect();

        $fileTypes = [
            ['type' => 'video', 'mime' => 'video/mp4', 'ext' => '.mp4'],
            ['type' => 'video', 'mime' => 'video/quicktime', 'ext' => '.mov'],
            ['type' => 'audio', 'mime' => 'audio/mpeg', 'ext' => '.mp3'],
            ['type' => 'audio', 'mime' => 'audio/wav', 'ext' => '.wav'],
            ['type' => 'image', 'mime' => 'image/jpeg', 'ext' => '.jpg'],
            ['type' => 'image', 'mime' => 'image/png', 'ext' => '.png'],
        ];

        $projectIds = collect();
        for ($i = 0; $i < 5; $i++) {
            $projectIds->push('project-' . Str::uuid());
        }

        for ($i = 0; $i < $count; $i++) {
            $fileType = $fileTypes[array_rand($fileTypes)];
            $user = $users->random();
            $projectId = rand(0, 100) < 70 ? $projectIds->random() : null;

            $width = $fileType['type'] === 'image' ? rand(800, 1920) : null;
            $height = $fileType['type'] === 'image' ? rand(600, 1080) : null;

            $descriptions = [
                'Sample video file for testing',
                'Demo audio track',
                'Test image file',
                'Project asset file',
                'Media library file',
            ];

            $file = UserFile::create([
                'user_id' => $user->id,
                'project_id' => $projectId,
                'original_name' => 'demo_' . Str::random(8) . $fileType['ext'],
                'disk' => 'local',
                'path' => 'files/' . Str::uuid() . $fileType['ext'],
                'size' => rand(102400, 52428800), // 100KB to 50MB
                'mime_type' => $fileType['mime'],
                'type' => $fileType['type'],
                'variant' => $fileType['type'] === 'image' && rand(0, 100) < 20 ? 'cover' : null,
                'parent_file_id' => null,
                'meta' => json_encode([
                    'description' => $descriptions[array_rand($descriptions)],
                    'width' => $width,
                    'height' => $height,
                    'duration' => $fileType['type'] === 'video' ? rand(10, 300) : null,
                ]),
                'created_at' => now()->subMonths(rand(1, 3)),
            ]);

            $files->push($file);
        }

        return $files;
    }

    /**
     * Generate demo video jobs
     */
    private function generateVideoJobs($users, $adminUser, $modelFile)
    {
        $count = (int) $this->option('jobs');
        $jobs = collect();

        $prompts = [
            'A futuristic cityscape at sunset',
            'Abstract geometric patterns in motion',
            'Nature documentary style wildlife',
            'Cyberpunk neon street scene',
            'Underwater coral reef exploration',
            'Time-lapse of clouds over mountains',
            'Retro 80s synthwave aesthetic',
            'Minimalist architecture showcase',
            'Epic space battle sequence',
            'Peaceful forest walk in spring',
        ];

        for ($i = 0; $i < $count; $i++) {
            $status = $this->statuses[array_rand($this->statuses)];
            $user = $users->random();
            $jobType = $this->jobTypes[array_rand($this->jobTypes)];
            
            // Determine progress based on status
            $progress = match($status) {
                Videojob::STATUS_FINISHED => 100,
                Videojob::STATUS_PROCESSING, Videojob::STATUS_POST_PROCESSING => rand(10, 90),
                Videojob::STATUS_PREPROCESSING => rand(1, 10),
                default => 0,
            };

            // Generate image dimensions
            $widths = [640, 1280, 1920];
            $heights = [480, 720, 1080];
            $width = $widths[array_rand($widths)];
            $height = $heights[array_rand($heights)];

            // Generate preview images
            $previewImg = $this->generatePlaceholderImage($width, $height);
            $thumbnail = $this->generatePlaceholderImage(320, 240);

            // Generate URLs
            $filename = 'demo_' . Str::slug(Str::random(10)) . '-' . rand(1000, 9999) . '.mp4';
            $url = $status === Videojob::STATUS_FINISHED ? '/processed/' . $filename : null;
            $previewUrl = $progress > 0 ? '/preview/' . Str::slug(Str::random(8)) . '-preview.mp4' : null;

            // Generate generation parameters based on job type
            $generationParams = $this->generateJobParameters($jobType);

            $job = new Videojob();
            $job->user_id = $user->id;
            $job->model_id = $modelFile->id;
            
            // Set fillable fields
            $job->fill([
                'filename' => $filename,
                'original_filename' => 'demo_' . Str::random(8) . '.mp4',
                'status' => $status,
                'url' => $url,
                'preview_url' => $previewUrl,
                'preview_img' => $previewImg,
                'thumbnail' => $thumbnail,
                'prompt' => $prompts[array_rand($prompts)],
                'cfg_scale' => round(rand(50, 100) / 10, 1),
                'width' => $width,
                'height' => $height,
                'fps' => [24, 30, 60][array_rand([24, 30, 60])],
                'length' => rand(5, 120),
                'frame_count' => rand(120, 7200),
                'progress' => $progress,
                'job_time' => $status === Videojob::STATUS_FINISHED ? rand(60, 1800) : 0,
            ]);
            
            $job->save();
            $jobs->push($job);
        }

        return $jobs;
    }

    /**
     * Generate job parameters based on job type
     */
    private function generateJobParameters($jobType)
    {
        return match($jobType) {
            'beat-match' => [
                'job_type' => 'beat-match',
                'cut_intensity' => rand(1, 3),
                'direction' => ['random', 'forward', 'backward'][array_rand(['random', 'forward', 'backward'])],
                'speed_factor' => round(rand(5, 20) / 10, 1),
                'start_time' => rand(0, 10),
                'end_time' => rand(60, 300),
            ],
            'audio-track-split' => [
                'job_type' => 'audio-track-split',
                'model' => ['MDX-Net-InstVoc_HQ_3', 'Demucs', 'VR-DeEcho-Aggressive'][array_rand(['MDX-Net-InstVoc_HQ_3', 'Demucs', 'VR-DeEcho-Aggressive'])],
                'output_format' => ['wav', 'mp3', 'flac'][array_rand(['wav', 'mp3', 'flac'])],
                'vocal_split_mode' => rand(0, 1) === 1,
            ],
            default => [
                'job_type' => $jobType,
                'seed' => rand(1000, 999999),
            ],
        };
    }

    /**
     * Generate placeholder image URL
     */
    private function generatePlaceholderImage($width, $height, $text = null)
    {
        // Use Unsplash/Picsum for random images
        $seed = rand(1, 1000);
        return "https://picsum.photos/seed/{$seed}/{$width}/{$height}";
    }

    /**
     * Clear existing demo content (optional)
     */
    private function clearDemoContent()
    {
        // Only clear if explicitly requested and in non-production
        if (app()->environment('production')) {
            $this->error('Cannot clear content in production environment');
            return;
        }

        // Delete demo video jobs (keep real ones if needed)
        Videojob::where('prompt', 'like', '%futuristic%')
            ->orWhere('prompt', 'like', '%Abstract%')
            ->orWhere('prompt', 'like', '%Nature documentary%')
            ->delete();

        // Delete demo user files
        UserFile::where('original_name', 'like', '%demo%')
            ->orWhere('path', 'like', '%/files/%')
            ->delete();

        $this->info('✅ Cleared existing demo content');
    }
}
