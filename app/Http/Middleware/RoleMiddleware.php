<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class RoleMiddleware
{
    // Inside App\Http\Middleware\EnsureApiUserIsAuthenticated
    public function handle(Request $request, Closure $next, $role)
    {
        $user = $request->user();
        if (strtolower($user->role) !== strtolower($role)) {
            return response()->json(['message' => 'Access denied. Only ' . $role . ' can perform this action.'], 403);
        }

        return $next($request);
    }
 

}
