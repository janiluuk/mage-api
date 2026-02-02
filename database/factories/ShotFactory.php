<?php

namespace Database\Factories;

use App\Models\Shot;
use App\Models\FilmProduction;
use App\Models\Sequence;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShotFactory extends Factory
{
    protected $model = Shot::class;

    public function definition(): array
    {
        return [
            'film_production_id' => FilmProduction::factory(),
            'sequence_id' => Sequence::factory(),
            'name' => $this->faker->sentence(2),
            'description' => $this->faker->paragraph(),
            'duration' => $this->faker->numberBetween(30, 300),
            'order' => $this->faker->numberBetween(1, 20),
            'scene_data' => null,
            'metadata' => null,
        ];
    }
}

