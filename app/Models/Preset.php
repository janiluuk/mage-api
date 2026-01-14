<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Preset extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'category',
        'type',
        'settings',
        'is_public',
        'is_favorite',
        'usage_count',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'settings' => 'array',
        'is_public' => 'boolean',
        'is_favorite' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the user that owns the preset.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Increment usage count and update last used timestamp
     */
    public function markAsUsed(): void
    {
        $this->increment('usage_count');
        $this->last_used_at = now();
        $this->save();
    }

    /**
     * Scope a query to only include public presets.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope a query to only include user's presets or public presets.
     */
    public function scopeAccessibleByUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_public', true);
        });
    }

    /**
     * Scope a query to only include favorite presets.
     */
    public function scopeFavorite($query)
    {
        return $query->where('is_favorite', true);
    }
}
