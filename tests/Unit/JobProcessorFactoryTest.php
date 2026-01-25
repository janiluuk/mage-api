<?php

namespace Tests\Unit;

use App\Jobs\JobType;
use App\Models\Videojob;
use App\Services\JobProcessors\JobProcessorFactory;
use App\Services\JobProcessors\JobProcessorInterface;
use App\Services\JobProcessors\StableDiffusionJobProcessor;
use App\Services\JobProcessors\DeforumJobProcessor;
use App\Services\JobProcessors\AudioJobProcessor;
use App\Services\JobProcessors\BeatMatchJobProcessor;
use App\Services\JobProcessors\ComfyUIJobProcessor;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class JobProcessorFactoryTest extends TestCase
{
    use RefreshDatabase;

    private JobProcessorFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock services that aren't needed for factory tests
        $this->factory = new JobProcessorFactory();
    }

    public function test_determines_correct_processor_class_for_job_types(): void
    {
        $this->assertEquals(
            \App\Services\JobProcessors\StableDiffusionJobProcessor::class,
            JobType::VID2VID->getProcessorClass()
        );
        
        $this->assertEquals(
            \App\Services\JobProcessors\DeforumJobProcessor::class,
            JobType::DEFORUM->getProcessorClass()
        );
        
        $this->assertEquals(
            \App\Services\JobProcessors\AudioJobProcessor::class,
            JobType::AUDIO_TRACK_SPLIT->getProcessorClass()
        );
        
        $this->assertEquals(
            \App\Services\JobProcessors\BeatMatchJobProcessor::class,
            JobType::BEAT_MATCH->getProcessorClass()
        );
        
        $this->assertEquals(
            \App\Services\JobProcessors\ComfyUIJobProcessor::class,
            JobType::COMFYUI_WORKFLOW->getProcessorClass()
        );
    }
    
    public function test_job_type_enum_values(): void
    {
        $this->assertEquals('vid2vid', JobType::VID2VID->value);
        $this->assertEquals('deforum', JobType::DEFORUM->value);
        $this->assertEquals('audio-track-split', JobType::AUDIO_TRACK_SPLIT->value);
        $this->assertEquals('beat-match', JobType::BEAT_MATCH->value);
        $this->assertEquals('comfyui-workflow', JobType::COMFYUI_WORKFLOW->value);
    }
}
