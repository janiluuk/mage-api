<?php

namespace App\Console\Commands;

use App\Services\InstanceMetricsService;
use Illuminate\Console\Command;

class CollectInstanceMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instances:collect-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect metrics for all generator instances';

    /**
     * Execute the console command.
     *
     * @param InstanceMetricsService $service
     * @return int
     */
    public function handle(InstanceMetricsService $service): int
    {
        $this->info('Collecting metrics for all instances...');

        try {
            $service->collectMetricsForAllInstances();
            $this->info('Metrics collected successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error collecting metrics: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}

