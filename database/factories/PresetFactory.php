<?php

namespace Database\Factories;

use App\Models\Preset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Preset>
 */
class PresetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['vid2vid', 'deforum', 'general']),
            'type' => fake()->randomElement(['video', 'image', 'animation']),
            'settings' => [
                'prompt' => fake()->sentence(),
                'cfg_scale' => fake()->numberBetween(2, 10),
                'denoising' => fake()->randomFloat(2, 0, 1),
            ],
            'is_public' => false,
            'is_favorite' => false,
            'usage_count' => fake()->numberBetween(0, 100),
            'last_used_at' => fake()->boolean(50) ? fake()->dateTimeBetween('-1 month') : null,
        ];
    }

    /**
     * Indicate that the preset is public.
     */
    public function public()
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Indicate that the preset is a favorite.
     */
    public function favorite()
    {
        return $this->state(fn (array $attributes) => [
            'is_favorite' => true,
        ]);
    }
}
