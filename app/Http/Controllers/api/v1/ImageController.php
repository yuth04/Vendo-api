<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use ImageKit\ImageKit;

class ImageController extends Controller
{
    // public function uploadProfileImage(Request $request)
    // {
    //     $request->validate([
    //         'image' => 'required|image|max:5120',
    //     ]);

    //     $user = $request->user();

    //     $imageKit = new ImageKit(
    //         env('IMAGEKIT_PUBLIC_KEY'),
    //         env('IMAGEKIT_PRIVATE_KEY'),
    //         env('IMAGEKIT_URL_ENDPOINT')
    //     );

    //     // Delete old image if exists
    //     if ($user->image_file_id) {
    //         try {
    //             $deleteResponse = $imageKit->deleteFile($user->image_file_id);
    //             if (!isset($deleteResponse->success) || !$deleteResponse->success) {
    //                 // Optional: log the delete failure
    //                 \Log::warning("Failed to delete old image: " . $user->image_file_id);
    //             }
    //         } catch (\Exception $e) {
    //             \Log::error("ImageKit delete error: " . $e->getMessage());
    //         }
    //     }

    //     $file = $request->file('image');

    //     // Upload new image
    //     try {
    //         $upload = $imageKit->upload([
    //             'file' => base64_encode(file_get_contents($file)),
    //             'fileName' => $file->getClientOriginalName(),
    //             'folder' => '/avatars',
    //         ]);

    //         $user->update([
    //             'image' => $upload->result->url,
    //             'image_file_id' => $upload->result->fileId,
    //         ]);

    //         return response()->json([
    //             'message' => 'Avatar updated successfully',
    //             'image' => $user->image,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Failed to upload image: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }


}
