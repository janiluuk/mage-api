<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFile;
use App\Services\FileManager;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class FileController extends Controller
{
    public function __construct(private readonly FileManager $files)
    {
    }

    public function index(Request $request): LengthAwarePaginator
    {
        $userId = $request->user()->id;
        $query = UserFile::where('user_id', $userId)
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->string('project_id')))
            ->orderByDesc('created_at');

        return $query->paginate($request->integer('per_page', 15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file'],
            'project_id' => ['nullable', 'string'],
            'meta' => ['array'],
        ]);

        try {
            $file = $this->files->upload(
                $data['file'],
                $request->user()->id,
                $data['project_id'] ?? null,
                $data['meta'] ?? []
            );
        } catch (\Throwable $throwable) {
            return response()->json(['message' => $throwable->getMessage()], 422);
        }

        return response()->json($file, 201);
    }

    public function destroy(Request $request, int $id)
    {
        $file = $this->findOwnedFile($request->user()->id, $id);
        $this->files->delete($file);

        return response()->json(['message' => 'File deleted']);
    }

    public function unzip(Request $request, int $id)
    {
        $file = $this->findOwnedFile($request->user()->id, $id);
        try {
            $files = $this->files->unzip($file);
        } catch (\Throwable $throwable) {
            return response()->json(['message' => $throwable->getMessage()], 422);
        }

        return response()->json(['message' => 'Archive unpacked', 'files' => $files]);
    }

    public function merge(Request $request)
    {
        $data = $request->validate([
            'file_ids' => ['required', 'array', 'min:2'],
            'file_ids.*' => ['integer'],
            'project_id' => ['nullable', 'string'],
            'output_name' => ['nullable', 'string'],
        ]);

        $userId = $request->user()->id;
        $files = UserFile::where('user_id', $userId)
            ->whereIn('id', $data['file_ids'])
            ->get();

        if ($files->count() !== count($data['file_ids'])) {
            return response()->json(['message' => 'One or more files were not found'], 404);
        }

        if ($files->contains(fn (UserFile $file) => $file->type !== 'video')) {
            return response()->json(['message' => 'Only video files can be merged'], 422);
        }

        try {
            $merged = $this->files->mergeVideos($files->all(), $userId, $data['project_id'] ?? null, $data['output_name'] ?? null);
        } catch (\Throwable $throwable) {
            return response()->json(['message' => $throwable->getMessage()], 422);
        }

        return response()->json($merged, 201);
    }

    public function import(Request $request, int $id)
    {
        $data = $request->validate([
            'project_id' => ['required', 'string'],
        ]);

        $file = $this->findOwnedFile($request->user()->id, $id);
        try {
            $copy = $this->files->importToProject($file, $data['project_id']);
        } catch (\Throwable $throwable) {
            return response()->json(['message' => $throwable->getMessage()], 422);
        }

        return response()->json($copy, 201);
    }

    public function transcode(Request $request, int $id)
    {
        $data = $request->validate([
            'format' => ['required', 'string', Rule::in(['mp4', 'mov', 'webm', 'mp3', 'aac'])],
            'width' => ['nullable', 'integer', 'min:0'],
            'height' => ['nullable', 'integer', 'min:0'],
        ]);

        $file = $this->findOwnedFile($request->user()->id, $id);
        try {
            $transcoded = $this->files->transcode(
                $file,
                $data['format'],
                $data['width'] ?? null,
                $data['height'] ?? null
            );
        } catch (\Throwable $throwable) {
            return response()->json(['message' => $throwable->getMessage()], 422);
        }

        return response()->json($transcoded, 201);
    }

    public function attachAudio(Request $request, int $id)
    {
        $data = $request->validate([
            'audio_file_id' => ['required', 'integer'],
            'start_seconds' => ['nullable', 'numeric', 'min:0'],
            'end_seconds' => ['nullable', 'numeric', 'gt:start_seconds'],
            'output_name' => ['nullable', 'string'],
        ]);

        $userId = $request->user()->id;
        $video = $this->findOwnedFile($userId, $id);
        $audio = $this->findOwnedFile($userId, $data['audio_file_id']);

        try {
            $merged = $this->files->attachAudioToVideo(
                $video,
                $audio,
                $userId,
                isset($data['start_seconds']) ? (float) $data['start_seconds'] : null,
                isset($data['end_seconds']) ? (float) $data['end_seconds'] : null,
                $data['output_name'] ?? null
            );
        } catch (\Throwable $throwable) {
            return response()->json(['message' => $throwable->getMessage()], 422);
        }

        return response()->json($merged, 201);
    }

    public function quota(Request $request)
    {
        $limit = (int) config('files.quota_bytes');
        $used = UserFile::where('user_id', $request->user()->id)->sum('size');

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
        ];
    }

    private function findOwnedFile(int $userId, int $id): UserFile
    {
        return UserFile::where('user_id', $userId)->findOrFail($id);
    }
}
