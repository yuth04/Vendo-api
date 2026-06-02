<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $role)
    {
        $user = $request->user();
        if ($user->role !== 'super-admin' && $user->role !== 'admin') {
            return response()->json(['message' => 'Access denied. Only super-admin and admin can perform this action.'], 403);
        }

        return $next($request);
    }
}