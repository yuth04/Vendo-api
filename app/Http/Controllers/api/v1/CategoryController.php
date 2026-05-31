<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ImageKit\ImageKit;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('products')
            ->where('is_active', true)
            ->orderBy('id', 'asc')
            ->get();
        return response()->json([
            'message' => "Retrieved categories successfully",
            'category' => $categories
        ]);
    }

    public function showById($id)
    {
        try {
            $category = Category::with(['children.products', 'products'])->where('status', true)->findOrFail($id);
            return response()->json([
                'message' => 'Category found successfully',
                'category' => $category
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => "Category $id not found",
            ], 404);
        }
    }

    public function showBySlug($slug)
    {
        $category = Category::with(['children.products', 'products'])
            ->whereRaw('slug ILIKE ?', [$slug])
            ->get();

        if (!$category) {
            return response()->json([
                'message' => "Category $slug not found"
            ], 404);
        }

        return response()->json([
            'message' => "Category found successfully",
            'category' => $category
        ]);
    }

    // Create category
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'parent_category_id' => 'nullable|exists:parent_categories,id', // Point to parent_categories table
                'is_active' => 'nullable|boolean',
                'image' => 'nullable|image|max:5120'
            ]);

            $parentId = $validated['parent_category_id'] ?? null;
            $slug = $this->generateUniqueSlug($validated['name'], $parentId);
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
                    'folder' => '/categories',
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

            $category = Category::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'parent_category_id' => $parentId,
                'is_active' => $validated['is_active'] ?? true,
                'image' => $imageUrl,
                'image_file_id' => $imageFileId,
            ]);

            return response()->json([
                'message' => "Created category successfully",
                'category' => $category
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to create category', 'error' => $e->getMessage()], 500);
        }
    }

    // Update category
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_category_id' => 'nullable|exists:categories,id',
            'is_active' => 'nullable|boolean',
            'image' => 'nullable|image|max:5120'
        ]);

        // $parentId = $validated['parent_category_id'] ?? $category->parent_category_id;
        $parentId = $request->has('parent_category_id')
            ? $request->parent_category_id
            : $category->parent_category_id;
        // Generate slug unique per parent
        $slug = $this->generateUniqueSlug($validated['name'], $parentId, $id);

        $imageUrl = $category->image;
        $imageFileId = $category->image_file_id;

        if ($request->hasFile('image')) {

            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            $upload = $imageKit->upload([
                'file' => fopen($request->file('image')->getRealPath(), "r"),
                'fileName' => $request->file('image')->getClientOriginalName(),
                'folder' => '/categories',
            ]);

            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }
            //delete old image from ImageKit if exists
            if ($category->image_file_id) {
                try {
                    $imageKit->deleteFile($category->image_file_id);
                } catch (\Exception $e) {
                    // ignore if already deleted
                }
            }
            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }
        $category->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'parent_category_id' => $parentId,
            'is_active' => $validated['is_active'] ?? $category->is_active,
            'image' => $imageUrl,
            'image_file_id' => $imageFileId,
        ]);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category->fresh()
        ]);
    }
    // Helper function to generate unique slug per parent

    private function generateUniqueSlug(string $name, ?int $parentId, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (
            Category::where('slug', $slug)
                ->where('parent_category_id', $parentId)
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
    // Delete category
    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'message' => "Category id: $id not found"
            ], 404);
        }

        try {
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            //  delete main image
            if ($category->image_file_id) {
                $imageKit->deleteFile($category->image_file_id);
            }

            // delete related images
            if ($category->images) {
                foreach ($category->images as $img) {
                    if (!empty($img->file_id)) {
                        $imageKit->deleteFile($img->file_id);
                    }
                }
            }

            //  delete category
            $category->delete();
            DB::statement("SELECT setval(pg_get_serial_sequence('categories', 'id'), coalesce(max(id), 0) + 1, false) FROM categories;");
            return response()->json([
                'message' => 'Category deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadImageToCategory(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        // Validate incoming image
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            // Upload image to ImageKit
            $upload = $imageKit->upload([
                'file' => fopen($request->file('image')->getRealPath(), "r"),
                'fileName' => $request->file('image')->getClientOriginalName(),
                'folder' => '/categories',
            ]);

            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }

            // Save new image URL
            $category->image = $upload->result->url;
            $category->save(); // save to database
        }

        return response()->json([
            'message' => 'Upload image successfully',
            'category' => $category->fresh(), // now includes uploaded image
        ]);
    }

}