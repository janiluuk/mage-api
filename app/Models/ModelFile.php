<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Videojob;
use Illuminate\Support\Facades\Cache;

class ModelFile extends Model
{
    use HasFactory;

        /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'model_files';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'filename',
        'name',
        'description',
        'version',
        'previewUrl',
        'enabled',         
        'instance_type',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model and set up event listeners for cache invalidation
     */
    protected static function boot(): void
    {
        parent::boot();

        // Clear cache when model files are created, updated, or deleted
        static::saved(function () {
            static::clearModelFilesCache();
        });

        static::deleted(function () {
            static::clearModelFilesCache();
        });
    }

    public function videoJobs()
    {
        return $this->hasMany(Videojob::class);
    }

    /**
     * Get all enabled model files with caching
     * Model files rarely change, so cache for 1 hour (3600 seconds)
     */
    public static function getCachedList()
    {
        return Cache::remember('model_files_all', 3600, function () {
            return static::where('enabled', true)->get();
        });
    }

    /**
     * Clear model files cache
     * Called automatically on model save/delete events
     */
    public static function clearModelFilesCache(): void
    {
        Cache::forget('model_files_all');
    }
}
