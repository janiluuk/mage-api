<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shot extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'film_production_id', // Keep as film_production_id for database compatibility
        'sequence_id',
        'name',
        'description',
        'duration',
        'order',
        'scene_data',
        'metadata',
    ];

    protected $casts = [
        'scene_data' => 'array',
        'metadata' => 'array',
        'duration' => 'decimal:2',
    ];

    public function filmProject(): BelongsTo
    {
        return $this->belongsTo(FilmProject::class, 'film_production_id');
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }
}

