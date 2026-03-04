<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApiUserIsAuthenticated
{
    // public function handle(Request $request, Closure $next)
    // {
    //     // Check user via Sanctum
    //     $user = $request->user('sanctum');

    //     if (!$user) {
    //         return response()->json([
    //             'message' => 'Unauthorized'
    //         ], 401); // 401 = Unauthorized
    //     }

    //     return $next($request);
    // }
}
