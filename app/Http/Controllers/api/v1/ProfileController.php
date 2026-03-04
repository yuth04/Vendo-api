<?php

namespace App\Http\Controllers\api\v1;

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
    // public function updateProfileImage(Request $request)
    // {
    //     $request->validate([
    //         'image' => 'required|image|max:5120',
    //     ]);
    //     $user = $request->user();

    //     // delete old image if exist 
    //     if ($user->image) {
    //             \Storage::disk('public')->delete($user->image);
    //     }
    //     // store new image
    //     $path = $request->file('image')->store('images', 'public');

    //     $user->image = $path;
    //     $user->save();

    //     return response()->json([
    //         'message' => 'Avatar updated successfully',
    //         'image' => $user->image,
    //     ]);


    // }



}
