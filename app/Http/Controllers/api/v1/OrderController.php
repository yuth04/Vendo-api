<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    // Check out cart and create order
    public function checkout(Request $request)
    {
        // Validate the input
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'address_line' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'province' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'required|string|max:20',
            'is_default' => 'nullable|boolean',
        ]);

        $user = Auth::user();

        $cart = Cart::with('items.variant')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty!'
            ], 400);
        }

        try {
            $order = DB::transaction(function () use ($user, $cart, $data) {

                // If user wants this as default address, unset other default addresses
                if (!empty($data['is_default']) && $data['is_default']) {
                    Address::where('user_id', $user->id)->update(['is_default' => false]);
                }

                // Create the address
                $address = Address::create([
                    'user_id' => $user->id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'] ?? null,
                    'address_line' => $data['address_line'],
                    'city' => $data['city'],
                    'province' => $data['province'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'phone' => $data['phone'],
                    'is_default' => $data['is_default'] ?? false,
                ]);

                // Calculate total price
                $totalPrice = 0;
                foreach ($cart->items as $cartItem) {
                    $variant = $cartItem->variant;
                    if (!$variant)
                        throw new Exception("Variant not found for CartItem ID {$cartItem->id}");
                    if ($variant->stock < $cartItem->quantity)
                        throw new Exception("Not enough stock for {$variant->product->productName}");

                    $price = $variant->price > 0 ? $variant->price : $variant->product->price;
                    $totalPrice += $cartItem->quantity * $price;

                    // Decrement stock
                    $variant->decrement('stock', $cartItem->quantity);
                }

                // Create order linked to the address
                $order = Order::create([
                    'user_id' => $user->id,
                    'address_id' => $address->id,
                    'total_price' => $totalPrice,
                    'status' => 'pending',
                    'order_number' => 'ODR-' . strtoupper(Str::random(10)),
                ]);

                // Transfer cart items to order
                foreach ($cart->items as $cartItem) {
                    $variant = $cartItem->variant;
                    $price = $variant->price > 0 ? $variant->price : $variant->product->price;

                    $order->items()->create([
                        'product_variant_id' => $variant->id,
                        'product_name' => $variant->product->productName,
                        'price' => $price,
                        'quantity' => $cartItem->quantity,
                        'size' => $variant->size,
                        'color' => $variant->color
                    ]);
                }

                // Clear cart
                $cart->items()->delete();

                return $order;
            });

            return response()->json([
                'message' => 'Order placed successfully',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_price' => number_format($order->total_price, 2),
                'status' => $order->status
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Checkout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // view order
    public function myOrders()
    {
        $orders = Auth::user()
            ->orders()
            ->with('items.variant.product')
            ->latest()
            ->get();
        if ($orders->isEmpty()) {
            return response()->json([
                'message' => 'No orders found'
            ], 404);
        }
        // tranform order
        $orders->transform(function ($order) {
            $order->items->transform(function ($item) {
                return [
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
                            'price' => (float) $item->variant->product->price,
                        ]
                    ]
                ];
            });
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_price' => (float) $order->total_price,
                'items' => $order->items
            ];
        });

        return response()->json([
            'message' => 'Orders retrieved successfully',
            'orders' => $orders
        ]);
    }
    //  cancel order
    public function cancel($orderId)
    {
        $user = Auth::user();
        // find user order
        $order = $user->orders()->with('items.variant')->find($orderId);

        if ($user->role === "admin") {
            return response()->json([
                'message' => 'Admin cannot cancel orders'
            ], 403);
        }

        if (!$order) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        // only allow cancel if pending
        if ($order->status !== "pending") {
            return response()->json([
                'message' => 'Only pending orders can be canceled'
            ], 400);
        }
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $variant = $item->variant;

                if ($variant) {
                    // Restore stock
                    $variant->increment('stock', $item->quantity);
                }
            }

            // Update order status
            $order->update([
                'status' => 'cancelled'
            ]);
        });

        return response()->json([
            'message' => 'Order cancelled successfully',
            'order_id' => $order->id,
            'status' => 'cancelled'
        ]);

    }
    // show order history of user (for admin)
    public function userOrderHistory(User $user)
    {
        $orders = $user->orders()
            ->with(['items.variant.product'])
            ->latest()
            ->get();
        if ($user->role === "admin") {
            return response()->json([
                'message' => 'Admin cannot view order history',
                'orders' => []
            ]);
        }
        if ($orders->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'user' => [
                    'id' => $user->id,
                    'firstName' => $user->first_name,
                    'lastName' => $user->last_name,
                    'email' => $user->email
                ],
                'orders' => []
            ]);
        }

        // Transform orders for clean admin view
        $orders->transform(function ($order) {
            $order->items->transform(function ($item) {
                return [
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
                            'price' => (float) $item->variant->product->price,
                        ]
                    ]
                ];
            });

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_price' => (float) $order->total_price,
                'items' => $order->items
            ];
        });

        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'email' => $user->email
            ],
            'orders' => $orders
        ]);
    }
}
