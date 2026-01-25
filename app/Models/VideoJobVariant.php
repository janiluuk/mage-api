<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoJobVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_video_job_id',
        'variant_video_job_id',
        'model_id',
        'variant_name',
        'description',
        'variant_order',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the base video job.
     */
    public function baseVideoJob()
    {
        return $this->belongsTo(Videojob::class, 'base_video_job_id');
    }

    /**
     * Get the variant video job.
     */
    public function variantVideoJob()
    {
        return $this->belongsTo(Videojob::class, 'variant_video_job_id');
    }

    /**
     * Get the model file used for this variant.
     */
    public function modelFile()
    {
        return $this->belongsTo(ModelFile::class, 'model_id');
    }
}
