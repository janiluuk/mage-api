<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GeneratorsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $generators = [
            [
                'name' => 'Stable Diffusion',
                'description' => 'Base Stable Diffusion model for AI image generation',
                'identifier' => 'stable-diffusion-v1',
                'enabled' => true,
                'img_src' => '/images/generators/sd-v1.png',
                'type' => 'image',
                'modifier_mimetypes' => json_encode(['image/png', 'image/jpeg', 'image/webp']),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Deforum',
                'description' => 'Deforum animation generator for creating AI-powered video sequences',
                'identifier' => 'deforum-v1',
                'enabled' => true,
                'img_src' => '/images/generators/deforum.png',
                'type' => 'video',
                'modifier_mimetypes' => json_encode(['video/mp4', 'video/webm', 'video/mov']),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Vid2Vid',
                'description' => 'Video-to-video transformation using AI models',
                'identifier' => 'vid2vid-v1',
                'enabled' => true,
                'img_src' => '/images/generators/vid2vid.png',
                'type' => 'video',
                'modifier_mimetypes' => json_encode(['video/mp4', 'video/webm', 'video/mov', 'video/ogg']),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($generators as $generator) {
            DB::table('generators')->insert($generator);
        }
    }
}
