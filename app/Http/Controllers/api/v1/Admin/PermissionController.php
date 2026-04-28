<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function updateRoleUser(Request $request, $id)
    {
        $authUser = Auth::user();

        // Only admin can update roles
        if ($authUser->role !== 'admin') {
            return response()->json([
                'message' => 'Only admin can action this.'
            ], 403);
        }

        // Validate
        $request->validate([
            'role' => 'required|string|in:admin,customer,manager'
        ]);

        // Find user
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        // Check if same role already
        if ($user->role === $request->role) {
            return response()->json([
                'message' => "This user is already {$user->role}"
            ]);
        }

        // Update role
        $user->update([
            'role' => $request->role
        ]);

        return response()->json([
            'message' => 'Updated role successful',
            'user' => $user
        ]);
    }
}
