<?php

namespace App\Http\Controllers\api\v1;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ImageKit\ImageKit;
class BrandController extends Controller
{
    public function index()
    {
        $brand = Brand::with(['products'])->get();
        return response()->json([
            'message' => "get brands successfully",
            'brands' => $brand
        ]);
    }
    // show by id
    public function showById($id)
    {
        $brand = Brand::with('products')->find($id);

        if (!$brand) {
            return response()->json([
                'message' => 'Brand not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'brand' => $brand
        ]);
    }
    // show by Slug
    public function showBySlug($slug)
    {
        $brand = Brand::with(['products'])->where('slug', $slug)->first();

        if (!$brand) {
            return response()->json([
                'message' => 'brand not found'
            ]);
        }
        return response()->json([
            'success' => true,
            'brand' => $brand
        ]);
    }
    // store
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'image' => 'nullable|image|max:5120',
        ]);

        $slug = Str::slug($validated['name']);
        if (Brand::where('slug', $slug)->exists()) {
            $slug = $slug . '-' . Str::lower(Str::random(5));
        }

        $imageUrl = null;
        $imageFileId = null;

        if ($request->hasFile('image')) {

            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            $upload = $imageKit->upload([
                'file' => fopen($request->file('image')->getRealPath(), "r"),
                'fileName' => $request->file('image')->getClientOriginalName(),
                'folder' => '/brands',
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
        $brand = Brand::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'status' => $validated['status'] ?? 'active',
            'image' => $imageUrl,
            'image_file_id' => $imageFileId,
        ]);

        return response()->json([
            'message' => 'Created brand succcessfully',
            'brand' => $brand
        ], 201);
    }
    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);
        if (!$brand) {
            return response()->json([
                'message' => "Brand $id not found"
            ]);
        }

        $validated = $request->validate([
            'name' => "required|string|max:255",
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|max:5120',

        ]);
        $slug = Str::slug($validated['name']);

        if ($validated['name'] !== $brand->name) {
            $brand->slug = $slug;
        }
        // update imageKit
        $imageUrl = $brand->image;
        $imageFileId = $brand->image_file_id;

        if ($request->hasFile('image')) {

            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            $upload = $imageKit->upload([
                'file' => fopen($request->file('image')->getRealPath(), "r"),
                'fileName' => $request->file('image')->getClientOriginalName(),
                'folder' => '/brands',
            ]);

            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }
            // DELETE OLD IMAGE (safe)
            if ($brand->image_file_id) {
                try {
                    $imageKit->deleteFile($brand->image_file_id);
                } catch (\Exception $e) {
                    // ignore if already deleted
                }
            }
            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }
        $brand->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'status' => $validated['status'] ?? $brand->status,
            'image' => $imageUrl,
            'image_file_id' => $imageFileId ?? $brand->image_file_id,
        ]);

        return response()->json([
            'message' => 'Updated brand succcessfully',
            'brand' => $brand
        ], 201);
    }
    public function destroy($id)
    {
        $brand = Brand::findOrFail($id);
        if (!$brand) {
            return response()->json([
                'message' => "Brand $id not found"
            ]);
        }
        try {
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );
            // Delete image from ImageKit
            if ($brand->image_file_id) {
                $imageKit->deleteFile($brand->image_file_id);
            }

            if ($brand->images) {
                foreach ($brand->images as $img) {
                    if (!empty($img->file_id)) {
                        $imageKit->deleteFile($img->file_id);
                    }
                }
            }
            $brand->delete();
            DB::statement("SELECT setval(pg_get_serial_sequence('brands', 'id'), coalesce(max(id), 0) + 1, false) FROM brands;");
            return response()->json([
                'message' => 'brand deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'failed to delete brand image',
                'error' => $e->getMessage()
            ], 500);
        }



    }

}
