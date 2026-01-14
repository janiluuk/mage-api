<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Batch>
 */
class BatchFactory extends Factory
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
            'status' => fake()->randomElement([
                Batch::STATUS_PENDING,
                Batch::STATUS_PROCESSING,
                Batch::STATUS_COMPLETED,
                Batch::STATUS_FAILED,
            ]),
            'total_jobs' => 0,
            'completed_jobs' => 0,
            'failed_jobs' => 0,
            'progress' => 0,
            'settings' => [],
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the batch is pending.
     */
    public function pending()
    {
        return $this->state(fn (array $attributes) => [
            'status' => Batch::STATUS_PENDING,
        ]);
    }

    /**
     * Indicate that the batch is processing.
     */
    public function processing()
    {
        return $this->state(fn (array $attributes) => [
            'status' => Batch::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate that the batch is completed.
     */
    public function completed()
    {
        return $this->state(fn (array $attributes) => [
            'status' => Batch::STATUS_COMPLETED,
            'progress' => 100,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }
}
