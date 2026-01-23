<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Database\Factories\BatchFactory;

class Batch extends Model
{
    use HasFactory;
    
    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return BatchFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
        'total_jobs',
        'completed_jobs',
        'failed_jobs',
        'progress',
        'started_at',
        'completed_at',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'settings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the user that owns the batch.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the video jobs associated with this batch.
     */
    public function videoJobs(): BelongsToMany
    {
        return $this->belongsToMany(Videojob::class, 'batch_video_job', 'batch_id', 'video_job_id')
            ->withPivot(['order', 'status', 'started_at', 'completed_at', 'error_message', 'description'])
            ->withTimestamps()
            ->orderBy('batch_video_job.order');
    }

    /**
     * Update batch progress based on job statuses
     */
    public function updateProgress(): void
    {
        $this->load('videoJobs');
        $totalJobs = $this->videoJobs->count();
        
        if ($totalJobs === 0) {
            $this->progress = 0;
            $this->save();
            return;
        }

        $completedJobs = $this->videoJobs->where('pivot.status', 'completed')->count();
        $failedJobs = $this->videoJobs->where('pivot.status', 'failed')->count();
        
        $this->total_jobs = $totalJobs;
        $this->completed_jobs = $completedJobs;
        $this->failed_jobs = $failedJobs;
        $this->progress = (int) (($completedJobs + $failedJobs) / $totalJobs * 100);
        
        // Update overall batch status
        if ($completedJobs + $failedJobs === $totalJobs) {
            $this->status = $failedJobs > 0 ? self::STATUS_FAILED : self::STATUS_COMPLETED;
            $this->completed_at = now();
        }
        
        $this->save();
    }
}
