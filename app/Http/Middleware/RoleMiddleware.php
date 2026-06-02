<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $role)
    {
        $user = $request->user();
        if ($user->role !== 'super-admin' && $user->role !== 'admin' && $user->role !== 'staff') {
            return response()->json(['message' => 'Access denied. Cannot perform this action.'], 403);
        }

        return $next($request);
    }
}