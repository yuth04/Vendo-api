<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use ImageKit\ImageKit;

class ProductImageController extends Controller
{
    protected $imageKit;

    public function __construct()
    {
        $this->imageKit = new ImageKit(
            config('imagekit.public_key'),
            config('imagekit.private_key'),
            config('imagekit.url_endpoint')
        );
    }
    public function getAll()
    {
        $variants = ProductVariant::with('images')->get();

        return response()->json([
            'message' => "Images retrieved successfully",
            'variants' => $variants
        ]);
    }
    public function index($variant_id)
    {
        $images = ProductImage::where('variant_id', $variant_id)
            ->orderBy('position')
            ->get();

        return response()->json([
            'message' => "Search Image successfully",
            "Images" => $images
        ]);

    }
    public function store(Request $request)
    {
        $request->validate([
            'variant_id' => 'required|exists:product_variants,id',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'position' => 'nullable|integer'
        ]);

        if (!$request->hasFile('image')) {
            return response()->json([
                "message" => "No image uploaded"
            ], 400);
        }

        $file = $request->file('image');

        // Upload to ImageKit
        $upload = $this->imageKit->upload([
            'file' => base64_encode(file_get_contents($file)),
            'fileName' => time() . '_' . $file->getClientOriginalName(),
            'folder' => '/variant_images',
        ]);

        $image = ProductImage::create([
            'variant_id' => $request->variant_id,
            'image' => $upload->result->url,
            'image_file_id' => $upload->result->fileId,
            'position' => $request->position ?? 1
        ]);

        return response()->json([
            "message" => "Image uploaded successfully",
            "data" => $image
        ], 201);
    }
    public function update(Request $request, $id)
    {
        $image = ProductImage::findOrFail($id);

        $request->validate([
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'position' => 'nullable|integer'
        ]);

        // If new image uploaded
        if ($request->hasFile('image')) {

            $file = $request->file('image');

            // Delete old image from ImageKit
            if ($image->image_file_id) {
                $this->imageKit->deleteFile($image->image_file_id);
            }

            // Upload new image
            $upload = $this->imageKit->upload([
                'file' => base64_encode(file_get_contents($file)),
                'fileName' => time() . '_' . $file->getClientOriginalName(),
                'folder' => '/variant_images',
            ]);

            $image->image_url = $upload->result->url;
            $image->image_file_id = $upload->result->fileId;
        }

        // Update position if provided
        if ($request->filled('position')) {
            $image->position = $request->position;
        }

        $image->save();

        return response()->json([
            "message" => "Image updated successfully",
            "data" => $image
        ]);
    }
    // Delete image
    public function destroy($id)
    {
        $image = ProductImage::findOrFail($id);

        $image->delete();

        return response()->json([
            "message" => "Image deleted successfully"
        ]);
    }
}
