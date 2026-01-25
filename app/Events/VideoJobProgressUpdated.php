<?php

namespace App\Events;

use App\Models\Videojob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoJobProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Videojob $job;
    public ?int $currentFrame;
    public ?string $frameUrl;

    /**
     * Create a new event instance.
     */
    public function __construct(Videojob $job, ?int $currentFrame = null, ?string $frameUrl = null)
    {
        $this->job = $job;
        $this->currentFrame = $currentFrame;
        $this->frameUrl = $frameUrl;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('video-job.' . $this->job->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'progress.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->job->id,
            'status' => $this->job->status,
            'progress' => (float) $this->job->progress,
            'current_frame' => $this->currentFrame,
            'total_frames' => $this->job->frame_count,
            'frame_url' => $this->frameUrl,
            'estimated_time_left' => $this->job->estimated_time_left,
            'job_time' => $this->job->job_time,
            'preview_url' => $this->job->preview_animation ?? $this->job->preview_img,
            'finished_url' => $this->job->url,
        ];
    }
}
