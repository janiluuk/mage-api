<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFile;
use Illuminate\Http\Request;

class FileAdminController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->withSum('files as storage_used', 'size')
            ->paginate($request->integer('per_page', 20));

        $defaultLimit = (int) config('files.quota_bytes');
        $users->getCollection()->transform(function (User $user) use ($defaultLimit) {
            $limit = (int) ($user->quota_bytes ?: $defaultLimit);
            $user->storage_limit = $limit;
            $user->storage_remaining = max(0, $limit - ($user->storage_used ?? 0));
            return $user;
        });

        return $users;
    }

    public function filesForUser(int $userId)
    {
        $files = UserFile::where('user_id', $userId)->latest()->get();
        $defaultLimit = (int) config('files.quota_bytes');
        $userQuota = User::whereKey($userId)->value('quota_bytes');
        $limit = (int) ($userQuota ?: $defaultLimit);
        $used = $files->sum('size');

        return [
            'user_id' => $userId,
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'files' => $files,
        ];
    }

    public function updateQuota(Request $request, int $userId)
    {
        $data = $request->validate([
            'quota_bytes' => ['required', 'integer', 'min:0'],
        ]);

        $user = User::findOrFail($userId);
        $user->quota_bytes = $data['quota_bytes'];
        $user->save();

        return response()->json([
            'user_id' => $user->id,
            'quota_bytes' => (int) $user->quota_bytes,
        ]);
    }
}
