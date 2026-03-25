<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartController extends Controller
{
    protected $discountService;

    public function __construct(DiscountService $discountService)
    {
        $this->discountService = $discountService;
    }


    //    Helper: Get or Create Cart and return along with current guest token

    private function getCart(Request $request)
    {
        if (Auth::check()) {
            return Cart::firstOrCreate(['user_id' => Auth::id()]);
        }

        $guestToken = $request->header('guest_token') ?: (string) Str::uuid();
        return Cart::firstOrCreate(['guest_token' => $guestToken]);
    }


    // Helper: Standardize Cart Response (Used by index and store)

    private function formatCartResponse(Cart $cart, string $message){
        $cart->load('items.variant.product.discount');

        $totalOriginal = 0;
        $totalFinal = 0;

        $items = $cart->items->map(function ($item) use (&$totalOriginal, &$totalFinal) {
            $variant = $item->variant;
            $product = $variant->product;

            $basePrice = (float) ($product->price ?? 0);
            $discountedPrice = $this->discountService->discountedPrice($product);

            // Logic: Use discounted price if it exists, otherwise use base price
            $effectivePrice = $discountedPrice ?? $basePrice;

            // Calculate specific totals for THIS line item
            $lineSubtotal = $effectivePrice * $item->quantity;
            $lineOriginal = $basePrice * $item->quantity;

            // Add to running cart totals
            $totalOriginal += $lineOriginal;
            $totalFinal += $lineSubtotal;

            return [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'subtotal' => round($lineSubtotal, 2), 
                'variant' => [
                    'id' => $variant->id,
                    'size' => $variant->size,
                    'color' => $variant->color,
                    'base_price' => round($basePrice,2),
                    'discounted_price' => round($discountedPrice, 2), 
                    'product' => [
                        'product_id' => $product->id,
                        'product_name' => $product->productName,
                    ]
                ],
            ];
        });

        return response()->json([
            'message' => $message,
            'guest_token' => $cart->guest_token,
            'cart' => [
                'id' => $cart->id,
                'total_price' => round($totalFinal, 2),
                'total_discount' => round($totalOriginal - $totalFinal, 2),
                'items' => $items
            ]
        ]);
    }
    //   View cart
    public function index(Request $request)
    {
        $cart = $this->getCart($request);
        if ($cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty'
            ]);
        }
        return $this->formatCartResponse($cart, 'Cart retrieved');
    }

    /**
     * ✅ Add item to cart
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $variant = ProductVariant::where('id', $request->product_variant_id)
            ->where('product_id', $request->product_id)
            ->firstOrFail();

        $cart = $this->getCart($request);

        // Find existing item or create new instance
        $cartItem = $cart->items()->firstOrNew([
            'product_variant_id' => $variant->id
        ]);

        $newQty = $cartItem->quantity + $request->quantity;

        if ($newQty > $variant->stock) {
            return response()->json(['message' => "Only {$variant->stock} items left in stock"], 400);
        }

        $cartItem->quantity = $newQty;
        $cartItem->save();

        return response()->json([
            'message' => 'Added to cart succcesfully'
        ]);
    }

    /**
     * ✅ Update quantity of specific item
     */
    public function updateItem(Request $request, $itemId)
    {
        $request->validate(['quantity' => 'required|integer|min:1']);

        $cart = $this->getCart($request);
        $item = $cart->items()->with('variant')->where('id', $itemId)->firstOrFail();

        if ($request->quantity > $item->variant->stock) {
            return response()->json(['message' => 'Insufficient stock'], 400);
        }

        $item->update(['quantity' => $request->quantity]);

        return $this->formatCartResponse($cart, 'Quantity updated');
    }

    /**
     * ✅ Remove single item
     */
    public function removeItem(Request $request, $itemId)
    {
        $cart = $this->getCart($request);
        $cart->items()->where('id', $itemId)->delete();

        return $this->formatCartResponse($cart, 'Item removed from cart');
    }

    /**
     * ✅ Clear entire cart
     */
    public function clearCart(Request $request)
    {
        $cart = $this->getCart($request);
        $cart->items()->delete();

        return response()->json(['message' => 'Cart cleared']);
    }

    /**
     * ✅ Sync guest cart to user cart (Post-Login)
     */
    public function sync(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.product_variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1'
        ]);

        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);

        DB::transaction(function () use ($request, $cart) {
            foreach ($request->items as $itemData) {
                $variant = ProductVariant::findOrFail($itemData['product_variant_id']);

                $cartItem = $cart->items()->firstOrNew([
                    'product_variant_id' => $variant->id
                ]);

                // Merge quantities
                $cartItem->quantity += $itemData['quantity'];

                // Cap at stock limit
                if ($cartItem->quantity > $variant->stock) {
                    $cartItem->quantity = $variant->stock;
                }

                $cartItem->save();
            }
        });

        return $this->formatCartResponse($cart, 'Cart synced successfully');
    }
}