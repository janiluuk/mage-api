<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sequence extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'film_production_id', // Keep as film_production_id for database compatibility
        'name',
        'description',
        'script',
        'order',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function filmProject(): BelongsTo
    {
        return $this->belongsTo(FilmProject::class, 'film_production_id');
    }

    public function shots(): HasMany
    {
        return $this->hasMany(Shot::class);
    }
}

