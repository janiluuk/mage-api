<?php

namespace Database\Factories;

use App\Models\Sequence;
use App\Models\FilmProduction;
use Illuminate\Database\Eloquent\Factories\Factory;

class SequenceFactory extends Factory
{
    protected $model = Sequence::class;

    public function definition(): array
    {
        return [
            'film_production_id' => FilmProduction::factory(),
            'name' => $this->faker->sentence(2),
            'description' => $this->faker->paragraph(),
            'order' => $this->faker->numberBetween(1, 10),
            'script' => null,
            'metadata' => null,
        ];
    }
}

