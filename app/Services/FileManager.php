<?php

namespace App\Services;

use App\Models\UserFile;
use App\Models\User;
use FFMpeg\Format\Audio\Aac;
use FFMpeg\Format\Audio\Mp3;
use FFMpeg\Format\Video\X264;
use FFMpeg\FFMpeg;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class FileManager
{
    private FFMpeg $ffmpeg;

    public function __construct(?FFMpeg $ffmpeg = null)
    {
        $this->ffmpeg = $ffmpeg ?: FFMpeg::create();
    }

    public function quotaRemaining(int $userId): int
    {
        $limit = $this->resolveQuotaBytes($userId);
        $used = UserFile::where('user_id', $userId)->sum('size');

        return max(0, $limit - $used);
    }

    public function upload(UploadedFile $file, int $userId, ?string $projectId = null, array $meta = []): UserFile
    {
        $this->ensureUnderQuota($file->getSize(), $userId);

        $disk = config('files.disk');
        $directory = $this->userDirectory($userId, $projectId);
        $filename = Str::random(20) . '-' . $file->getClientOriginalName();
        $path = Storage::disk($disk)->putFileAs($directory, $file, $filename);

        return UserFile::create([
            'user_id' => $userId,
            'project_id' => $projectId,
            'original_name' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'type' => $this->detectType($file->getMimeType()),
            'meta' => $meta,
        ]);
    }

    public function delete(UserFile $file): void
    {
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();
    }

    public function unzip(UserFile $file): array
    {
        if (!$this->isArchive($file->mime_type)) {
            throw new \InvalidArgumentException('File is not an archive');
        }

        $disk = Storage::disk($file->disk);
        $tmpPath = tempnam(sys_get_temp_dir(), 'zip');
        $stream = $disk->readStream($file->path);
        file_put_contents($tmpPath, stream_get_contents($stream));

        $archive = new ZipArchive();
        if ($archive->open($tmpPath) !== true) {
            throw new \RuntimeException('Unable to open archive');
        }

        $entries = [];
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $stats = $archive->statIndex($i);
            if (Arr::get($stats, 'size', 0) === 0 || str_ends_with($stats['name'], '/')) {
                continue;
            }
            $entries[] = $stats;
        }

        $this->ensureUnderQuota(array_sum(array_column($entries, 'size')), $file->user_id);

        $extracted = [];
        foreach ($entries as $entry) {
            $content = $archive->getFromName($entry['name']);
            $basename = basename($entry['name']);
            $path = $this->userDirectory($file->user_id, $file->project_id) . '/' . Str::random(12) . '-' . $basename;
            $disk->put($path, $content);

            $extracted[] = UserFile::create([
                'user_id' => $file->user_id,
                'project_id' => $file->project_id,
                'original_name' => $basename,
                'disk' => $file->disk,
                'path' => $path,
                'size' => $entry['size'],
                'mime_type' => mime_content_type($this->resolveAbsolutePath($file->disk, $path)),
                'type' => $this->detectType(mime_content_type($this->resolveAbsolutePath($file->disk, $path))),
                'parent_file_id' => $file->id,
            ]);
        }

        $archive->close();
        unlink($tmpPath);

        return $extracted;
    }

    public function mergeVideos(array $files, int $userId, ?string $projectId = null, ?string $outputName = null): UserFile
    {
        if (count($files) < 2) {
            throw new \InvalidArgumentException('At least two video files are required for merging.');
        }

        $disk = $files[0]->disk ?? config('files.disk');
        $tempList = tempnam(sys_get_temp_dir(), 'ffconcat');
        $paths = [];
        foreach ($files as $file) {
            $paths[] = $this->resolveAbsolutePath($disk, $file->path);
        }
        $listContent = collect($paths)
            ->map(fn (string $path) => "file '" . str_replace("'", "'\\''", $path) . "'")
            ->implode(PHP_EOL);
        file_put_contents($tempList, $listContent);

        $outputPath = tempnam(sys_get_temp_dir(), 'merged') . '.mp4';
        $command = sprintf('ffmpeg -y -f concat -safe 0 -i %s -c copy %s', escapeshellarg($tempList), escapeshellarg($outputPath));
        $this->runProcess($command);

        $size = filesize($outputPath);
        $this->ensureUnderQuota($size, $userId);

        $storedPath = Storage::disk($disk)->putFileAs(
            $this->userDirectory($userId, $projectId),
            new \Illuminate\Http\File($outputPath),
            ($outputName ?: ('merged-' . now()->timestamp)) . '.mp4'
        );

        $merged = UserFile::create([
            'user_id' => $userId,
            'project_id' => $projectId,
            'original_name' => basename($storedPath),
            'disk' => $disk,
            'path' => $storedPath,
            'size' => $size,
            'mime_type' => 'video/mp4',
            'type' => 'video',
            'variant' => 'merged',
            'parent_file_id' => $files[0]->id,
        ]);

        unlink($tempList);
        unlink($outputPath);

        return $merged;
    }

    public function importToProject(UserFile $file, string $projectId): UserFile
    {
        $disk = $file->disk;
        $absolutePath = $this->resolveAbsolutePath($disk, $file->path);
        $size = filesize($absolutePath);
        $this->ensureUnderQuota($size, $file->user_id);

        $copiedPath = Storage::disk($disk)->putFileAs(
            $this->userDirectory($file->user_id, $projectId),
            new \Illuminate\Http\File($absolutePath),
            Str::random(14) . '-' . basename($file->path)
        );

        return UserFile::create([
            'user_id' => $file->user_id,
            'project_id' => $projectId,
            'original_name' => $file->original_name,
            'disk' => $disk,
            'path' => $copiedPath,
            'size' => $size,
            'mime_type' => $file->mime_type,
            'type' => $file->type,
            'variant' => 'imported',
            'parent_file_id' => $file->id,
            'meta' => $file->meta,
        ]);
    }

    public function transcode(UserFile $file, string $format, ?int $width = null, ?int $height = null): UserFile
    {
        $disk = $file->disk;
        $absolutePath = $this->resolveAbsolutePath($disk, $file->path);
        $outputPath = tempnam(sys_get_temp_dir(), 'transcode');
        $variant = null;
        $mimeType = $file->mime_type;

        if (str_starts_with($file->mime_type, 'video/')) {
            $outputPath .= '.' . ($format ?: 'mp4');
            $video = $this->ffmpeg->open($absolutePath);
            if ($width || $height) {
                $video->filters()->resize(
                    new \FFMpeg\Coordinate\Dimension($width ?? 0, $height ?? 0),
                    \FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET,
                    false
                );
            }
            $formatObject = new X264();
            $video->save($formatObject, $outputPath);
            $variant = 'transcode:' . ($format ?: 'mp4');
            $mimeType = 'video/' . $format;
        } else {
            $outputPath .= '.' . ($format ?: 'mp3');
            $audio = $this->ffmpeg->open($absolutePath);
            $formatObject = $format === 'aac' ? new Aac() : new Mp3();
            $audio->save($formatObject, $outputPath);
            $variant = 'transcode:' . ($format ?: 'mp3');
            $mimeType = 'audio/' . $format;
        }

        $size = filesize($outputPath);
        $this->ensureUnderQuota($size, $file->user_id);

        $storedPath = Storage::disk($disk)->putFileAs(
            $this->userDirectory($file->user_id, $file->project_id),
            new \Illuminate\Http\File($outputPath),
            Str::random(16) . '.' . $format
        );

        $transcoded = UserFile::create([
            'user_id' => $file->user_id,
            'project_id' => $file->project_id,
            'original_name' => basename($storedPath),
            'disk' => $disk,
            'path' => $storedPath,
            'size' => $size,
            'mime_type' => $mimeType,
            'type' => str_starts_with($mimeType, 'video') ? 'video' : 'audio',
            'variant' => $variant,
            'parent_file_id' => $file->id,
        ]);

        unlink($outputPath);

        return $transcoded;
    }

    public function attachAudioToVideo(
        UserFile $video,
        UserFile $audio,
        int $userId,
        ?float $startSeconds = null,
        ?float $endSeconds = null,
        ?string $outputName = null
    ): UserFile {
        if ($video->type !== 'video') {
            throw new \InvalidArgumentException('Target file must be a video.');
        }

        if ($audio->type !== 'audio') {
            throw new \InvalidArgumentException('Audio file must be an audio asset.');
        }

        $disk = $video->disk;
        $videoPath = $this->resolveAbsolutePath($disk, $video->path);
        $audioPath = $this->resolveAbsolutePath($audio->disk, $audio->path);

        $videoDuration = $this->getMediaDuration($videoPath);
        $clipStart = max(0, (float) ($startSeconds ?? 0.0));
        $clipDuration = $videoDuration;

        if ($endSeconds !== null) {
            $clipDuration = max(0.0, (float) $endSeconds - $clipStart);
        }

        if ($videoDuration !== null && $clipDuration !== null) {
            $clipDuration = min($clipDuration, $videoDuration);
        }

        if ($clipDuration !== null && $clipDuration <= 0.0) {
            throw new \InvalidArgumentException('Soundtrack clip duration must be greater than zero.');
        }

        $outputPath = tempnam(sys_get_temp_dir(), 'soundtrack') . '.mp4';
        $commandParts = ['ffmpeg -y'];
        if ($clipStart > 0) {
            $commandParts[] = '-ss ' . escapeshellarg((string) $clipStart);
        }
        $commandParts[] = '-i ' . escapeshellarg($audioPath);
        $commandParts[] = '-i ' . escapeshellarg($videoPath);
        if ($clipDuration !== null) {
            $commandParts[] = '-t ' . escapeshellarg((string) $clipDuration);
        }
        $commandParts[] = '-map 1:v:0 -map 0:a:0 -c:v copy -c:a aac -shortest ' . escapeshellarg($outputPath);

        $this->runProcess(implode(' ', $commandParts));

        $size = filesize($outputPath);
        $this->ensureUnderQuota($size, $userId);

        $storedPath = Storage::disk($disk)->putFileAs(
            $this->userDirectory($userId, $video->project_id),
            new \Illuminate\Http\File($outputPath),
            ($outputName ?: ('soundtrack-' . now()->timestamp)) . '.mp4'
        );

        $merged = UserFile::create([
            'user_id' => $userId,
            'project_id' => $video->project_id,
            'original_name' => basename($storedPath),
            'disk' => $disk,
            'path' => $storedPath,
            'size' => $size,
            'mime_type' => 'video/mp4',
            'type' => 'video',
            'variant' => 'soundtrack',
            'parent_file_id' => $video->id,
            'meta' => [
                'audio_file_id' => $audio->id,
                'soundtrack_start_seconds' => $clipStart,
                'soundtrack_end_seconds' => $endSeconds,
                'video_duration_seconds' => $videoDuration,
            ],
        ]);

        unlink($outputPath);

        return $merged;
    }

    public function resolveAbsolutePath(string $disk, string $path): string
    {
        return Storage::disk($disk)->path($path);
    }

    private function detectType(?string $mime): string
    {
        if (!$mime) {
            return 'file';
        }
        return match (true) {
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            $this->isArchive($mime) => 'archive',
            default => 'file',
        };
    }

    private function isArchive(?string $mime): bool
    {
        return in_array($mime, ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip']);
    }

    private function userDirectory(int $userId, ?string $projectId = null): string
    {
        $parts = [trim(config('files.base_directory'), '/'), 'user-' . $userId];
        if ($projectId) {
            $parts[] = 'project-' . $projectId;
        }

        return implode('/', $parts);
    }

    private function ensureUnderQuota(int $incomingSize, int $userId): void
    {
        if ($incomingSize <= 0) {
            return;
        }

        $remaining = $this->quotaRemaining($userId);
        if ($incomingSize > $remaining) {
            throw new \RuntimeException('Storage quota exceeded. Free up space or request more capacity.');
        }
    }

    private function resolveQuotaBytes(int $userId): int
    {
        $default = (int) config('files.quota_bytes');
        $userQuota = User::whereKey($userId)->value('quota_bytes');

        return (int) ($userQuota ?: $default);
    }

    private function runProcess(string $command): void
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start ffmpeg process.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new \RuntimeException('FFmpeg merge failed: ' . $stderr . $stdout);
        }
    }

    private function getMediaDuration(string $path): ?float
    {
        $command = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
            escapeshellarg($path)
        );

        $output = shell_exec($command);
        if ($output === null) {
            return null;
        }

        $duration = trim($output);
        if ($duration === '') {
            return null;
        }

        return (float) $duration;
    }
}
