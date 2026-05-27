<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;

use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ImageKit\ImageKit;
class ProductController extends Controller
{

    public function index(Request $request)
    {

        $products = Product::with(['category', 'brand', 'variants'])
            ->where('status', 'active')
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
            ->where('status', 'active')
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
            $product = Product::with(['category', 'brand', 'variants'])->where('status', 'active')->findOrFail($id);

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
        // ✅ Validate
        $validated = $request->validate([
            'productName' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'image' => 'nullable|image|max:5120',
            'status' => 'nullable|string|max:50'
        ]);

        // ✅ Generate slug
        $slug = Str::slug($validated['productName']);

        if (Product::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::lower(Str::random(5));
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
                'folder' => '/products',
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
        // Create product
        $product = Product::create([
            'productName' => $validated['productName'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'],
            'image' => $imageUrl,
            'image_file_id' => $imageFileId,
            'status' => $validated['status']
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

        // ✅ Validate
        $validated = $request->validate([
            'productName' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'image' => 'nullable|image|max:5120',
            'status' => 'nullable|string|max:50'
        ]);

        // ✅ Handle slug
        $slug = $product->slug;

        if ($validated['productName'] !== $product->productName) {
            $slug = Str::slug($validated['productName']);

            if (Product::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug .= '-' . Str::lower(Str::random(5));
            }
        }

        $imageUrl = $product->image;
        $imageFileId = $product->image_file_id;

        if ($request->hasFile('image')) {

            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

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

            // DELETE OLD IMAGE (safe)
            if ($product->image_file_id) {
                try {
                    $imageKit->deleteFile($product->image_file_id);
                } catch (\Exception $e) {
                    // ignore if already deleted
                }
            }

            //  SET NEW IMAGE DATA
            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }

        //Final update
        $product->update([
            'productName' => $validated['productName'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'],
            'image' => $imageUrl,
            'image_file_id' => $imageFileId,
            'status' => $validated['status'] ?? $product->status,
        ]);

        return response()->json([
            'message' => 'Updated product successfully',
            'product' => $product->fresh()
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
        try {
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );
            // Delete image from ImageKit
            if ($product->image_file_id) {
                $imageKit->deleteFile($product->image_file_id);
            }

            if ($product->images) {
                foreach ($product->images as $img) {
                    if (!empty($img->file_id)) {
                        $imageKit->deleteFile($img->file_id);
                    }
                }
            }
            $product->delete();
            DB::statement("SELECT setval(pg_get_serial_sequence('products', 'id'), coalesce(max(id), 0) + 1, false) FROM products;");
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
    // Most sold
    public function mostSoldProducts()
    {
        $topProducts = DB::table('order_items')
            ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('payments', 'orders.id', '=', 'payments.order_id')
            ->select(
                'products.id',
                'products.productName',
                'products.image',
                DB::raw('SUM(order_items.quantity) as total_sold')
            )
            ->groupBy(
                'products.id',
                'products.productName',
                'products.image'
            )
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();

        return response()->json([
            'message' => 'Top products retrieved successfully',
            'data' => $topProducts
        ]);
    }
    // Top saled products
    public function topSales()
    {
        try {

            $products = DB::table('order_items')

                ->join(
                    'product_variants',
                    'order_items.product_variant_id',
                    '=',
                    'product_variants.id'
                )

                ->join(
                    'products',
                    'product_variants.product_id',
                    '=',
                    'products.id'
                )

                ->join(
                    'orders',
                    'order_items.order_id',
                    '=',
                    'orders.id'
                )

                ->join(
                    'payments',
                    'orders.id',
                    '=',
                    'payments.order_id'
                )

                ->leftJoin(
                    'categories',
                    'products.category_id',
                    '=',
                    'categories.id'
                )

                ->where('payments.status', 'paid')

                ->select(
                    'products.id',
                    'products.productName',
                    'products.slug',
                    'products.image',
                    'products.price',
                    'categories.name as category_name',

                    DB::raw('SUM(order_items.quantity) as total_sales')
                )

                ->groupBy(
                    'products.id',
                    'products.productName',
                    'products.slug',
                    'products.image',
                    'products.price',
                    'categories.name'
                )

                ->orderByDesc('total_sales')

                ->take(8)

                ->get()

                ->map(function ($product) {

                    return [
                        'id' => $product->id,

                        'productName' => $product->productName,

                        'slug' => $product->slug,

                        'image' => $product->image ?? null,

                        'price' => number_format($product->price, 2),

                        'category' => $product->category_name,

                        'total_sales' => (int) $product->total_sales,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Top sale products retrieved successfully',
                'products' => $products,
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve top sale products',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function searchImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        try {

            $response = Http::attach(
                'image',
                fopen($request->file('image')->getRealPath(), 'r'),
                $request->file('image')->getClientOriginalName()
            )->post('http://127.0.0.1:5000/search');

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Python AI server error',
                    'error' => $response->body(),
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Image search completed successfully',
                'data' => $response->json(),
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Connection failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

