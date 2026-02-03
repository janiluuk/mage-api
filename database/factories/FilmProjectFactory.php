<?php

namespace Database\Factories;

use App\Models\FilmProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FilmProjectFactory extends Factory
{
    protected $model = FilmProject::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['draft', 'in_progress', 'completed']),
            'script' => null,
            'thumbnail' => null,
            'metadata' => null,
        ];
    }
}

