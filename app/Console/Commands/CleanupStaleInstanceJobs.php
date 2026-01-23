<?php

namespace App\Console\Commands;

use App\Models\InstanceJob;
use Illuminate\Console\Command;

class CleanupStaleInstanceJobs extends Command
{
    protected $signature = 'instances:cleanup-stale-jobs';

    protected $description = 'Clean up stale instance jobs (processing for more than 2 hours)';

    public function handle(): int
    {
        $staleCutoff = now()->subHours(2);

        $staleJobs = InstanceJob::where('status', InstanceJob::STATUS_PROCESSING)
            ->where('started_at', '<', $staleCutoff)
            ->get();

        $count = 0;
        foreach ($staleJobs as $job) {
            $job->markAsFailed();
            $count++;
        }

        if ($count > 0) {
            $this->info("Cleaned up {$count} stale instance jobs.");
        } else {
            $this->info('No stale jobs found.');
        }

        return Command::SUCCESS;
    }
}


