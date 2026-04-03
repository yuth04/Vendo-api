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
<<<<<<< HEAD
<<<<<<< Updated upstream
=======
        $product = $discount->product;

        // Recalculate discount price here
        if ($discount->type === 'percent') {
            $discountPrice = $product->price - ($product->price * $discount->amount / 100);
        } else {
            $discountPrice = $product->price - $discount->amount;
        }

        $product->discount_price = max(0, $discountPrice);

>>>>>>> Stashed changes
=======

>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
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
<<<<<<< HEAD

<<<<<<< Updated upstream
=======
        $exists = Discount::where('product_id', $validated['product_id'])
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->exists();
        if ($exists) {
            return response()->json([
                'message' => 'This product already has an active discount'
            ], 409);
        }
>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
        $discount = Discount::create([
            'product_id' => $validated['product_id'],
            'name' => $validated['name'] ?? null,
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'type' => $validated['type'] ?? 'percent',
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);
=======
        // Check for existing active discount
        $exists = Discount::where('product_id', $validated['product_id'])
            ->active()
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Product already has an active discount'], 409);
        }

        $discount = Discount::create($validated);
>>>>>>> Stashed changes

        return response()->json([
            'message' => 'Discount created successfully',
            'data' => $discount
        ], 201);
    }
    // update
    public function update(Request $request, $id)
    {
        $discount = Discount::findOrFail($id);

<<<<<<< Updated upstream
        $validated = $request->validate([
            'product_id' => 'sometimes|exists:products,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|in:percent,fixed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
=======
>>>>>>> Stashed changes

        $discount->update($request->all());
        $discount = $discount->fresh();

        $product = $discount->product;

        if ($discount->type === 'percent') {
            $discountPrice = $product->price - ($product->price * ($discount->amount / 100));
        } else {
            $discountPrice = $product->price - $discount->amount;
        }

        // Save to database
        $product->update([
            'discount_price' => max(0, round($discountPrice, 2))
        ]);

        return response()->json([
            'message' => 'Discount updated successfully',
            'data' => $discount->load('product'),
        ]);
    }
    // public function update(Request $request, $id)
    // {
    //     $discount = Discount::findOrFail($id);

    //     $validated = $request->validate([
    //         'name' => 'nullable|string',
    //         'amount' => 'sometimes|numeric|min:0',
    //         'type' => 'sometimes|in:percent,fixed',
    //         'start_date' => 'nullable|date',
    //         'end_date' => 'nullable|date|after_or_equal:start_date',
    //     ]);

    //     $discount->update($validated);
    //     $product = Product::with('activeDiscount')->find($discount->product_id);

    //     return response()->json([
    //         'message' => 'Discount updated successfully',
    //         'data' => $discount->fresh(),
    //         'product' => $product
    //     ]);
    // }

    public function destroy($id)
    {
        $discount = Discount::active()->findOrFail($id);
        $discount->delete();
        DB::statement("SELECT setval(pg_get_serial_sequence('discounts', 'id'), coalesce(max(id), 0) + 1, false) FROM discounts;");
        return response()->json([
            'message' => 'Active discount deleted successfully',
        ]);
    }
<<<<<<< HEAD
<<<<<<< Updated upstream
=======

>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
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
=======
>>>>>>> Stashed changes
}
