<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use ImageKit\ImageKit;

class ProfileController extends Controller
{
    public function getProfileImage(Request $request)
    {
        $user = $request->user();

        if (!$user->image) {
            return response()->json([
                'message' => 'No profile image found',
                'image' => null
            ], 404);
        }

        return response()->json([
            'message' => 'Profile image retrieved successfully',
            'image' => $user->image 
        ], 200);
    }
    public function updateName(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
        ]);
        $user = auth()->user();

        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
        ]);
        return response()->json([
            'message' => 'Name updated successfully',
            'data' => $user,
        ], 200);
    }


}
