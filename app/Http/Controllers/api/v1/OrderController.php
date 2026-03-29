<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\BakongService;
use App\Services\DiscountService;
use Carbon\Carbon;
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
    //Checkout
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
            // 'payment_method' => 'required|in:Cash on Delivery,ABA,Credit Card',
            'payment_method' => 'required|string',
            'note' => 'nullable|string',
            // get cart from frontend
            'items' => 'required|array|min:1',
            'items.*.product_variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        $user = Auth::user();
        // $cart = Cart::with('items.variant.product.discount')->where('user_id', $user->id)->first();
        // if (!$cart || $cart->items->isEmpty()) {
        //     return response()->json(['message' => 'Cart is empty'], 400);
        // }
        try {
            $result = DB::transaction(function () use ($user, $request) {
                $address = $this->resolveOrderAddress($user, $request);
                $order = $this->initializeOrder($user, $address);
                $total = $this->processOrderItems($order, $request->items);
                $order->update(['total_price' => round($total + 1.00, 2)]);
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'payment_method' => $request->payment_method,
                    'amount' => $order->total_price,
                    'status' => 'pending',
                    'payment_reference' => 'REF-' . time() . strtoupper(Str::random(4)),
                    'paid_at' => now()->setTimezone('Asia/Phnom_Penh')->format('Y-m-d h:i A'),
                ]);
                // $cart->items()->delete();
                return $this->prepareCheckoutResponse($order, $payment);

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
        } else {
            return Address::create([
                'user_id' => $user->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'address_line' => $request->address_line,
                'city' => $request->city,
                'province' => $request->province,
                'phone' => $request->phone,
            ]);
        }
    }
    // Initialize new Order record
    private function initializeOrder($user, $address)
    {
        return Order::create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'order_number' => 'ODR-' . strtoupper(Str::random(10)),
            'status' => 'pending',
            'total_price' => 0,
        ]);
    }
    // Process stock, calculation, order item create
    private function processOrderItems(Order $order, array $frontendItems){
        $total = 0;

        foreach ($frontendItems as $item) {
            $variant = ProductVariant::with('product.discount')
                ->where('id', $item['product_variant_id'])
                ->lockForUpdate() // Protects against 2 people buying the last item
                ->firstOrFail();

            // Check if stock is actually available in the DB
            if ($variant->stock < $item['quantity']) {
                throw new \Exception("Insufficient stock for {$variant->product->productName}");
            }

            // Calculate price based on DB records
            $price = $this->discountService->discountedPrice($variant->product) ?? $variant->product->price;
            $total += $price * $item['quantity'];

            // Deduct stock
            $variant->decrement('stock', $item['quantity']);

            // Save the item to the order
            $order->items()->create([
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->productName,
                'price' => round($price, 2),
                'quantity' => $item['quantity'],
                'size' => $variant->size,
                'color' => $variant->color,
            ]);
        }

        return $total;
    }
    // Return data based on payment method
    private function prepareCheckoutResponse(Order $order, Payment $payment)
    {
        if ($payment->payment_method === "Cash on Delivery") {
            $order->update([
                'status' => 'pending',
            ]);
            return [
                'message' => 'Order placed successfully',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number
                ]
            ];
        }

        // ✅ BAKONG / ONLINE PAYMENT
        $bakongService = new BakongService();
        $qrData = $bakongService->generateKhqr($order, $payment);
        $khqrString = $qrData['khqr_string'];

        return [
            'message' => 'QR Generated',
            'data' => [
                // 'qr_code_url' => $qrCodeUrl,
                'khqr_string' => $khqrString,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_reference' => $payment->payment_reference
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
            'payment' => [
                'method' => $order->payment->payment_method ?? null,
                'status' => $order->payment->status ?? null,
                'reference' => $order->payment->payment_reference ?? null,
                'paid_at' => $order->payment->paid_at

            ],
            'items' => $order->items->map(fn($item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                // 'price' => (float) $item->price,
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