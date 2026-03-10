<?php

namespace App\Http\Controllers\api\v1;

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

        $discounts = Discount::when($productId, function ($query, $productId) {
            return $query->where('product_id', $productId);
        })
            ->with('product')
            ->get();
        if ($discounts->isEmpty()) {
            return response()->json([
                'message' => "Discount is empty"
            ]);
        }
        return response()->json([
            'message' => 'Discounts retrieved successfully',
            'data' => $discounts,
        ]);
    }
    public function show($id)
    {
        $discount = Discount::with('product')->findOrFail($id);
        if (!$discount) {
            return response()->json([
                'message' => "Discount not found"
            ]);
        }
        return response()->json([
            'message' => 'Discount retrieved successfully',
            'data' => $discount,
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'type' => 'nullable|in:percent,fixed',
        ]);

        $discount = Discount::create([
            'product_id' => $validated['product_id'],
            'name' => $validated['name'] ?? null,
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'type' => $validated['type'] ?? 'percent',
        ]);

        return response()->json([
            'message' => 'Discount created successfully',
            'data' => $discount,
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
        ]);

        $discount->update($validated);

        return response()->json([
            'message' => 'Discount updated successfully',
            'data' => $discount,
        ]);
    }
    public function destroy($id)
    {
        $discount = Discount::findOrFail($id);
        $discount->delete();
        DB::statement("SELECT setval(pg_get_serial_sequence('discounts', 'id'), coalesce(max(id), 0) + 1, false) FROM discounts;");
        return response()->json([
            'message' => 'Discount deleted successfully',
        ]);
    }
    public function discountedPrice(Product $product)
    {
        $discount = $product->discounts()->latest()->first();

        if (!$discount) {
            return $product->price;
        }

        return match ($discount->type) {
            'percent' => round($product->price * (1 - $discount->amount / 100), 2),
            'fixed' => round($product->price - $discount->amount, 2),
            default => $product->price,
        };
    }
}
