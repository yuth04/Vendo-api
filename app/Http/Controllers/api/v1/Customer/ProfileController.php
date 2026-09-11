<?php

namespace App\Http\Controllers\api\v1\Customer;

use App\Http\Controllers\Controller;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use ImageKit\ImageKit;

class ProfileController extends Controller
{
    public function __construct(protected SystemLogService $systemLogServicer)
    {

    }
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
        $oldValues = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ];
        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
        ]);
        $newValues = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ];

        // Audit Log: Update Profile Name
        $this->systemLogServicer->log(
            action: 'update_name',
            module: 'Profile',
            description: "User {$user->email} updated their name.",
            entity: $user,
            oldValues: $oldValues,
            newValues: $newValues
        );
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
            $oldValues = [
                'image' => $user->image,
                'image_file_id' => $user->image_file_id,
            ];
            $user->update([
                'image' => $upload->result->url,
                'image_file_id' => $upload->result->fileId,
            ]);
            $newValues = [
                'image' => $user->image,
                'image_file_id' => $user->image_file_id,
            ];

            // Audit Log: Upload Profile Image
            $this->systemLogServicer->log(
                action: 'upload_avatar',
                module: 'Profile',
                description: "User {$user->email} updated their profile image.",
                entity: $user,
                oldValues: $oldValues,
                newValues: $newValues
            );
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
