<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use Illuminate\Http\Request;
use App\Constant\UserRoleConstant;
use App\Exceptions\User\AccessDeniedException;

class IsAdministratorChecker
{
    public function handle(Request $request, Closure $next)
    {
        // Check both web and api guards for web routes
        $user = Auth::guard('web')->user() ?? Auth::guard('api')->user();
        
        if (!$user) {
            throw new AccessDeniedException();
        }

        // Support both old user_role system and new Spatie roles
        $isAdmin = false;
        
        // Check Spatie roles first (preferred)
        if (method_exists($user, 'hasRole')) {
            $isAdmin = $user->hasRole(['administrator', 'super-administrator', 'admin', 'super-admin']);
        }
        
        // Fallback to old user_role system if no Spatie role assigned
        if (!$isAdmin && $user->userRole) {
            $authUserRole = $user->userRole->getType();
            $isAdmin = $authUserRole === UserRoleConstant::ADMINISTRATOR ||
                       $authUserRole === UserRoleConstant::SUPER_ADMINISTRATOR;
        }

        if (!$isAdmin) {
            throw new AccessDeniedException();
        }

        return $next($request);
    }
}
