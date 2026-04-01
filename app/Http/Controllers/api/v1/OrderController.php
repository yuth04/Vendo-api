<?php

namespace App\Http\Controllers\api\v1;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use App\Mail\OrderPlaced;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'payment_method' => 'required|in:Cash on Delivery,ABA,Bakong,Credit Card',
            // 'payment_method' => 'required|string',
            'note' => 'nullable|string',
            // get cart from frontend
            'items' => 'required|array|min:1',
            'items.*.product_variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        $user = Auth::user();
        try {
            $orderModel = null;
            $result = DB::transaction(function () use ($user, $request, &$orderModel) {
                $address = $this->resolveOrderAddress($user, $request);
                $order = $this->initializeOrder($user, $address);
                $total = $this->processOrderItems($order, $request->items);
                $shipping = $this->calculateShipping($order->address->province);
                $subtotal = round($total, 2);
                $order->update([
                    // 'total_price' => round($total + $shipping, 2)
                    'total_price' => round($subtotal + $shipping, 2),
                ]);
                $amount = $subtotal + $shipping;
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'payment_method' => $request->payment_method,
                    'amount' => round($amount, 2),
                    'status' => 'unpaid',
                    'payment_reference' => 'REF-' . time() . strtoupper(Str::random(4)),
                    // 'paid_at' => now()->setTimezone('Asia/Phnom_Penh')->format('Y-m-d h:i A'),
                    'paid_at' => null,
                ]);

                $orderModel = $order;
                return $this->prepareCheckoutResponse($order, $payment);

            });
            if ($orderModel) {
                $orderModel->load(['items', 'user', 'address', 'payment']);
                Mail::to($user->email)->send(new OrderPlaced($orderModel));
            }
            return response()->json([
                'message' => $result['message'],
                'order' => $result['order']
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
    private function processOrderItems(Order $order, array $frontendItems)
    {
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
    // Shipping calculate
    private function calculateShipping($province)
    {
        $province = strtolower($province);
        if ($province === 'phnom penh' || $province === 'ភ្នំពេញ' || $province === 'pp') {
            return 1.5;
        }
        return 2.0;
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
                'order' => [
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
            'order' => [
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
    // Filter Orders by status
    public function filterByStatus(Request $request)
    {
        $status = $request->query('status');

        $allowedStatuses = [
            'pending',
            'confirmed',
            'shipping',
            'delivered',
            'cancelled',
            'returned',
            'return_requested'
        ];

        // ✅ validate status
        if ($status && !in_array($status, $allowedStatuses)) {
            return response()->json([
                'message' => 'Invalid order status'
            ], 422);
        }

        // ✅ authorization
        if (Auth::user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized, only admins can filter orders'
            ], 403);
        }

        // ✅ query
        $orders = Order::when($status, function ($q) use ($status) {
            $q->where('status', $status);
        })
            ->with('items.variant.product')
            ->latest()
            ->get();

        return response()->json([
            'message' => $orders->isEmpty()
                ? 'No orders found'
                : 'Orders retrieved successfully',

            'orders' => $orders->map(fn($order) => $this->formatOrder($order))
        ]);
    }
    // Cancel order
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
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now()->setTimezone('Asia/Phnom_Penh')->format('Y-m-d h:i A'),
            ]);
        });

        return response()->json(['message' => 'Order cancelled successfully', 'order' => $this->formatOrder($order->fresh())]);
    }

    // Delete order
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

    // User order history (Admin)
    public function userOrderHistory(User $user)
    {
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
    // Format order response
    private function formatOrder($order)
    {
        $shipping = $this->calculateShipping($order->address->province ?? '');

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'sub_total_price' => (float) $order->total_price - $shipping,
            'shipping_fee' => (float) $shipping,
            'total_price' => (float) $order->total_price,
            'payment' => [
                'method' => $order->payment->payment_method ?? null,
                'status' => $order->payment->status ?? null,
                'reference' => $order->payment->payment_reference ?? null,
                'paid_at' => $order->payment->paid_at

            ],
            'address' => [
                'first_name' => $order->address->first_name ?? null,
                'last_name' => $order->address->last_name ?? null,
                'address_line' => $order->address->address_line ?? null,
                'city' => $order->address->city ?? null,
                'province' => $order->address->province ?? null,
                'phone' => $order->address->phone ?? $order->user->contact ?? null,
                'note' => $order->note,
            ],
            'items' => $order->items->map(fn($item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'subtotal' => (float) ($item->price * $item->quantity),
                'variant' => [
                    'id' => $item->variant->id,
                    'size' => $item->variant->size,
                    'color' => $item->variant->color,
                    'price' => (float) $item->price,
                    'product' => [
                        'id' => $item->variant->product->id,
                        'name' => $item->variant->product->productName,
                        'slug' => $item->variant->product->slug,
                        'image' => $item->variant->product->image,
                    ]
                ]
            ])
        ];
    }

    // PDF download
    public function downloadReceipt(Request $request, Order $order)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'The download link has expired.');
        }
        $order->load(['items', 'user', 'address', 'payment']);


        $pdf = Pdf::loadView('invoices.receipt', [
            'order' => $order,
            // 'isPdf' => true
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Vendo-Receipt-' . $order->order_number . '.pdf');
    }
}