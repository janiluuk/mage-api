<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V2\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    /**
     * Handle the incoming login request.
     *
     * Authenticates the user with JWT and returns the access token.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json([
                'error' => [
                    'title'  => 'Unauthorized',
                    'detail' => 'Invalid email or password.',
                    'status' => Response::HTTP_UNAUTHORIZED,
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'data' => [
                'accessToken' => $token,
                'token_type'  => 'bearer',
                'expires_in'  => auth('api')->factory()->getTTL() * 60,
                'user'        => auth('api')->user(),
            ],
        ]);
    }
}
