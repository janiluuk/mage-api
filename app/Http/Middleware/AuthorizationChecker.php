<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use Illuminate\Http\Request;
use App\Exceptions\User\UserNotAuthorizedException;

class AuthorizationChecker
{
    public function handle(Request $request, Closure $next)
    {
        // Check both web and api guards for web routes
        $user = Auth::guard('web')->user() ?? Auth::guard('api')->user();
        
        if (!$user) {
            throw new UserNotAuthorizedException();
        }

        return $next($request);
    }
}
