<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoryFrame extends Model
{
    protected $fillable = [
        'story_batch_id',
        'frame_id',
        'prompt',
        'image_url',
    ];
}

