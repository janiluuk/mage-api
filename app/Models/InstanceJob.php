<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstanceJob extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'instance_jobs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'instance_id',
        'video_job_id',
        'status',
        'assigned_at',
        'started_at',
        'completed_at',
        'processing_time_seconds',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_QUEUED = 'queued';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the generator instance that this job is assigned to.
     *
     * @return BelongsTo
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(GeneratorInstance::class);
    }

    /**
     * Get the video job associated with this instance job.
     *
     * @return BelongsTo
     */
    public function videoJob(): BelongsTo
    {
        return $this->belongsTo(Videojob::class, 'video_job_id');
    }

    /**
     * Mark the job as started.
     *
     * @return void
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the job as completed.
     *
     * @return void
     */
    public function markAsCompleted(): void
    {
        $processingTime = $this->started_at 
            ? now()->diffInSeconds($this->started_at) 
            : null;

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'processing_time_seconds' => $processingTime,
        ]);
    }

    /**
     * Mark the job as failed.
     *
     * @return void
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the job as cancelled.
     *
     * @return void
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
    }
}

