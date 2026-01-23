<?php

namespace App\Models;

use Database\Factories\UserFileFactory;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'original_name',
        'disk',
        'path',
        'size',
        'mime_type',
        'type',
        'variant',
        'parent_file_id',
        'meta',
    ];

    protected $casts = [
        'meta' => AsArrayObject::class,
        'size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(UserFile::class, 'parent_file_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(UserFile::class, 'parent_file_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'user_file_tag');
    }

    protected static function newFactory()
    {
        return UserFileFactory::new();
    }
}
