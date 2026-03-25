<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ImageKit\ImageKit;
class ProductController extends Controller
{

    public function index(Request $request)
    {

        $products = Product::with(['category', 'brand', 'variants'])
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'message' => 'Retrieved products successfully',
            'product' => $products
        ]);

    }
    // 

    public function searchBySlug($slug)
    {
        $product = Product::with(['category', 'brand', 'variants'])
            ->whereRaw('slug ILIKE ?', [$slug])
            ->first();

        if (!$product) {
            return response()->json([
                'message' => "Product $slug not found"
            ]);
        }
        return response()->json([
            'message' => "get product succesfully",
            'product' => $product
        ]);
    }
    // search by id 
    public function searchById($id)
    {
        try {
            $product = Product::with(['category', 'brand', 'variants'])->findOrFail($id);

            return response()->json([
                'message' => "search product successfully",
                'product' => $product
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Product $id not found"
            ], 404);
        }
    }

    // added product
    public function store(Request $request)
    {
        $product = Product::all();
        // validate
        $validated = $request->validate([
            'productName' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'image' => 'nullable|image|max:5120'
        ]);

        // create slug
        $slug = Str::slug($validated['productName']);
        // check if slug exist
        if (Product::where('slug', $slug)->exists()) {
            $slug = $slug . '-' . Str::lower(Str::random(5));
        }
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
                'folder' => '/products',
            ]);

            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }

            // Delete old ImageKit image if exists
            if ($product->image) {
                try {
                    $imageKit->deleteFile($product->image);
                } catch (\Exception $e) {

                }
            }

            // Set new image URL
            $product->image = $upload->result->url;
        }

        // create product
        $product = Product::create([
            'productName' => $validated['productName'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'],
            'image' => $validated['image'] ?? null,
        ]);
        return response()->json([
            'message' => 'Created product successfully',
            'product' => $product
        ], 201);
    }
    // updated product
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        // validate
        $validated = $request->validate([
            'productName' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'image' => 'nullable|image|max:5120'
        ]);

        // generate new slug if name changed
        if ($validated['productName'] !== $product->productName) {
            $product->slug = Str::slug($validated['productName']);
        }

        // handle image upload if provided
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
                'folder' => '/products',
            ]);

            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }

            // Delete old ImageKit image if exists
            if ($product->image) {
                try {
                    $imageKit->deleteFile($product->image);
                } catch (\Exception $e) {
                    // ignore errors
                }
            }

            // Set new image URL
            $product->image = $upload->result->url;
        }

        // update other product fields
        $product->update([
            'productName' => $validated['productName'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'],
        ]);

        return response()->json([
            'message' => 'Updated product successfully',
            'product' => $product
        ]);
    }
    // destroy product
    public function destroy($id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'message' => "product id: $id not found"
            ], 404);
        }
        $product->delete();
        DB::statement("SELECT setval(pg_get_serial_sequence('products', 'id'), coalesce(max(id), 0) + 1, false) FROM products;");
        return response()->json([
            'message' => 'product deleted successfully',
        ], 200);
    }
    public function uploadImageToProduct(Request $request, $id)
    {
        $product = Product::findOrFail($id);

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
                'folder' => '/products',
            ]);

            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }

            // Save new image URL
            $product->image = $upload->result->url;
            $product->save(); 
        }

        return response()->json([
            'message' => 'Upload image successfully',
            'product' => $product->fresh(),
        ]);
    }
}

