<?php

namespace App\Console\Commands;

use App\Models\VideoExportJob;
use App\Services\VideoExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VideoExportProcessCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'video:export:process {job_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process a video export job';

    /**
     * Execute the console command.
     */
    public function handle(VideoExportService $exportService)
    {
        $jobId = $this->argument('job_id');
        
        $job = VideoExportJob::find($jobId);
        
        if (!$job) {
            $this->error("Export job {$jobId} not found");
            return 1;
        }

        if ($job->status !== VideoExportJob::STATUS_PENDING) {
            $this->warn("Export job {$jobId} is not pending (status: {$job->status})");
            return 0;
        }

        $this->info("Processing export job {$jobId}...");

        try {
            $exportService->process($job);
            $this->info("Export job {$jobId} completed successfully");
            return 0;
        } catch (\Exception $e) {
            $this->error("Export job {$jobId} failed: " . $e->getMessage());
            Log::error('Video export command failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}
