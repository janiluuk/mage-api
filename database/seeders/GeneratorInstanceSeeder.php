<?php

namespace Database\Seeders;

use App\Models\GeneratorInstance;
use Illuminate\Database\Seeder;

class GeneratorInstanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Clear existing instances (optional - comment out if you want to keep existing data)
        // GeneratorInstance::truncate();

        // Create ComfyUI instances
        GeneratorInstance::updateOrCreate(
            ['name' => 'ComfyUI Instance 1'],
            [
                'url' => 'http://localhost:8188',
                'type' => 'comfyui',
                'enabled' => true,
                'queue_size' => 2,
                'processing_count' => 1,
                'last_queue_check_at' => now()->subMinutes(5),
            ]
        );

        GeneratorInstance::updateOrCreate(
            ['name' => 'ComfyUI Instance 2'],
            [
                'url' => 'http://localhost:8189',
                'type' => 'comfyui',
                'enabled' => true,
                'queue_size' => 0,
                'processing_count' => 0,
                'last_queue_check_at' => now()->subMinutes(2),
            ]
        );

        // Create Stable Diffusion Forge instances
        GeneratorInstance::updateOrCreate(
            ['name' => 'SD Forge Instance 1'],
            [
                'url' => 'http://localhost:7860',
                'type' => 'stable_diffusion_forge',
                'enabled' => true,
                'queue_size' => 1,
                'processing_count' => 0,
                'last_queue_check_at' => now()->subMinutes(1),
            ]
        );

        GeneratorInstance::updateOrCreate(
            ['name' => 'SD Forge Instance 2'],
            [
                'url' => 'http://localhost:7861',
                'type' => 'stable_diffusion_forge',
                'enabled' => true,
                'queue_size' => 3,
                'processing_count' => 2,
                'last_queue_check_at' => now()->subMinutes(3),
            ]
        );

        // Create a disabled instance for testing
        GeneratorInstance::updateOrCreate(
            ['name' => 'Disabled SD Instance'],
            [
                'url' => 'http://localhost:7862',
                'type' => 'stable_diffusion_forge',
                'enabled' => false,
                'queue_size' => 0,
                'processing_count' => 0,
                'last_queue_check_at' => now()->subHours(1),
            ]
        );

        $this->command->info('Generator instances seeded successfully!');
    }
}

