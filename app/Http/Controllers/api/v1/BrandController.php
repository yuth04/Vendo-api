<?php

namespace App\Http\Controllers\Api\v1;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'name' => "required|string|max:255"
        ]);

        $slug = Str::slug($validated['name']);
        if (Brand::where('slug', $slug)->exists()) {
            $slug = $slug . '-' . Str::lower(Str::random(5));
        }
        $brand = Brand::create([
            'name' => $validated['name'],
            'slug' => $slug
        ]);

        return response()->json([
            'message' => 'Created brand succcessfully'
            ,
            'brand' => $brand
        ], 201);
    }
    public function updateBrand(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);
        if (!$brand) {
            return response()->json([
                'message' => "Brand $id not found"
            ]);
        }
        
        $validated = $request->validate([
            'name' => "required|string|max:255"
        ]);
          $slug = Str::slug($validated['name']);

        if ($validated['name'] !== $brand->name) {
            $brand->slug = $slug;
        }

        $brand->update([
            'name' => $validated['name'],
            'slug'=>$slug
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
        $brand->delete();
        DB::statement("SELECT setval(pg_get_serial_sequence('brands', 'id'), coalesce(max(id), 0) + 1, false) FROM brands;");
        return response()->json([
            'message' => 'Deleted brand successfully'
        ]);
    }

}
