<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
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
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can create product'
            ], 403);
        }
        $validated = $request->validate([
            'productName' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'image' => 'nullable|image|max:5120',
            'status' => 'nullable|string|max:50'
        ]);
        if (Product::where('productName', $validated['productName'])->exists()) {
            return response()->json([
                'message' => 'Product already exists. You can only create it once.'
            ], 409);
        }
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
        $this->refreshAllIndex();
        return response()->json([
            'message' => 'Created product successfully',
            'product' => $product
        ], 201);
    }
    // updated product
    public function update(Request $request, $id)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can update product'
            ], 403);
        }
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
        $this->refreshAllIndex();
        return response()->json([
            'message' => 'Updated product successfully',
            'product' => $product->fresh()
        ]);
    }
    // destroy product
    public function destroy($id)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can delete product'
            ], 403);
        }

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
            $this->refreshAllIndex();
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

    // =========================
    // Get All Product Images
    // =========================

    public function allImages()
    {
        $images = Product::whereNotNull('image')
            ->select('id', 'image')
            ->get();

        return response()->json($images);
    }
    // Visual Search
    public function searchImage(Request $request)
    {
        $request->validate([
            'image' => 'nullable|image|max:5120',
        ]);

        try {

            $response = Http::attach(
                'image',
                file_get_contents(
                    $request->file('image')->path()
                ),
                $request->file('image')
                    ->getClientOriginalName()
            )->post(
                    env('PYTHON_AI_URL') . '/search'
                );

            if ($response->failed()) {

                return response()->json([
                    'success' => false,
                    'message' => 'Python server error',
                    'error' => $response->body()
                ], 500);
            }

            $results = $response->json();

            $productIds = collect($results)
                ->pluck('id');

            $products = Product::with('variants')
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $data = collect($results)->map(
                function ($result) use ($products) {

                    $product = $products->get(
                        $result['id']
                    );

                    if (!$product) {
                        return null;
                    }

                    return [
                        'id' => $product->id,
                        'name' => $product->productName,
                        'price' => $product->price,
                        'image' => $product->image,
                        'size' => optional(
                            $product->variants->first()
                        )->size,
                        'similarity' => $result['similarity']
                    ];
                }
            )->filter()->values();

            return response()->json([
                'message' => 'Search completed',
                'data' => $data
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Connection failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Text Search
    public function searchByText(Request $request)
    {
        $request->validate([
            'query' => 'required|string'
        ]);

        try {

            $query = strtolower($request->input('query'));

            // =====================
            // SIZE
            // =====================
            $size = null;

            if (preg_match('/size\s*([a-zA-Z0-9]+)/i', $query, $m)) {
                $size = strtoupper($m[1]);
            }

            // =====================
            // COLOR
            // =====================
            $color = null;

            if (preg_match('/color\s*([a-zA-Z]+)/i', $query, $m)) {
                $color = strtolower($m[1]);
            }

            // =====================
            // PRICE
            // =====================
            $minPrice = null;
            $maxPrice = null;

            // under / less than / lower than / max / maximum
            if (preg_match('/(?:price\s*)?(?:under|below|less\s*than|lower\s*than|max(?:imum)?)\s*\$?\s*(\d+(?:\.\d+)?)/i', $query, $m)) {
                $maxPrice = (float) $m[1];
            }

            // above / more than / over / min / minimum
            if (preg_match('/(?:price\s*)?(?:above|over|more\s*than|greater\s*than|min(?:imum)?)\s*\$?\s*(\d+(?:\.\d+)?)/i', $query, $m)) {
                $minPrice = (float) $m[1];
            }

            // between X and Y
            if (preg_match('/(?:price\s*)?between\s*\$?\s*(\d+(?:\.\d+)?)\s*(?:and|to|-)\s*\$?\s*(\d+(?:\.\d+)?)/i', $query, $m)) {
                $minPrice = (float) $m[1];
                $maxPrice = (float) $m[2];
            }

            // =====================
            // CLEAN QUERY
            // =====================
            $cleanQuery = $query;

            // remove price expressions
            $cleanQuery = preg_replace('/price\s*(under|below|above|over|between|less\s*than|lower\s*than|more\s*than|greater\s*than|max(?:imum)?|min(?:imum)?)?/i', '', $cleanQuery);
            $cleanQuery = preg_replace('/(under|below|above|over|between|less\s*than|lower\s*than|more\s*than|greater\s*than)\s*\$?\s*\d+(?:\.\d+)?/i', '', $cleanQuery);
            $cleanQuery = preg_replace('/\$?\s*\d+(?:\.\d+)?\s*(to|-)\s*\$?\s*\d+(?:\.\d+)?/i', '', $cleanQuery);
            $cleanQuery = preg_replace('/\$?\s*\d+(?:\.\d+)?/i', '', $cleanQuery);

            // remove size and color expressions
            $cleanQuery = preg_replace('/size\s*[a-zA-Z0-9]+/i', '', $cleanQuery);
            $cleanQuery = preg_replace('/color\s*[a-zA-Z]+/i', '', $cleanQuery);

            // remove leftover filler words
            $cleanQuery = preg_replace('/\b(with|and|or|for|the|a|an|in|under|above|over|between)\b/i', ' ', $cleanQuery);

            // clean extra spaces
            $cleanQuery = trim(preg_replace('/\s+/', ' ', $cleanQuery));

            // fallback if query becomes empty
            if (empty($cleanQuery)) {
                $cleanQuery = $request->input('query');
            }

            // =====================
            // PYTHON AI SEARCH
            // =====================
            $response = Http::asJson()->post(
                env('PYTHON_AI_URL') . '/search/text',
                [
                    'query' => $cleanQuery
                ]
            );

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Python server error',
                    'error' => $response->body()
                ], 500);
            }

            // filter by similarity threshold
            $results = collect($response->json())
                ->filter(
                    fn($r) => $r['similarity'] >= self::SIMILARITY_THRESHOLD
                )
                ->values()
                ->toArray();

            if (empty($results)) {
                return response()->json([
                    'message' => 'Item not found.',
                ], 404);
            }

            $productIds = collect($results)->pluck('id');

            // =====================
            // DB FILTER
            // =====================
            $productsQuery = Product::with(['variants', 'category', 'brand'])
                ->whereIn('id', $productIds);

            if ($size || $color) {
                $productsQuery->whereHas('variants', function ($q) use ($size, $color) {

                    if ($size) {
                        $q->where('size', $size);
                    }

                    if ($color) {
                        $q->where('color', $color);
                    }
                });
            }

            if ($maxPrice) {
                $productsQuery->where('price', '<=', $maxPrice);
            }

            if ($minPrice) {
                $productsQuery->where('price', '>=', $minPrice);
            }

            $products = $productsQuery->get()->keyBy('id');

            // =====================
            // RESPONSE
            // =====================
            $data = collect($results)->map(
                function ($result) use ($products) {

                    $product = $products->get($result['id']);

                    if (!$product) {
                        return null;
                    }

                    return [
                        'id' => $product->id,
                        'name' => $product->productName,
                        'category' => optional($product->category)->name,
                        'brand' => optional($product->brand)->name,
                        'description' => $product->description,
                        'price' => $product->price,
                        'color' => optional(
                            $product->variants->first()
                        )->color,
                        'image' => $product->image,
                        'size' => optional(
                            $product->variants->first()
                        )->size,
                        'similarity' => $result['similarity']
                    ];
                }
            )->filter()->values();

            if ($data->isEmpty()) {
                return response()->json([
                    'message' => 'Item not found.',
                ], 404);
            }

            return response()->json([
                'message' => 'Search completed successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // public function searchByText(Request $request)
    // {
    //     $request->validate([
    //         'query' => 'required|string'
    //     ]);

    //     try {

    //         $query = strtolower($request->input('query'));

    //         // =====================
    //         // SIZE
    //         // =====================
    //         $size = null;

    //         if (preg_match('/size\s*([a-zA-Z0-9]+)/i', $query, $m)) {
    //             $size = strtoupper($m[1]);
    //         }

    //         // =====================
    //         // COLOR
    //         // =====================
    //         $color = null;

    //         if (preg_match('/color\s*([a-zA-Z]+)/i', $query, $m)) {
    //             $color = strtolower($m[1]);
    //         }

    //         // =====================
    //         // CLEAN QUERY
    //         // =====================
    //         $cleanQuery = preg_replace('/size\s*[a-zA-Z0-9]+/i', '', $query);
    //         $cleanQuery = preg_replace('/color\s*[a-zA-Z]+/i', '', $cleanQuery);
    //         $cleanQuery = str_replace('with', '', $cleanQuery);
    //         $cleanQuery = trim($cleanQuery);

    //         // =====================
    //         // PYTHON AI SEARCH
    //         // =====================
    //         $response = Http::asJson()->post(
    //             env('PYTHON_AI_URL') . '/search/text',
    //             [
    //                 'query' => $cleanQuery
    //             ]
    //         );

    //         if ($response->failed()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Python server error',
    //                 'body' => $response->body()
    //             ], 500);
    //         }

    //         $results = $response->json();

    //         $productIds = collect($results)->pluck('id');

    //         // =====================
    //         // DB FILTER
    //         // =====================
    //         $productsQuery = Product::with('variants')
    //             ->whereIn('id', $productIds);

    //         $productsQuery->whereHas('variants', function ($q) use ($size, $color) {

    //             if ($size) {
    //                 $q->where('size', $size);
    //             }

    //             if ($color) {
    //                 $q->where('color', $color);
    //             }
    //         });

    //         $products = $productsQuery->get()->keyBy('id');

    //         // =====================
    //         // RESPONSE
    //         // =====================
    //         $data = collect($results)->map(function ($result) use ($products) {

    //             $product = $products->get($result['id']);

    //             if (!$product)
    //                 return null;

    //             return [
    //                 'id' => $product->id,
    //                 'name' => $product->productName,
    //                 'category' => optional(
    //                     $product->category
    //                 )->name,
    //                 'brand' => optional(
    //                     $product->brand
    //                 )->name,
    //                 'description' => $product->description,
    //                 'price' => $product->price,
    //                 'color' => optional(
    //                     $product->variants->first()
    //                 )->color,
    //                 'image' => $product->image,
    //                 'size' => optional(
    //                     $product->variants->first()
    //                 )->size,
    //                 'similarity' => $result['similarity']
    //             ];
    //         })->filter()->values();
    //         if ($data->isEmpty()) {
    //             return response()->json([
    //                 'message' => 'Item not found.',
    //             ], 404);
    //         }
    //         return response()->json([
    //             'message' => 'Search completed successfully',
    //             'data' => $data
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    private function refreshAllIndex()
    {
        try {
            Http::post(env('PYTHON_AI_URL') . '/refresh');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh index',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}


