<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;

use App\Models\Discount;
use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\NewProductNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ImageKit\ImageKit;
use App\Traits\NotifiesUsers;
class ProductController extends Controller
{
    use NotifiesUsers;
    private const SIMILARITY_THRESHOLD = 0.25;
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
    // slug search
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
            'status' => $validated['status'] ?? 'active',
            'created_by' => null,
            'updated_by' => null,
        ]);
        $user = Auth::user();
        $auditInfo = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'image' => $user->image,
            'created_at' => now()->format('d-m-Y g:i:s A'),
        ];
        $product->update([
            'created_by' => $auditInfo,
            'updated_by' => $auditInfo
        ]);
        // NOTIFICATION
        $this->notifyAllUsers(
            'New Product Available!',
            'Check out our new product "' . $product->productName . '" — available now.',
            'product',
            'shopping-bag',
            "/products"
        );

        $this->refreshAllIndex();
        return response()->json([
            'message' => 'Created product successfully',
            'product' => $product
        ], 201);
    }
    // updated product
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!in_array($user->role, ['super-admin', 'admin'])) {
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
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
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
            'updated_by' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'image' => $user->image,
                'updated_at' => now()->format('d-m-Y g:i:s A'),
            ],
        ]);
        // Recalculate discount price if product has active discount
        $product->refresh();
        if ($product->discount_id) {
            $discount = Discount::find(
                $product->discount_id
            );
            if ($discount && $discount->is_active) {
                if ($discount->type === 'percent') {

                    $discountPrice =
                        $product->price -
                        ($product->price * $discount->amount / 100);
                } else {
                    $discountPrice = $product->price - $discount->amount;
                }
                $product->update([
                    'discount_price' => max(0, round($discountPrice, 2))
                ]);
            } else {
                // Discount inactive/expired
                $product->update(['discount_price' => 0]);
            }
        }
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

    public function topSales()
    {
        try {
            $products = DB::table('order_items')
                ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('payments', 'orders.id', '=', 'payments.order_id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->where('payments.status', 'paid')
                ->where('products.status', 'active')
                ->where('orders.created_at', '>=', now()->subMonths(3))
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
                ->having(DB::raw('SUM(order_items.quantity)'), '>=', 1)
                ->orderByDesc('total_sales')
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
    // New product
    public function newProducts()
    {
        try {

            $products = DB::table('products')
                ->where('products.status', 'active')
                ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->where('products.created_at', '>=', Carbon::now()->subMonths(3))
                ->select(
                    'products.id',
                    'products.productName',
                    'products.slug',
                    'products.image',
                    'products.price',
                    'brands.name as brand_name',
                    'categories.name as category_name',
                    'products.created_at'
                )
                ->orderByDesc('products.created_at')
                ->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No new products found',
                    'products' => []
                ], 404);
            }

            $products = $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'productName' => $product->productName,
                    'slug' => $product->slug,
                    'image' => $product->image,
                    'price' => number_format($product->price, 2),
                    'brand' => $product->brand_name,
                    'category' => $product->category_name,
                    'created_at' => Carbon::parse($product->created_at)
                        ->format('d-m-Y h:i:s A'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'New products retrieved successfully',
                'total' => $products->count(),
                'products' => $products
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve new products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get All Product Images
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


