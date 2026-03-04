<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use ImageKit\ImageKit;

class ImageController extends Controller
{
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
        // Upload image to ImageKit
        $upload = $imageKit->upload([
            'file' => fopen($request->file('image')->getRealPath(), "r"),
            'fileName' => $request->file('image')->getClientOriginalName(),
            'folder' => '/avatars',
        ]);
        if (isset($upload->error)) {
            return response()->json([
                'message' => 'Upload failed',
                'error' => $upload->error->message,
            ], 400);
        }
        // Delete old ImageKit image
        if ($user->image) {
            try {
                $imageKit->deleteFile($user->image);
            } catch (\Exception $e) {

            }
        }
        // Save new image URL to user
        $user->image = $upload->result->url;
        $user->save();

        return response()->json([
            'message' => 'Avatar updated successfully',
            'image' => $user->image,
        ]);

    }
    // 


}
