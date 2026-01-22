<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Videojob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

\Illuminate\Support\Facades\Artisan::call('migrate:fresh');

Carbon::setTestNow('2024-05-05 12:00:00');

$user = \App\Models\User::factory()->create();
$approvedJob = Videojob::factory()->for($user, 'user')->create([
    'status' => 'approved',
    'queued_at' => Carbon::now()->timestamp,
    'frame_count' => 5,
    'model_id' => 1,
]);

Videojob::factory()->create([
    'status' => 'processing',
]);

$otherApprovedJob = Videojob::factory()->create([
    'status' => 'approved',
    'queued_at' => Carbon::now()->addMinute()->timestamp,
]);

echo "ApprovedJob ID: " . $approvedJob->id . "\n";
echo "ApprovedJob queued_at (model): " . $approvedJob->queued_at . "\n";
echo "ApprovedJob queued_at (raw): " . $approvedJob->getAttributes()['queued_at'] . "\n";

echo "\nOther approved job ID: " . $otherApprovedJob->id . "\n";
echo "Other approved job queued_at (model): " . $otherApprovedJob->queued_at . "\n";
echo "Other approved job queued_at (raw): " . $otherApprovedJob->getAttributes()['queued_at'] . "\n";

// Check DB directly
$dbJobs = DB::table('video_jobs')->where('status', 'approved')->select('id', 'queued_at')->orderBy('id')->get();
echo "\nJobs in DB:\n";
foreach ($dbJobs as $job) {
    echo "ID: {$job->id}, queued_at: {$job->queued_at}\n";
}

$queueInfo = $approvedJob->getQueueInfo();
echo "\nQueue Info: \n";
print_r($queueInfo);
