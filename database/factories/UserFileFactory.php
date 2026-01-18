<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserFile;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFileFactory extends Factory
{
    protected $model = UserFile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => null,
            'original_name' => $this->faker->word() . '.mp4',
            'disk' => 'local',
            'path' => 'files/' . $this->faker->uuid() . '.mp4',
            'size' => $this->faker->numberBetween(1000, 1000000),
            'mime_type' => 'video/mp4',
            'type' => 'video',
            'variant' => null,
            'parent_file_id' => null,
            'meta' => [],
        ];
    }
}

