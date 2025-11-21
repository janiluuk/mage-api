<?php

namespace App\Services\VideoJobs;

use App\Models\Videojob;
use App\Services\VideoProcessingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VideoJobUploader
{
    private VideoProcessingService $videoProcessingService;

    public function __construct(VideoProcessingService $videoProcessingService)
    {
        $this->videoProcessingService = $videoProcessingService;
    }

    public function upload(string $type, UploadedFile $file, int $userId): Videojob
    {
        return $type === 'deforum'
            ? $this->handleDeforum($file, $userId)
            : $this->handleVid2Vid($file, $userId);
    }

    private function handleVid2Vid(UploadedFile $uploadedFile, int $userId): Videojob
    {
        $fileInfo = $this->persistUploadedFile($uploadedFile);

        $videoJob = new Videojob();
        $videoJob->filename = $fileInfo['filename'];
        $videoJob->original_filename = $fileInfo['originalName'];
        $videoJob->outfile = $fileInfo['outfile'];
        $videoJob->model_id = 1;
        $videoJob->cfg_scale = 7;
        $videoJob->mimetype = $fileInfo['mimeType'];
        $videoJob->seed = -1;
        $videoJob->user_id = $userId;
        $videoJob->prompt = '';
        $videoJob->negative_prompt = '';
        $videoJob->queued_at = null;
        $videoJob->status = 'pending';

        $videoJob = $this->videoProcessingService->parseJob($videoJob, $fileInfo['publicPath']);
        $this->persistMedia($videoJob, $fileInfo['path']);

        return $videoJob;
    }

    private function handleDeforum(UploadedFile $uploadedFile, int $userId): Videojob
    {
        $fileInfo = $this->persistUploadedFile($uploadedFile);

        $videoJob = new Videojob();
        $videoJob->filename = $fileInfo['filename'];
        $videoJob->original_filename = $fileInfo['originalName'];
        $videoJob->generator = 'deforum';
        $videoJob->outfile = $fileInfo['outfile'];
        $videoJob->model_id = 1;
        $videoJob->mimetype = $fileInfo['mimeType'];
        $videoJob->queued_at = null;
        $videoJob->seed = -1;
        $videoJob->frame_count = 90;
        $videoJob->user_id = $userId;
        $videoJob->prompt = 'skull face, Halloween, (sharp teeth:1.4), (mouth open:1.3), (dark skin:1.2), scull, night, dim light, darkness, looking to the viewer, eyes looking straight,  <lora:LowRA:0.3> <lora:more_details:0.5>';
        $videoJob->negative_prompt = 'bad-picture-chill-75v';
        $videoJob->status = 'pending';

        $videoJob->save();
        $this->persistMedia($videoJob, $fileInfo['path']);

        return $videoJob;
    }

    private function persistUploadedFile(UploadedFile $uploadedFile): array
    {
        $path = $uploadedFile->store('videos', 'public');
        $filename = basename($path);

        $publicDirectory = public_path('videos');
        if (! is_dir($publicDirectory)) {
            mkdir($publicDirectory, 0755, true);
        }

        $storagePath = Storage::disk('public')->path($path);
        copy($storagePath, $publicDirectory . '/' . $filename);

        return [
            'filename' => $filename,
            'originalName' => $uploadedFile->getClientOriginalName(),
            'outfile' => pathinfo($filename, PATHINFO_FILENAME) . '.mp4',
            'path' => $path,
            'publicPath' => $publicDirectory . '/' . $filename,
            'mimeType' => $uploadedFile->getMimeType(),
        ];
    }

    private function persistMedia(Videojob $videoJob, string $path): void
    {
        $videoJob->save();
        $videoJob->addMedia($path)
            ->withResponsiveImages()
            ->preservingOriginal()
            ->toMediaCollection(Videojob::MEDIA_ORIGINAL);

        $videoJob->original_url = $videoJob->getMedia(Videojob::MEDIA_ORIGINAL)->first()?->getFullUrl();
        $videoJob->save();
    }
}
