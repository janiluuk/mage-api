<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\ProcessVideoJob;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
       // $schedule->job(new ProcessVideoJob(new \App\Models\Videojob))->everyMinute();

       // Collect instance metrics every minute
       $schedule->command('instances:collect-metrics')->everyMinute();

       // Cleanup stale instance jobs every hour
       $schedule->command('instances:cleanup-stale-jobs')->hourly();
    }


    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
