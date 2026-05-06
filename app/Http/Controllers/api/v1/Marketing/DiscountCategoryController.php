<?php

namespace App\Http\Controllers\api\v1\Marketing;

use App\Http\Controllers\Controller;
use App\Models\DiscountCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ImageKit\ImageKit;

class DiscountCategoryController extends Controller
{
  
    public function index()
    {
        $category = DiscountCategory::with(['discounts.products','coupons'])->where('is_active',true)->orderBy('id','asc')->get();

        if (!$category) {
            return response()->json([
                'message' => "No discount category found"
            ], 404);
        }

        return response()->json([
            'message' => 'Discount category fetched successfully',
            'data' => $category
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'banner_image' => 'nullable|image|max:5120',
            'is_active'=>'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        $slug = Str::slug($request->title);
        $count = DiscountCategory::where('slug', 'like', "{$slug}%")->count();
        $slug = $count ? "{$slug}-{$count}" : $slug;
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
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }

            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }
        $category = DiscountCategory::create([
            'title' => $request->title,
            'slug' => $slug,
            'description' => $request->description,
            'banner_image' => $imageUrl,
            'image_file_id' => $imageFileId,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active'=>$request->is_active
        ]);

        return response()->json([
            'message' => 'Discount category created successfully',
            'data' => $category
        ], 201);
    }


    public function show($id)
    {
        $category = DiscountCategory::where('is_active',true)->find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Discount category not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Discount category fetched successfully',
            'data' => $category
        ]);
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
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'banner_image' => 'nullable|image|max:5120',
            'is_active'=>'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        // $title = $request->title ?? $discountCategory->title;

        if ($request->has('title') && $request->title !== $discountCategory->title) {
            $slug = Str::slug($request->title);

            // avoid duplicate slug (exclude current record)
            $count = DiscountCategory::where('slug', 'like', "{$slug}%")
                ->where('id', '!=', $discountCategory->id)
                ->count();

            $slug = $count ? "{$slug}-{$count}" : $slug;
        } else {
            $slug = $discountCategory->slug;
        }
        // keep old data
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
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }

            // delete old image safely
            if ($discountCategory->image_file_id) {
                try {
                    $imageKit->deleteFile($discountCategory->image_file_id);
                } catch (\Exception $e) {
                }
            }

            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }

        $discountCategory->update([
            'title' => $request->title ?? $discountCategory->title,
            'slug' => $slug,
            'description' => $request->description ?? $discountCategory->description,
            'banner_image' => $imageUrl,
            'is_active'=>$request->is_active,
            'image_file_id' => $imageFileId,
            'start_date' => $request->start_date ?? $discountCategory->start_date,
            'end_date' => $request->end_date ?? $discountCategory->end_date,
        ]);

        return response()->json([
            'message' => 'Discount category updated successfully',
            'data' => $discountCategory
        ]);
    }

    public function destroy($id)
    {
        $category = DiscountCategory::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Discount category not found'
            ], 404);
        }
        try {
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );
            // Delete image from ImageKit
            if ($category->image_file_id) {
                $imageKit->deleteFile($category->image_file_id);
            }

            if ($category->images) {
                foreach ($category->images as $img) {
                    if (!empty($img->file_id)) {
                        $imageKit->deleteFile($img->file_id);
                    }
                }
            }
            $category->delete();
            DB::statement("SELECT setval(pg_get_serial_sequence('discount_categories', 'id'), coalesce(max(id), 0) + 1, false) FROM discount_categories;");
            return response()->json([
                'message' => 'product deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'failed to delete product image',
                'error' => $e->getMessage()
            ], 500);
        }

    }
}