<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoExportJob extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'status',
        'fragments',
        'input_files',
        'filter_graph',
        'outputs',
        'export_options',
        'output_name',
        'output_path',
        'output_url',
        'progress',
        'timemark',
        'error',
        'output_log',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'fragments' => 'array',
        'input_files' => 'array',
        'filter_graph' => 'array',
        'outputs' => 'array',
        'export_options' => 'array',
        'output_log' => 'array',
        'progress' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the export job
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
