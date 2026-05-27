<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use ImageKit\ImageKit;

class SettingController extends Controller
{
    // Change Name
    public function changeName(Request $request)
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
            'message' => 'Name changed successfully',
            'user' => $user,
        ], 200);
    }
    // Change Password
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string|min:8',
            'new_password' => 'required|string|min:8|confirmed',
        ]);
        $user = auth()->user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 400);
        }
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);
        return response()->json([
            'message' => 'Password changed successfully',
        ], 200);
    }
    // change profile picture
    public function changeProfileImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $user = $request->user();

        $imageKit = new ImageKit(
            config('imagekit.public_key'),
            config('imagekit.private_key'),
            config('imagekit.url_endpoint')
        );

        // Delete old image if exists
        if ($user->image_file_id) {
            try {
                $deleteResponse = $imageKit->deleteFile($user->image_file_id);
                if (!isset($deleteResponse->success) || !$deleteResponse->success) {
                    // Optional: log the delete failure
                    \Log::warning("Failed to delete old image: " . $user->image_file_id);
                }
            } catch (\Exception $e) {
                \Log::error("ImageKit delete error: " . $e->getMessage());
            }
        }

        $file = $request->file('image');

        // Upload new image
        try {
            $upload = $imageKit->upload([
                'file' => base64_encode(file_get_contents($file)),
                'fileName' => $file->getClientOriginalName(),
                'folder' => '/avatars',
            ]);

            $user->update([
                'image' => $upload->result->url,
                'image_file_id' => $upload->result->fileId,
            ]);

            return response()->json([
                'message' => 'Avatar updated successfully',
                'image' => $user->image,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }
}
