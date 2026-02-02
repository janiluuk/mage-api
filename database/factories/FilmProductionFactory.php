<?php

namespace Database\Factories;

use App\Models\FilmProduction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FilmProductionFactory extends Factory
{
    protected $model = FilmProduction::class;

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

