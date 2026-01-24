<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoryBatch extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'progress',
        'total_frames',
        'completed_frames',
        'config_json',
        'share_token',
    ];
}

