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
    public function uploadProfileImage(Request $request)
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
