<?php

namespace Database\Factories;

use App\Models\SdInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

class SdInstanceFactory extends Factory
{
    protected $model = SdInstance::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Instance',
            'url' => 'http://' . $this->faker->ipv4() . ':7860',
            'type' => $this->faker->randomElement(['stable_diffusion_forge', 'comfyui']),
            'enabled' => true,
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => true,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    public function stableDiffusionForge(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'stable_diffusion_forge',
        ]);
    }

    public function comfyui(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'comfyui',
        ]);
    }
}
