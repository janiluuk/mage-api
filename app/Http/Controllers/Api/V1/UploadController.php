<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Api\V1\UploadRequest;

class UploadController extends Controller
{
    /**
     * List of allowed upload paths
     *
     * @var array
     */
    protected $allowedPaths = [
        'users' => [
            'profile-image'
        ],
        'items' => [
            'image'
        ]
    ];

    public function __construct()
    {
        $this->allowedPaths = collect($this->allowedPaths);
    }

    /**
     * Handle the incoming request.
     *
     * @param string $resource
     * @param int $id
     * @param string $field
     * @param UploadRequest $request
     * @return JsonResponse
     */
    public function __invoke(string $resource, int $id, string $field, UploadRequest $request)
    {
        // Check if path is allowed
        if ($this->routeIsAllowed($resource, $field)) {
            // Check if user has permissions to upload to this resource
            if (!$this->userCanUploadToResource($request, $resource, $id)) {
                return response()->json([
                    'message' => 'You do not have permission to upload files to this resource.'
                ], 403);
            }

            $path = "{$resource}/{$id}/{$field}";

            // Upload the image and return the path
            $path = Storage::put($path, $request->file('attachment'));
            $url  = Storage::url($path);

            return response()->json(compact('url'), 201);
        }

        abort(400);
    }

    /**
     * Check if the authenticated user has permission to upload to the specified resource.
     *
     * @param UploadRequest $request
     * @param string $resource
     * @param int $id
     * @return bool
     */
    protected function userCanUploadToResource(UploadRequest $request, string $resource, int $id): bool
    {
        $user = $request->user();
        
        if (!$user) {
            return false;
        }

        // Check if user is admin (admins can upload to any resource)
        if ($user->userRole && 
            ($user->userRole->getType() === \App\Constant\UserRoleConstant::ADMINISTRATOR ||
             $user->userRole->getType() === \App\Constant\UserRoleConstant::SUPER_ADMINISTRATOR)) {
            return true;
        }

        // For users resource, user can only upload to their own profile
        if ($resource === 'users') {
            return $user->id === $id;
        }

        // For items resource, check if user owns the item
        if ($resource === 'items') {
            $item = \App\Models\Item::find($id);
            return $item && $item->user_id === $user->id;
        }

        return false;
    }

    /**
     * Check if route is allowed
     *
     * @param string $resource
     * @param string $field
     * @return string|boolean
     */
    protected function routeIsAllowed(string $resource, string $field)
    {
        return $this->allowedPaths->search(function ($allowedFields, $allowedResource) use ($resource, $field) {
            return $resource == $allowedResource && in_array($field, $allowedFields);
        });
    }
}
