<?php

namespace App\Events;

use App\Models\VideoExportJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoExportProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public VideoExportJob $job;

    /**
     * Create a new event instance.
     */
    public function __construct(VideoExportJob $job)
    {
        $this->job = $job;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('video-export.' . $this->job->id),
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
            'timemark' => $this->job->timemark,
            'error' => $this->job->error,
            'output_url' => $this->job->output_url,
            'output_log' => array_slice($this->job->output_log ?? [], -10), // Last 10 lines
        ];
    }
}
