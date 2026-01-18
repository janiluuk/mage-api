<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneratorInstance extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'generator_instances';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'url',
        'type',
        'enabled',
        'queue_size',
        'processing_count',
        'last_queue_check_at',
        'current_model',
        'gpu_utilization',
        'cpu_utilization',
        'memory_utilization',
        'health_status',
        'last_health_check_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'enabled' => 'boolean',
        'queue_size' => 'integer',
        'processing_count' => 'integer',
        'last_queue_check_at' => 'datetime',
        'gpu_utilization' => 'integer',
        'cpu_utilization' => 'integer',
        'memory_utilization' => 'integer',
        'last_health_check_at' => 'datetime',
    ];

    /**
     * Get enabled instances.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Get all instance jobs for this instance.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function instanceJobs()
    {
        return $this->hasMany(InstanceJob::class);
    }

    /**
     * Increment the queue size.
     *
     * @return void
     */
    public function incrementQueueSize(): void
    {
        $this->increment('queue_size');
        $this->update(['last_queue_check_at' => now()]);
    }

    /**
     * Decrement the queue size.
     *
     * @return void
     */
    public function decrementQueueSize(): void
    {
        $this->decrement('queue_size');
        if ($this->queue_size < 0) {
            $this->update(['queue_size' => 0]);
        }
        $this->update(['last_queue_check_at' => now()]);
    }

    /**
     * Increment the processing count.
     *
     * @return void
     */
    public function incrementProcessingCount(): void
    {
        $this->increment('processing_count');
        $this->update(['last_queue_check_at' => now()]);
    }

    /**
     * Decrement the processing count.
     *
     * @return void
     */
    public function decrementProcessingCount(): void
    {
        $this->decrement('processing_count');
        if ($this->processing_count < 0) {
            $this->update(['processing_count' => 0]);
        }
        $this->update(['last_queue_check_at' => now()]);
    }

    /**
     * Get the total load (queue_size + processing_count).
     *
     * @return int
     */
    public function getTotalLoad(): int
    {
        return $this->queue_size + $this->processing_count;
    }
}
