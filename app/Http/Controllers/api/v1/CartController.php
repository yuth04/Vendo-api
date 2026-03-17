<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    // Get current user's cart
    public function index()
    {
        $cart = Cart::with('items.variant.product')
            ->where('user_id', Auth::id())
            ->first();

        if (!$cart) {
            return response()->json([
                'message' => "Cart is empty"
            ]);
        }

        $discountController = new DiscountController();

        return response()->json([
            'message' => "Get cart successfully",
            'cart' => [
                'id' => $cart->id,
                'total_quantity' => $cart->items->sum('quantity'),
                'total_price' => $cart->items->sum(function ($item) use ($discountController) {
                    return $item->quantity * $discountController->discountedPrice($item->variant->product);
                }),
                'items' => $cart->items->map(function ($item) use ($discountController) {
                    $discountedPrice = $discountController->discountedPrice($item->variant->product);

                    return [
                        'id' => $item->id,
                        'quantity' => $item->quantity,
                        'variant' => [
                            'id' => $item->variant->id,
                            'size' => $item->variant->size,
                            'color' => $item->variant->color,
                            'price' => (float) $item->variant->product->price, // original price
                            'discounted_price' => $discountedPrice, // new field
                            'stock' => $item->variant->stock,
                            'product' => [
                                'id' => $item->variant->product->id,
                                'name' => $item->variant->product->productName,
                            ],
                        ],
                        'subtotal' => $item->quantity * $discountedPrice,
                    ];
                }),
            ]
        ]);
    }

    // Add  item in cart
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1'
        ]);

        // Ensure variant belongs to product
        $variant = ProductVariant::where('id', $request->product_variant_id)
            ->where('product_id', $request->product_id)
            ->firstOrFail();

        $cart = Cart::firstOrCreate([
            'user_id' => Auth::id()
        ]);

        // Get existing iitem (f any)
        $cartItem = $cart->items()
            ->where('product_variant_id', $variant->id)
            ->first();

        $currentQuantity = $cartItem?->quantity ?? 0;
        $newQuantity = $currentQuantity + $request->quantity;

        // Stock validation
        if ($newQuantity > $variant->stock) {
            return response()->json([
                'message' => 'Not enough available stock',
                'available_stock' => $variant->stock,
                'current_in_cart' => $currentQuantity
            ], 400);
        }

        // Save item
        $cartItem = $cart->items()->updateOrCreate(
            ['product_variant_id' => $variant->id],
            ['quantity' => $newQuantity]
        );

        return response()->json([
            'message' => 'Added item to cart successfully',
            'item' => $cartItem
        ], 201);
    }

    // Remove entire cart
    public function destroy()
    {
        $cart = Cart::where('user_id', Auth::id())->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found'
            ], 404);
        }

        $cart->delete();

        return response()->json([
            'message' => 'Cart removed successfully'
        ], 200);
    }

    // Optional: Remove single item from cart
    public function clearCart($itemId)
    {
        $cart = Cart::where('user_id', Auth::id())->first();

        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        $item = $cart->items()->where('id', $itemId)->first();

        if (!$item) {
            return response()->json(['message' => 'Item not found in cart'], 404);
        }

        $item->delete();

        return response()->json([
            'message' => 'Item removed from cart successfully'
        ], 200);
    }

    // Optional: Update quantity of a cart item
    public function updateItem(Request $request, $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $cart = Cart::where('user_id', Auth::id())->firstOrFail();

        $item = $cart->items()->where('id', $itemId)->firstOrFail();
        $variant = $item->variant;

        if ($request->quantity > $variant->stock) {
            return response()->json([
                'message' => 'Not enough stock',
                'available_stock' => $variant->stock
            ], 400);
        }

        $item->update(['quantity' => $request->quantity]);

        return response()->json([
            'message' => 'Item quantity updated successfully',
            'item' => $item
        ]);
    }
}
