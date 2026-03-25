<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\BakongService;
use App\Services\DiscountService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class OrderController extends Controller
{
    protected $discountService;

    public function __construct(DiscountService $discountService)
    {
        $this->discountService = $discountService;
    }

    // Checkout
    // public function checkout(Request $request)
    // {
    //     if (!Auth::check()) {
    //         return response()->json(['message' => 'Login required'], 401);
    //     }

    //     $request->validate([
    //         'address_id' => 'nullable|exists:addresses,id',
    //         'first_name' => 'required_without:address_id|string|max:255',
    //         'last_name' => 'nullable|string|max:255',
    //         'address_line' => 'required_without:address_id|string|max:500',
    //         'city' => 'required_without:address_id|string|max:255',
    //         'province' => 'required_without:address_id|string|max:255',
    //         'phone' => 'required_without:address_id|string|max:20',
    //         'payment_method' => 'required|string',
    //     ]);

    //     $user = Auth::user();
    //     $cart = Cart::with('items.variant.product')->where('user_id', $user->id)->first();

    //     if (!$cart || $cart->items->isEmpty()) {
    //         return response()->json(['message' => 'Cart is empty'], 400);
    //     }

    //     try {
    //         $result = DB::transaction(function () use ($user, $cart, $request) {
    //             // 1. Address Logic
    //             if ($request->filled('address_id')) {
    //                 $address = Address::where('id', $request->address_id)
    //                     ->where('user_id', $user->id)
    //                     ->firstOrFail();
    //             } else {
    //                 $address = Address::create(array_merge(
    //                     $request->only(['first_name', 'last_name', 'address_line', 'city', 'province', 'postal_code', 'phone']),
    //                     ['user_id' => $user->id, 'is_default' => false]
    //                 ));
    //             }

    //             // 2. Create Order
    //             $order = Order::create([
    //                 'user_id' => $user->id,
    //                 'address_id' => $address->id,
    //                 'order_number' => 'ODR-' . strtoupper(Str::random(10)),
    //                 'payment_reference' => 'REF-' . time() . strtoupper(Str::random(4)),
    //                 'payment_method' => $request->payment_method,
    //                 'status' => 'pending',
    //                 'total_price' => 0,
    //             ]);

    //             $total = 0;

    //             foreach ($cart->items as $item) {
    //                 $variant = ProductVariant::where('id', $item->product_variant_id)
    //                     ->lockForUpdate()
    //                     ->firstOrFail();

    //                 if ($variant->stock < $item->quantity) {
    //                     throw new Exception("Stock not enough for {$variant->product->productName}");
    //                 }

    //                 $price = $this->discountService->discountedPrice($variant->product);
    //                 $subtotal = $price * $item->quantity;
    //                 $total += $subtotal;

    //                 $variant->decrement('stock', $item->quantity);

    //                 $order->items()->create([
    //                     'product_variant_id' => $variant->id,
    //                     'product_name' => $variant->product->productName,
    //                     'price' => round($price, 2),
    //                     'quantity' => $item->quantity,
    //                     'size' => $variant->size,
    //                     'color' => $variant->color,
    //                 ]);
    //             }

    //             // Add $1.00 fee and update total
    //             $order->update(['total_price' => round($total + 1.00, 2)]);

    //             // 3. Clear Cart (Important: Clear for both methods to prevent double ordering)
    //             $cart->items()->delete();

    //             if ($request->payment_method === "Cash on Delivery") {
    //                 return ['type' => 'cod', 'order' => $order];
    //             }

    //             // For Online: Hand off to the QR Generator
    //             $bakongService = new BakongService();
    //             $khqrString = $bakongService->generateKHQR($order);

    //             // Using External API to avoid local Imagick/GD issues
    //             $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($khqrString);

    //             return [
    //                 'type' => 'online',
    //                 'data' => [
    //                     'message' => "KHQR generated",
    //                     'qr_code_url' => $qrCodeUrl,
    //                     'khqr_string' => $khqrString,
    //                     'order_number' => $order->order_number,
    //                     'order_id' => $order->id
    //                 ]
    //             ];
    //         });

    //         return response()->json([
    //             'message' => $result['type'] === 'cod' ? 'Order placed successfully' : 'QR Generated',
    //             'data' => $result['type'] === 'cod' ? $result['order'] : $result['data']
    //         ], 201);

    //     } catch (Exception $e) {
    //         return response()->json(['message' => 'Checkout failed', 'error' => $e->getMessage()], 500);
    //     }
    // }

    public function checkout(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'login required'], 400);
        }
        $request->validate([
            'address_id' => 'nullable|exists:addresses,id',
            'first_name' => 'required_without:address_id|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'address_line' => 'required_without:address_id|string|max:500',
            'city' => 'required_without:address_id|string|max:255',
            'province' => 'required_without:address_id|string|max:255',
            'phone' => 'required_without:address_id|string|max:20',
            'payment_method' => 'required|in:Cash on Delivery,ABA,Credit Card',
        ]);
        $user = Auth::user();
        $cart = Cart::with('items.variant.product.discount')->where('user_id', $user->id)->first();
        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }
        try {
            $result = DB::transaction(function () use ($user, $cart, $request) {
                $address = $this->resolveOrderAddress($user, $request);
                $order = $this->initializeOrder($user, $address, $request);
                $total = $this->processOrderItems($order, $cart);
                $order->update(['total_price' => round($total + 1.00, 2)]);
                $cart->items()->delete();
                return $this->prepareCheckoutResponse($order, $request->payment_method);

            });
            return response()->json([
                'message' => $result['message'],
                'data' => $result['data']
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'checkout failed',
                'error' => $e->getMessage()
            ], 500);
        }




    }
    // Handle address logic
    private function resolveOrderAddress($user, Request $request)
    {
        if ($request->filled('address_id')) {
            return Address::where('id', $request->address_id)
                ->where('user_id', $user->id)
                ->firstOrFail();
        }

        return Address::create(array_merge(
            $request->only(['first_name', 'last_name', 'address_line', 'city', 'province', 'postal_code', 'phone']),
            ['user_id' => $user->id, 'is_default' => false]
        ));
    }
    // Initialize new Order record
    private function initializeOrder($user, $address, Request $request)
    {
        return Order::create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'order_number' => 'ODR-' . strtoupper(Str::random(10)),
            'payment_reference' => 'REF-' . time() . strtoupper(Str::random(4)),
            'payment_method' => $request->payment_method,
            'status' => 'pending',
            'total_price' => 0,
        ]);
    }
    // Process stock, calculation, order item create
    private function processOrderItems(Order $order, Cart $cart)
    {
        $total = 0;

        foreach ($cart->items as $item) {
            $variant = ProductVariant::where('id', $item->product_variant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($variant->stock < $item->quantity) {
                throw new \Exception("Stock not enough for {$variant->product->productName}");
            }

            // Logic fix: Ensure we use the base price if discount is null
            $price = $this->discountService->discountedPrice($variant->product) ?? $variant->product->price;
            $total += $price * $item->quantity;

            $variant->decrement('stock', $item->quantity);

            $order->items()->create([
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->productName,
                'price' => round($price, 2),
                'quantity' => $item->quantity,
                'size' => $variant->size,
                'color' => $variant->color,
            ]);
        }

        return $total;
    }
    // Return data based on payment method
    private function prepareCheckoutResponse(Order $order, $paymentMethod)
    {
        if ($paymentMethod === "Cash on Delivery") {
            $order->update([
                'status' => 'delivered',
                'payment_status' => 'Unpaid'
            ]);
            return [
                'message' => 'Order placed successfully',
                'data' => $order
            ];
        }

        $bakongService = new BakongService();
        $khqrString = $bakongService->generateKHQR($order);
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($khqrString);

        return [
            'message' => 'QR Generated',
            'data' => [
                'qr_code_url' => $qrCodeUrl,
                'khqr_string' => $khqrString,
                'order_number' => $order->order_number,
                'order_id' => $order->id
            ]
        ];
    }
    //   Get current user's orders
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

        if (!$order)
            return response()->json(['message' => 'Order not found'], 404);
        if ($user->role === 'admin')
            return response()->json(['message' => 'Admin cannot cancel orders'], 403);
        if ($order->status !== 'pending')
            return response()->json(['message' => 'Only pending orders can be canceled'], 400);

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

        if (!$order)
            return response()->json(['message' => 'Order not found'], 404);
        if ($user->role === 'admin')
            return response()->json(['message' => 'Admin cannot delete orders'], 403);
        if ($order->status !== 'pending')
            return response()->json(['message' => 'Only pending orders can be deleted'], 400);

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
        if ($user->role === 'admin')
            return response()->json(['message' => 'Admin cannot view order history', 'orders' => []]);

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
                    'price' => (float) $item->variant->product->price,
                    'product' => [
                        'id' => $item->variant->product->id,
                        'name' => $item->variant->product->productName,
                        'slug' => $item->variant->product->slug,
                        
                    ]
                ]
            ])
        ];
    }
}