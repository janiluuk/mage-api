<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\FilmProject;
use App\Models\Sequence;
use App\Models\Shot;
use App\Models\Batch;
use App\Models\Videojob;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FilmProjectSeeder extends Seeder
{
    /**
     * Seed film projects with sequences, shots, stories, and video jobs
     */
    public function run(): void
    {
        $users = User::take(3)->get();
        
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Creating a test user...');
            $user = User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
            $users = collect([$user]);
        }

        foreach ($users as $user) {
            // Create 3 film projects per user
            for ($i = 1; $i <= 3; $i++) {
                $project = FilmProject::create([
                    'user_id' => $user->id,
                    'name' => "Film Project {$i} - " . fake()->words(2, true),
                    'description' => fake()->paragraph(),
                    'status' => fake()->randomElement(['draft', 'in_progress', 'post_production', 'completed']),
                    'script' => $i === 1 ? $this->generateSampleScript() : null,
                    'thumbnail' => null,
                    'metadata' => [
                        'genre' => fake()->randomElement(['Action', 'Drama', 'Comedy', 'Sci-Fi', 'Thriller']),
                        'estimated_duration' => fake()->numberBetween(10, 120) . ' minutes',
                    ],
                ]);

                // Create 2-4 sequences per project
                $sequenceCount = fake()->numberBetween(2, 4);
                for ($j = 1; $j <= $sequenceCount; $j++) {
                    $sequence = Sequence::create([
                        'film_production_id' => $project->id,
                        'name' => "Sequence {$j}: " . fake()->words(3, true),
                        'description' => fake()->sentence(),
                        'script' => $j === 1 ? $this->generateSequenceScript() : null,
                        'order' => $j,
                        'metadata' => [
                            'location' => fake()->randomElement(['Interior', 'Exterior', 'Studio']),
                            'time_of_day' => fake()->randomElement(['Day', 'Night', 'Dawn', 'Dusk']),
                        ],
                    ]);

                    // Create 1-3 shots per sequence
                    $shotCount = fake()->numberBetween(1, 3);
                    for ($k = 1; $k <= $shotCount; $k++) {
                        $shot = Shot::create([
                            'film_production_id' => $project->id,
                            'sequence_id' => $sequence->id,
                            'name' => "Shot {$k}",
                            'description' => fake()->sentence(),
                            'duration' => fake()->randomFloat(2, 3, 30),
                            'order' => $k,
                            'scene_data' => $k === 1 && $j === 1 ? [
                                'generated' => true,
                                'generator' => 'comfyui',
                                'workflow' => 'ltx-2-i2v',
                                'prompt' => fake()->sentence(),
                            ] : null,
                            'metadata' => [
                                'camera_angle' => fake()->randomElement(['Close-up', 'Medium', 'Wide', 'Extreme Wide']),
                                'movement' => fake()->randomElement(['Static', 'Pan', 'Tilt', 'Dolly', 'Crane']),
                            ],
                        ]);
                    }
                }
            }

            // Create 2-3 stories (batches) per user
            for ($i = 1; $i <= fake()->numberBetween(2, 3); $i++) {
                $batch = Batch::create([
                    'user_id' => $user->id,
                    'name' => "Story {$i} - " . fake()->words(3, true),
                    'description' => fake()->paragraph(),
                    'status' => fake()->randomElement(['pending', 'processing', 'completed', 'paused']),
                    'total_jobs' => 0,
                    'settings' => [
                        'model' => 'qwen3-18b',
                        'length' => fake()->randomElement(['short', 'medium', 'long']),
                    ],
                ]);

                // Create 3-8 video jobs per story
                $jobCount = fake()->numberBetween(3, 8);
                for ($j = 1; $j <= $jobCount; $j++) {
                    $videoJob = Videojob::create([
                        'user_id' => $user->id,
                        'model_id' => 1, // Assuming model_id exists
                        'filename' => "story_{$batch->id}_job_{$j}.mp4",
                        'original_filename' => "job_{$j}.mp4",
                        'prompt' => fake()->sentence(),
                        'generator' => fake()->randomElement(['deforum', 'comfyui']),
                        'status' => fake()->randomElement([
                            Videojob::STATUS_PENDING,
                            Videojob::STATUS_PROCESSING,
                            Videojob::STATUS_FINISHED,
                            Videojob::STATUS_FAILED,
                        ]),
                        'progress' => fake()->numberBetween(0, 100),
                        'generation_parameters' => [
                            'seed' => fake()->numberBetween(1000, 9999),
                            'steps' => 20,
                            'cfg_scale' => 7.0,
                        ],
                        'frame_count' => fake()->numberBetween(10, 50),
                    ]);

                    $batch->videoJobs()->attach($videoJob->id, [
                        'order' => $j,
                        'status' => fake()->randomElement(['pending', 'processing', 'completed']),
                        'description' => fake()->sentence(),
                    ]);
                }

                $batch->total_jobs = $jobCount;
                $batch->save();
            }
        }

        $this->command->info('Film projects, sequences, shots, stories, and video jobs seeded successfully!');
    }

    private function generateSampleScript(): string
    {
        return <<<'SCRIPT'
FADE IN:

EXT. CITY STREET - DAY

A bustling city street. People hurry past, lost in their own worlds.

JOHN (30s, determined) walks purposefully down the sidewalk, checking his watch.

JOHN
(to himself)
I'm late. Again.

He picks up his pace, weaving through the crowd.

FADE OUT.
SCRIPT;
    }

    private function generateSequenceScript(): string
    {
        return <<<'SCRIPT'
INT. COFFEE SHOP - DAY

JOHN sits at a corner table, staring at his phone. The screen shows a message.

He looks up, scanning the room. His eyes meet SARAH's across the shop.

SARAH
(smiling)
You made it.

JOHN
I always do.
SCRIPT;
    }
}

