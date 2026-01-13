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

        $limit = (int) config('files.quota_bytes');
        $users->getCollection()->transform(function (User $user) use ($limit) {
            $user->storage_limit = $limit;
            $user->storage_remaining = max(0, $limit - ($user->storage_used ?? 0));
            return $user;
        });

        return $users;
    }

    public function filesForUser(int $userId)
    {
        $files = UserFile::where('user_id', $userId)->latest()->get();
        $limit = (int) config('files.quota_bytes');
        $used = $files->sum('size');

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'files' => $files,
        ];
    }
}
