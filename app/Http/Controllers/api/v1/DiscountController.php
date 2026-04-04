<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscountController extends Controller
{
    public function index(Request $request)
    {

        $discounts = Discount::with('product')
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
        $discount = Discount::with('product')->find($id);

        if (!$discount) {
            return response()->json([
                'message' => 'Discount not found'
            ], 404);
        }

        $product = $discount->product;

        // Calculate discount price for response
        if ($discount->type === 'percent') {
            $discountPrice = $product->price - ($product->price * $discount->amount / 100);
        } else {
            $discountPrice = $product->price - $discount->amount;
        }

        $product->discount_price = max(0, round($discountPrice, 2));

        return response()->json([
            'message' => 'Discount retrieved successfully',
            'data' => $discount,
            'product' => $product
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'amount' => 'required|numeric',
            'type' => 'required|in:percent,fixed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        return DB::transaction(function () use ($validated) {
            // 1. Create the discount
            $discount = Discount::create($validated);

            // 2. Find the product
            $product = Product::findOrFail($validated['product_id']);

            // 3. Calculate the price
            $price = (float) $product->price;
            $amount = (float) $validated['amount'];

            $newPrice = $validated['type'] === 'percent'
                ? $price - ($price * $amount / 100)
                : $price - $amount;

            // 4. SAVE to the actual database column
            $product->update([
                'discount_price' => max(0, round($newPrice, 2))
            ]);

            return response()->json([
                'message' => 'Discount created and product column updated',
                'data' => $discount->load('product') // This will now show the DB value
            ], 201);
        });
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
            'product_id' => 'sometimes|exists:products,id',
            'discount_category_id' => 'nullable|exists:discount_categories,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|in:percent,fixed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $discount->update($validated);

        $discount->load(['product']);

        $product = $discount->product;

        if (!$product) {
            return response()->json([
                'message' => 'Product not found for this discount'
            ], 404);
        }

        // ✅ SAFE calculation
        $discountPrice = $discount->type === 'percent'
            ? $product->price - ($product->price * $discount->amount / 100)
            : $product->price - $discount->amount;

        $product->update([
            'discount_price' => max(0, round($discountPrice, 2))
        ]);

        return response()->json([
            'message' => 'Discount updated successfully',
            'data' => $discount
        ]);
    }

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

    public function destroy($id)
    {
        $discount = Discount::findOrFail($id);
        $discount->delete();
        DB::statement("SELECT setval(pg_get_serial_sequence('discounts', 'id'), coalesce(max(id), 0) + 1, false) FROM discounts;");
        return response()->json([
            'message' => 'Active discount deleted successfully',


        ]);
    }


}