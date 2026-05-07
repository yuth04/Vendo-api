<?php

namespace App\Http\Controllers\api\v1\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ImageKit\ImageKit;

class DiscountController extends Controller
{

    public function index(Request $request)
    {

        $discounts = Discount::with(['products','coupon'])

            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'message' => $discounts->isEmpty()
                ? 'No active discounts found'
                : 'Active discounts retrieved successfully',
            'discount' => $discounts,
        ]);
    }

    public function show($id)
    {
        $discount = Discount::with('products')->find($id);

        if (!$discount) {
            return response()->json(['message' => 'Discount not found'], 404);
        }

        $discountedProducts = $discount->products->map(function ($product) use ($discount) {
            $price = (float) $product->price;
            $amount = (float) $discount->amount;

            $discountPrice = $discount->type === 'percent'
                ? $price - ($price * $amount / 100)
                : $price - $amount;

            $product->discount_price = max(0, round($discountPrice, 2));
            return $product;
        });

        return response()->json([
            'message' => 'Discount retrieved successfully',
            'data' => $discount,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:percent,fixed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'banner_image' => 'nullable|image|max:5120',
            'is_active' => 'nullable|boolean'
        ]);
        $slug = Str::slug($request->name);
        $count = Discount::where('slug', 'like', "{$slug}%")->count();
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

        $discount = Discount::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'banner_image' => $imageUrl,
            'image_file_id' => $imageFileId
        ]);
        return response()->json([
            'message' => 'Discount created successfully',
            'data' => $discount
        ], 201);
    }
    public function update(Request $request, $id)
    {
        $discount = Discount::find($id);

        if (!$discount) {
            return response()->json([
                'message' => 'Discount not found'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|in:percent,fixed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'banner_image' => 'nullable|image|max:5120',
            'is_active' => 'nullable|boolean',
        ]);

        // upload image if exists
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
            if ($discount->image_file_id) {
                try {
                    $imageKit->deleteFile($discount->image_file_id);
                } catch (\Exception $e) {
                }
            }

            $validated['banner_image'] = $upload->result->url;
            $validated['image_file_id'] = $upload->result->fileId;
        }

        return DB::transaction(function () use ($discount, $validated) {

            $discount->update($validated);

            foreach ($discount->products as $product) {

                $price = (float) $product->price;
                $amount = (float) $discount->amount;

                $discountPrice = $discount->type === 'percent'
                    ? $price - ($price * $amount / 100)
                    : $price - $amount;

                $product->update([
                    'discount_price' => max(0, round($discountPrice, 2))
                ]);
            }

            return response()->json([
                'message' => 'Discount and products updated successfully',
                'data' => $discount->load('products')
            ]);
        });
    }
    public function destroy($id)
    {
        $discount = Discount::find($id);

        if (!$discount) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $discount->delete();

        return response()->json([
            'message' => 'Discount deleted successfully'
        ]);
    }
    public function addProducts(Request $request, $discountId)
    {
        // Find the existing discount
        $discount = Discount::find($discountId);
        if (!$discount) {
            return response()->json([
                'message' => 'Discount not found'
            ]);
        }
        $validated = $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        if ($discount->products()->whereIn('products.id', $validated['product_ids'])->exists()) {
            return response()->json([
                'message' => 'Some products already have this discount'
            ], 409);
        }
        return DB::transaction(function () use ($validated, $discount) {
            $products = Product::whereIn('id', $validated['product_ids'])->get();
            $addedCount = 0;

            foreach ($products as $product) {

                if ($product->discount_id == $discount->id)
                    continue;
                $price = (float) $product->price;
                $amount = (float) $discount->amount;

                $discountPrice = $discount->type === 'percent'
                    ? $price - ($price * $amount / 100)
                    : $price - $amount;

                $product->update([
                    'discount_id' => $discount->id,
                    'discount_price' => max(0, round($discountPrice, 2))
                ]);

                $addedCount++;
            }

            return response()->json([
                'message' => 'Add new product to discount successfully',
                'discount' => $discount->load('products')
            ]);
        });
    }
    public function removeProduct(Request $request, $discountId)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        // Find the product that belongs to this specific discount
        $product = Product::where('id', $validated['product_id'])
            ->where('discount_id', $discountId)
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'Product not found or not associated with this discount.'
            ], 404);
        }

        // Reset the discount fields
        $product->update([
            'discount_id' => null,
            'discount_price' => 0 // Or keep original price depending on your logic
        ]);

        return response()->json([
            'message' => "Product {$product->id} removed from discount successfully."
        ]);
    }

}