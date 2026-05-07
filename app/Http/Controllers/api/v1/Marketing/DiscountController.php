<?php

namespace App\Http\Controllers\api\v1\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscountController extends Controller
{

    public function index(Request $request)
    {

        $discounts = Discount::with('products')

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
            'discount_category_id' => 'nullable|exists:discount_categories,id',
            'description' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:percent,fixed',
            'product_id' => 'required|array',
            'product_id.*' => 'exists:products,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status'
        ]);

        // 1. Check for  discounts for ANY of the products in the array
        $exists = Discount::whereIn('product_id', $validated['product_id'])

            ->where('start_date', $request->start_date)
            ->where('end_date', $request->end_date)
            ->exists();
        if ($exists) {
            return response()->json([
                'message' => 'One or more products already have a discount in this period'
            ], 409);
        }

        return DB::transaction(function () use ($validated) {
            $discountData = $validated;
            $discountData['product_id'] = $validated['product_id'][0];

            $discount = Discount::create($discountData);

            $products = Product::whereIn('id', $validated['product_id'])->get();

            foreach ($products as $product) {
                $price = (float) $product->price;
                $amount = (float) $validated['amount'];

                $discountPrice = $validated['type'] === 'percent'
                    ? $price - ($price * $amount / 100)
                    : $price - $amount;

                $product->update([
                    'discount_id' => $discount->id, // Link them
                    'discount_price' => max(0, round($discountPrice, 2))
                ]);
            }

            return response()->json([
                'message' => 'Discount applied to ' . $products->count() . ' products successfully',
                'data' => $discount->load('products')
            ], 201);
        });
    }
    // public function update(Request $request, $id)
    // {
    //     // 1. Load the discount with all associated products
    //     $discount = Discount::with('products')->find($id);

    //     if (!$discount) {
    //         return response()->json(['message' => 'Discount not found'], 404);
    //     }

    //     $validated = $request->validate([
    //         'product_id'=>'sometime|array',
    //         'product_id.*'=>'exists:product,id',
    //         'discount_category_id' => 'nullable|exists:discount_categories,id',
    //         'name' => 'nullable|string|max:255',
    //         'description' => 'nullable|string',
    //         'amount' => 'sometimes|numeric|min:0',
    //         'type' => 'sometimes|in:percent,fixed',
    //         'start_date' => 'nullable|date',
    //         'end_date' => 'nullable|date|after_or_equal:start_date',
    //     ]);

    //     // 2. Use a Transaction to ensure data consistency
    //     return DB::transaction(function () use ($discount, $validated) {

    //         // Update the Discount record
    //         $discount->update($validated);
    //         foreach ($discount->products as $product) {
    //             $price = (float) $product->price;
    //             $amount = (float) $discount->amount;

    //             $discountPrice = $discount->type === 'percent'
    //                 ? $price - ($price * $amount / 100)
    //                 : $price - $amount;

    //             $product->update([
    //                 'discount_price' => max(0, round($discountPrice, 2))
    //             ]);
    //         }

    //         return response()->json([
    //             'message' => 'Discount and all associated products updated successfully',
    //             'data' => $discount->load('products')
    //         ]);
    //     });
    // }

    // public function update(Request $request, $id)
    // {
    //     $discount = Discount::findOrFail($id);

    //     $validated = $request->validate([


    //         'product_id' => 'sometimes|exists:products,id',

    //         'name' => 'nullable|string|max:255',
    //         'description' => 'nullable|string',
    //         'amount' => 'sometimes|numeric|min:0',
    //         'type' => 'sometimes|in:percent,fixed',
    //         'start_date' => 'nullable|date',
    //         'end_date' => 'nullable|date|after_or_equal:start_date',
    //     ]);

    //     $discount->update($validated);

    //     $discount = $discount->fresh();

    //     $product = $discount->product;

    //     if (!$product) {
    //         return response()->json([
    //             'message' => 'Product not found for this discount'
    //         ], 404);
    //     }

    //     // calculate discount price safely

    //     if ($discount->type === 'percent') {
    //         $discountPrice = $product->price - ($product->price * ($discount->amount / 100));
    //     } else {
    //         $discountPrice = $product->price - $discount->amount;
    //     }


    //     $product->update([
    //         'discount_price' => max(0, round($discountPrice, 2))
    //     ]);

    //     return response()->json([
    //         'message' => 'Discount updated successfully',

    //         'data' => $discount->load(['product', 'discountCategory'])
    //     ]);
    // }

    public function update(Request $request, $id)
    {
        $discount = Discount::with('products')->find($id);

        if (!$discount) {
            return response()->json([
                'message' => 'Discount not found'
            ], 404);
        }

        $validated = $request->validate([
            'discount_category_id' => 'nullable|exists:discount_categories,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|in:percent,fixed',
            'product_id' => 'sometimes|array',
            'product_id.*' => 'exists:products,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        return DB::transaction(function () use ($discount, $validated) {
            $discountData = $validated;

            // save first product_id only
            if (isset($validated['product_id'])) {
                $discountData['product_id'] = $validated['product_id'][0];
            }

            $discount->update($discountData);

            // Add new products
            if (isset($validated['product_id'])) {

                $products = Product::whereIn('id', $validated['product_id'])->get();

                foreach ($products as $product) {

                    $price = (float) $product->price;
                    $amount = (float) $discount->amount;

                    $discountPrice = $discount->type === 'percent'
                        ? $price - ($price * $amount / 100)
                        : $price - $amount;

                    $product->update([
                        'discount_id' => $discount->id,
                        'discount_price' => max(0, round($discountPrice, 2))
                    ]);
                }
            }
            // Response
            return response()->json([
                'message' => 'Discount updated successfully',
                'data' => $discount->fresh()->load('products')
            ]);
        });
    }
    public function destroy($id)
    {
        $discount = Discount::findOrFail($id);
        Product::where('discount_id', $discount->id)->update([
            'discount_id' => null,
            'discount_price' => null
        ]);
        $discount->delete();

        DB::statement("SELECT setval(pg_get_serial_sequence('discounts', 'id'), coalesce(max(id), 0) + 1, false) FROM discounts;");
        return response()->json([
            'message' => 'Active discount deleted successfully',


        ]);
    }
    public function addProducts(Request $request, $discountId)
    {
        // Find the existing discount
        $discount = Discount::findOrFail($discountId);

        $validated = $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

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
                'message' => "$addedCount new products added to discount: {$discount->name}",
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