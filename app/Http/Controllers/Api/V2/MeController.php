<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MeController extends Controller
{
    /**
     * Read the authenticated user's profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function readProfile(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        // Manually create a JSON:API formatted response
        $response = [
            'data' => [
                'type' => 'users',
                'id' => (string)$user->id,
                'attributes' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $user->updated_at?->format('Y-m-d H:i:s'),
                ],
                'relationships' => [
                    'role' => [
                        'data' => $user->userRole ? [
                            'type' => 'roles',
                            'id' => (string)$user->userRole->id
                        ] : null
                    ]
                ]
            ]
        ];

        // Include related role if it exists
        if ($user->userRole) {
            $response['included'] = [
                [
                    'type' => 'roles',
                    'id' => (string)$user->userRole->id,
                    'attributes' => [
                        'type' => $user->userRole->type,
                    ]
                ]
            ];
        }

        return response()->json($response, 200, ['Content-Type' => 'application/vnd.api+json']);
    }

    /**
     * Update the authenticated user's profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        // Get the input and update the user
        $input = $request->json('data', []);
        
        if (isset($input['attributes'])) {
            $user->update($input['attributes']);
        }

        // Return the updated user as JSON:API
        $response = [
            'data' => [
                'type' => 'users',
                'id' => (string)$user->id,
                'attributes' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $user->updated_at?->format('Y-m-d H:i:s'),
                ],
                'relationships' => [
                    'role' => [
                        'data' => $user->userRole ? [
                            'type' => 'roles',
                            'id' => (string)$user->userRole->id
                        ] : null
                    ]
                ]
            ]
        ];

        // Include related role if it exists
        if ($user->userRole) {
            $response['included'] = [
                [
                    'type' => 'roles',
                    'id' => (string)$user->userRole->id,
                    'attributes' => [
                        'type' => $user->userRole->type,
                    ]
                ]
            ];
        }

        return response()->json($response, 200, ['Content-Type' => 'application/vnd.api+json']);
    }
}
