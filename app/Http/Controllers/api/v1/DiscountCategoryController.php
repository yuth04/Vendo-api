<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\DiscountCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ImageKit\ImageKit;

class DiscountCategoryController extends Controller
{
    public function index()
    {
        $categories = DiscountCategory::with('discounts')->get();
        return response()->json([
            'message' => 'List of Discount Categories',
            'discounts' => $categories
        ]);
    }
    // show by ID
    public function show($id)
    {
        $category = DiscountCategory::with('discounts')->find($id);
        if (!$category) {
            return response()->json([
                'message' => 'Discount category not found'
            ], 404);
        }
        return response()->json([
            'message' => 'Discount category details',
            'discount_category' => $category
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'banner_image' => 'nullable|image|max:5120',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);
        $imageUrl = null;
        $imageFileId = null;
        if ($request->hasFile('banner_image')) {
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );
            $upload = $imageKit->upload([
                'file' => fopen($request->file('banner_image')->getRealPath(), "r"),
                'fileName' => $request->file('banner_image')->getClientOriginalName(),
                'folder' => '/banner_images',
            ]);
            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Error uploading banner image',
                    'error' => $upload->error->message,
                ], 400);
            }

            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }
        $category = DiscountCategory::create([
            'title' => $request->title,
            'description' => $request->description,
            'banner_image' => $imageUrl,
            'image_file_id' => $imageFileId,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        return response()->json([
            'message' => 'Discount category created successfully',
            'data' => $category
        ], 201);
    }
    public function update(Request $request, $id)
    {
        $discountCategory = DiscountCategory::find($id);
        if (!$discountCategory) {
            return response()->json([
                'message' => 'Discount category not found'
            ], 404);
        }
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'banner_image' => 'nullable|image|max:5120',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);
        $imageUrl = $discountCategory->banner_image;
        $imageFileId = $discountCategory->image_file_id;
        if ($request->hasFile('banner_image')) {
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            $upload = $imageKit->upload([
                'file' => fopen($request->file('banner_image')->getRealPath(), "r"),
                'fileName' => $request->file('banner_image')->getClientOriginalName(),
                'folder' => '/banner_images',
            ]);
            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Error uploading banner image',
                    'error' => $upload->error->message,
                ], 400);
            }
            if ($discountCategory->image_file_id) {
                try {
                    $imageKit->deleteFile($discountCategory->image_file_id);
                } catch (\Exception $e) {
                    // ignore if already deleted
                }
            }
            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }
        $discountCategory->update([
            'title' => $request->title,
            'description' => $request->description,
            'banner_image' => $imageUrl,
            'image_file_id' => $imageFileId,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        return response()->json([
            'message' => 'Discount category updated successfully',
            'data' => $discountCategory
        ]);

    }

    public function destroy($id)
    {
        $discountCategory = DiscountCategory::find($id);
        if (!$discountCategory) {
            return response()->json([
                'message' => 'Discount category not found'
            ], 404);
        }
        // delete banner image from ImageKit if exists
        try {
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            //  delete main image
            if ($discountCategory->image_file_id) {
                $imageKit->deleteFile($discountCategory->image_file_id);
            }

            // delete related images
            if ($discountCategory->images) {
                foreach ($discountCategory->images as $img) {
                    if (!empty($img->file_id)) {
                        $imageKit->deleteFile($img->file_id);
                    }
                }
            }

            //  delete category
            $discountCategory->delete();
            DB::statement("SELECT setval(pg_get_serial_sequence('discount_categories', 'id'), coalesce(max(id), 0) + 1, false) FROM discount_categories;");
            return response()->json([
                'message' => 'Category deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'Discount category deleted successfully'
        ]);
    }
}
