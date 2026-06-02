<?php

namespace App\Http\Controllers\api\v1;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\ParentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ParentCategoryController extends Controller
{

    public function index()
    {
        $baseCategories = ParentCategory::with(['categories.products'])
            ->where('status', true)
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($base) {
                return [
                    'id' => $base->id,
                    'name' => $base->name,
                    'status' => $base->status,
                    'created_at' => $base->created_at->format('d-m-Y g:i:s A'),
                    'updated_at' => $base->updated_at,
                    'categories' => $base->categories->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'slug' => $category->slug,
                            'is_active' => $category->is_active,
                            'parent_category_id' => $category->parent_category_id,
                            'created_at' => $category->created_at->format('d-m-Y g:i:s A'),
                            'updated_at' => $category->updated_at,
                            'image' => $category->image,
                            'products' => $category->products->map(function ($product) {
                                return [
                                    'id' => $product->id,
                                    'productName' => $product->productName,
                                    'slug' => $product->slug,
                                    'description' => $product->description,
                                    'price' => $product->price,
                                    'category_id' => $product->category_id,
                                    'brand_id' => $product->brand_id,
                                    'created_at' => $product->created_at->format('d-m-Y g:i:s A'),
                                    'updated_at' => $product->updated_at->format('d-m-Y g:i:s A'),
                                    'image' => $product->image,
                                ];
                            }),
                        ];
                    }),
                ];
            });

        return response()->json([
            'message' => 'Retrieved base-category successfully',
            'data' => $baseCategories
        ], 200);
    }
    // search by id
    public function search($id)
    {
        $parentCategories = ParentCategory::with(['categories.products'])->where('status', 'active')->find($id);
        if (!$parentCategories) {
            return response()->json([
                'message' => 'Search not found',

            ]);
        }
        return response()->json([
            'message' => 'Search successfully',
            'data' => $parentCategories
        ]);
    }
    // search by name
    public function searchName(Request $request)
    {
        $request->validate([
            'name' => 'required|string'
        ]);
        $name = $request->input('name');

        $parentCategories = ParentCategory::with(['categories.products'])
            ->where('name', 'ILIKE', "%{$name}%")
            ->get();
        if ($parentCategories->isEmpty()) {
            return response()->json([
                'message' => 'No parent category found for the given name'
            ], 400);
        }
        return response()->json([
            'message' => 'Search successfully',
            'data' => $parentCategories
        ]);
    }

    public function store(Request $request)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can delete product variant'
            ], 403);
        }
        $validated = $request->validate([
            'name' => "required|string|max:255",
            'status' => "nullable|boolean"
        ]);
        if (ParentCategory::where('name', $validated['name'])->exists()) {
            return response()->json([
                'message' => 'Parent category with this name already exists'
            ], 400);
        }
        $parentCategory = ParentCategory::create([
            'name' => $validated['name'],
            'status' => $validated['status']
        ]);

        return response()->json([
            'message' => 'Created successfully',
            'Data' => $parentCategory
        ]);
    }

    public function update(Request $request, $id)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can delete product variant'
            ], 403);
        }
        $parentCategory = ParentCategory::find($id);

        $validated = Validator::make($request->all(), [
            'name' => "required|string|max:255|unique:parent_categories,name,$id",
            'status' => 'required|in:0,1'
        ], [
            'name.unique' => 'This category name already exists.'
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validated->errors()
            ], 422);
        }
        $parentCategory->update($validated->validated());
        return response()->json([
            'message' => 'Updated successfully',
            'Data' => $parentCategory
        ]);
    }
    public function destroy($id)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can delete product variant'
            ], 403);
        }
        $parentCategory = ParentCategory::findOrFail($id);
        if (!$parentCategory) {
            return response()->json([
                'message' => "parentCategory $id not found"
            ], 404);
        }
        $parentCategory->delete();
        DB::statement("SELECT setval(pg_get_serial_sequence('parent_categories', 'id'), coalesce(max(id), 0) + 1, false) FROM parent_categories;");
        return response()->json([
            'message' => 'Deleted successfully',
        ], 200);
    }
}
