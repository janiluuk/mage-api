<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
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
            ->when($request->filled('tag_id'), function ($q) use ($request) {
                $q->whereHas('tags', fn ($tagQuery) => $tagQuery->where('tags.id', $request->integer('tag_id')));
            })
            ->when($request->filled('tag_name'), function ($q) use ($request) {
                $q->whereHas('tags', fn ($tagQuery) => $tagQuery->where('tags.name', 'LIKE', '%' . $request->string('tag_name') . '%'));
            });

        // Handle sorting
        $sortBy = $request->string('sort_by', 'created_at')->toString();
        $sortOrder = $request->string('sort_order', 'desc')->toString();
        
        if (in_array($sortBy, ['created_at', 'updated_at', 'original_name', 'size', 'type'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('created_at');
        }

        // Load tags relationship
        $query->with('tags');

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
        $user = $request->user();
        $limit = (int) ($user->quota_bytes ?: config('files.quota_bytes'));
        $used = UserFile::where('user_id', $user->id)->sum('size');

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
        ];
    }

    public function attachTags(Request $request, int $id)
    {
        $data = $request->validate([
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $file = $this->findOwnedFile($request->user()->id, $id);
        
        // Attach tags (sync will avoid duplicates)
        $file->tags()->syncWithoutDetaching($data['tag_ids']);

        return response()->json([
            'message' => 'Tags attached successfully',
            'file' => $file->load('tags'),
        ]);
    }

    public function detachTag(Request $request, int $id, int $tagId)
    {
        $file = $this->findOwnedFile($request->user()->id, $id);
        
        $file->tags()->detach($tagId);

        return response()->json([
            'message' => 'Tag detached successfully',
            'file' => $file->load('tags'),
        ]);
    }

    public function syncTags(Request $request, int $id)
    {
        // Allow empty array to remove all tags
        // Use 'present' instead of 'required' to accept empty arrays
        $data = $request->validate([
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $file = $this->findOwnedFile($request->user()->id, $id);
        
        // Sync tags (replace all existing tags)
        // Empty array is allowed to remove all tags
        $file->tags()->sync($data['tag_ids'] ?? []);

        return response()->json([
            'message' => 'Tags synced successfully',
            'file' => $file->load('tags'),
        ]);
    }

    public function byTags(Request $request)
    {
        $userId = $request->user()->id;

        // Get all tags that have files for this user
        $tags = Tag::whereHas('userFiles', fn ($q) => $q->where('user_id', $userId))
            ->withCount(['userFiles' => fn ($q) => $q->where('user_id', $userId)])
            ->orderBy('name')
            ->get();

        // Group files by tag
        $grouped = [];
        foreach ($tags as $tag) {
            $files = UserFile::where('user_id', $userId)
                ->whereHas('tags', fn ($q) => $q->where('tags.id', $tag->id))
                ->with('tags')
                ->orderByDesc('created_at')
                ->get();

            $grouped[] = [
                'tag' => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                    'files_count' => $tag->user_files_count,
                ],
                'files' => $files,
            ];
        }

        return response()->json([
            'groups' => $grouped,
            'total_tags' => count($grouped),
        ]);
    }

    public function byTag(Request $request, int $tagId)
    {
        $userId = $request->user()->id;

        // Verify tag exists
        $tag = Tag::findOrFail($tagId);

        $query = UserFile::where('user_id', $userId)
            ->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));

        // Handle filtering
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->string('project_id'));
        }

        // Handle sorting
        $sortBy = $request->string('sort_by', 'created_at')->toString();
        $sortOrder = $request->string('sort_order', 'desc')->toString();
        
        $allowedSortFields = ['created_at', 'updated_at', 'original_name', 'size', 'type'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('created_at');
        }

        // Load tags relationship
        $query->with('tags');

        $files = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'tag' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ],
            'files' => $files,
        ]);
    }

    private function findOwnedFile(int $userId, int $id): UserFile
    {
        return UserFile::where('user_id', $userId)->findOrFail($id);
    }
}
