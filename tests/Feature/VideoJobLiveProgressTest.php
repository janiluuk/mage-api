<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Videojob;
use App\Events\VideoJobProgressUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

class VideoJobLiveProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Videojob $videoJob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        
        $this->videoJob = Videojob::factory()->create([
            'user_id' => $this->user->id,
            'status' => Videojob::STATUS_PROCESSING,
            'progress' => 50,
            'frame_count' => 100,
            'estimated_time_left' => 120,
        ]);
    }

    public function test_video_job_progress_event_contains_required_data()
    {
        $event = new VideoJobProgressUpdated($this->videoJob, 50);

        $broadcastData = $event->broadcastWith();

        $this->assertEquals($this->videoJob->id, $broadcastData['id']);
        $this->assertEquals(Videojob::STATUS_PROCESSING, $broadcastData['status']);
        $this->assertEquals(50.0, $broadcastData['progress']);
        $this->assertEquals(50, $broadcastData['current_frame']);
        $this->assertEquals(100, $broadcastData['total_frames']);
        $this->assertEquals(120, $broadcastData['estimated_time_left']);
    }

    public function test_video_job_progress_event_broadcasts_on_correct_channel()
    {
        $event = new VideoJobProgressUpdated($this->videoJob);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals('private-video-job.' . $this->videoJob->id, $channels[0]->name);
    }

    public function test_video_job_progress_event_has_correct_name()
    {
        $event = new VideoJobProgressUpdated($this->videoJob);

        $this->assertEquals('progress.updated', $event->broadcastAs());
    }

    public function test_progress_event_includes_frame_url_when_provided()
    {
        $frameUrl = 'http://example.com/frame-50.png';
        $event = new VideoJobProgressUpdated($this->videoJob, 50, $frameUrl);

        $broadcastData = $event->broadcastWith();

        $this->assertEquals($frameUrl, $broadcastData['frame_url']);
    }

    public function test_progress_event_includes_preview_and_finished_urls()
    {
        $this->videoJob->preview_animation = 'http://example.com/preview.gif';
        $this->videoJob->url = 'http://example.com/finished.mp4';
        $this->videoJob->save();

        $event = new VideoJobProgressUpdated($this->videoJob);

        $broadcastData = $event->broadcastWith();

        $this->assertEquals('http://example.com/preview.gif', $broadcastData['preview_url']);
        $this->assertEquals('http://example.com/finished.mp4', $broadcastData['finished_url']);
    }

    public function test_broadcast_channel_is_private()
    {
        $event = new VideoJobProgressUpdated($this->videoJob);

        $channels = $event->broadcastOn();

        $this->assertInstanceOf(\Illuminate\Broadcasting\PrivateChannel::class, $channels[0]);
    }
}
