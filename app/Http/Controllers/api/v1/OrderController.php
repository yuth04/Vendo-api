<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\DiscountService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    protected $discountService;

    public function __construct(DiscountService $discountService)
    {
        $this->discountService = $discountService;
    }

    /**
     * ✅ Checkout - only for logged-in users
     */
    public function checkout(Request $request)
    {
        // Guests cannot checkout
        if (!Auth::check()) {
            return response()->json(['message' => 'Login required to place an order'], 401);
        }

        $request->validate([
            'address_id' => 'required|exists:addresses,id'
        ]);

        $user = Auth::user();

        // Use DB cart for logged-in users
        $cart = Cart::with('items.variant.product')
            ->where('user_id', $user->id)
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        try {
            $order = DB::transaction(function () use ($user, $cart, $request) {

                $address = Address::where('id', $request->address_id)
                    ->where('user_id', $user->id)
                    ->firstOrFail();

                $order = Order::create([
                    'user_id' => $user->id,
                    'address_id' => $address->id,
                    'total_price' => 0,
                    'status' => 'pending',
                    'order_number' => 'ODR-' . strtoupper(Str::random(10)),
                ]);

                $total = 0;

                foreach ($cart->items as $item) {

                    $variant = ProductVariant::where('id', $item->product_variant_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($variant->stock < $item->quantity) {
                        throw new Exception("Stock not enough for {$variant->product->productName}");
                    }

                    $price = $this->discountService->discountedPrice($variant->product);
                    $subtotal = $price * $item->quantity;
                    $total += $subtotal;

                    // Reduce stock
                    $variant->decrement('stock', $item->quantity);

                    $order->items()->create([
                        'product_variant_id' => $variant->id,
                        'product_name' => $variant->product->productName,
                        'price' => $price,
                        'quantity' => $item->quantity,
                        'size' => $variant->size,
                        'color' => $variant->color,
                    ]);
                }

                // Update total price
                $order->update(['total_price' => $total]);

                // Clear cart and cart items
                $cart->items()->delete();
                $cart->delete();

                return $order;
            });

            return response()->json([
                'message' => 'Order placed successfully',
                'order' => $order
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Checkout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Get current user's orders
     */
    public function myOrders()
    {
        $user = Auth::user();
        $orders = $user->orders()->with('items.variant.product')->latest()->get();

        return response()->json([
            'message' => $orders->isEmpty() ? 'No orders found' : 'Orders retrieved successfully',
            'orders' => $orders->map(fn($order) => $this->formatOrder($order))
        ]);
    }

    /**
     * ✅ Cancel order
     */
    public function cancel($orderId)
    {
        $user = Auth::user();
        $order = $user->orders()->with('items.variant')->find($orderId);

        if (!$order) return response()->json(['message' => 'Order not found'], 404);
        if ($user->role === 'admin') return response()->json(['message' => 'Admin cannot cancel orders'], 403);
        if ($order->status !== 'pending') return response()->json(['message' => 'Only pending orders can be canceled'], 400);

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $item->variant?->increment('stock', $item->quantity);
            }
            $order->update(['status' => 'cancelled']);
        });

        return response()->json(['message' => 'Order cancelled successfully', 'order' => $this->formatOrder($order->fresh())]);
    }

    /**
     * ✅ Delete order
     */
    public function destroy($orderId)
    {
        $user = Auth::user();
        $order = $user->orders()->with('items.variant')->find($orderId);

        if (!$order) return response()->json(['message' => 'Order not found'], 404);
        if ($user->role === 'admin') return response()->json(['message' => 'Admin cannot delete orders'], 403);
        if ($order->status !== 'pending') return response()->json(['message' => 'Only pending orders can be deleted'], 400);

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $item->variant?->increment('stock', $item->quantity);
            }
            $order->items()->delete();
            $order->delete();
        });

        return response()->json(['message' => 'Order deleted successfully', 'order_id' => $orderId]);
    }

    /**
     * ✅ Admin: view specific user's orders
     */
    public function userOrderHistory(User $user)
    {
        if ($user->role === 'admin') return response()->json(['message' => 'Admin cannot view order history', 'orders' => []]);

        $orders = $user->orders()->with('items.variant.product')->latest()->get();

        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email
            ],
            'orders' => $orders->map(fn($order) => $this->formatOrder($order))
        ]);
    }

    /**
     * ✅ Helper: Format order response
     */
    private function formatOrder($order)
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total_price' => (float) $order->total_price,
            'items' => $order->items->map(fn($item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
                'subtotal' => (float) ($item->price * $item->quantity),
                'variant' => [
                    'id' => $item->variant->id,
                    'size' => $item->variant->size,
                    'color' => $item->variant->color,
                    'product' => [
                        'id' => $item->variant->product->id,
                        'name' => $item->variant->product->productName,
                        'slug' => $item->variant->product->slug,
                        'price' => (float) $item->variant->product->price
                    ]
                ]
            ])
        ];
    }
}