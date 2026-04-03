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
        $productId = $request->query('product_id');

        $discounts = Discount::active()
            ->when($productId, fn($q) => $q->where('product_id', $productId))
            ->with('product')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'message' => $discounts->isEmpty()
                ? 'No active discounts found'
                : 'Active discounts retrieved successfully',
            'data' => $discounts,
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
            'discount_category_id' => 'nullable|exists:discount_categories,id',
            'name' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:percent,fixed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Check for existing active discount to prevent overlaps
        $exists = Discount::where('product_id', $validated['product_id'])
            ->active()
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This product already has an active discount'
            ], 409);
        }

        $discount = Discount::create($validated);

        return response()->json([
            'message' => 'Discount created successfully',
            'data' => $discount
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $discount = Discount::findOrFail($id);

        $validated = $request->validate([
            'product_id' => 'sometimes|exists:products,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|in:percent,fixed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $discount->update($validated);
        $discount = $discount->fresh();

        $product = $discount->product;

        // Recalculate based on updated values
        if ($discount->type === 'percent') {
            $discountPrice = $product->price - ($product->price * ($discount->amount / 100));
        } else {
            $discountPrice = $product->price - $discount->amount;
        }

        // Persist the calculated price to the products table
        $product->update([
            'discount_price' => max(0, round($discountPrice, 2))
        ]);

        return response()->json([
            'message' => 'Discount updated successfully',
            'data' => $discount->load('product'),
        ]);
    }

    public function destroy($id)
    {
        $discount = Discount::find($id);

        if (!$discount) {
            return response()->json(['message' => 'Discount not found'], 404);
        }

        // Before deleting, reset the product's discount_price
        $product = $discount->product;
        if ($product) {
            $product->update(['discount_price' => null]);
        }

        $discount->delete();

        // Reset Postgres ID sequence
        DB::statement("SELECT setval(pg_get_serial_sequence('discounts', 'id'), coalesce(max(id), 0) + 1, false) FROM discounts;");

        return response()->json([
            'message' => 'Discount deleted successfully and product price reset',
        ]);
    }

    /**
     * Helper to get the calculated price directly
     */
    public function discountedPrice(Product $product)
    {
        $discount = $product->discounts()->active()->latest()->first();

        if (!$discount) {
            return $product->price;
        }

        return match ($discount->type) {
            'percent' => round($product->price * (1 - $discount->amount / 100), 2),
            'fixed' => max(0, round($product->price - $discount->amount, 2)),
            default => $product->price,
        };
    }
}