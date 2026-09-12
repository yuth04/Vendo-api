<?php

namespace App\Http\Controllers\Api\v1\Orders;
use App\Http\Controllers\Controller;
use App\Mail\AdminOrderPlaced;
use App\Mail\OrderPlaced;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use App\Models\User;
use App\Services\BakongService;
use App\Services\InventoryMovementService;
use App\Services\InventoryService;
use App\Services\SystemLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(protected SystemLogService $systemLogService, private InventoryService $inventoryService)
    {

    }
    private function calculateShipping(?string $province): float
    {
        if (!$province) {
            return 1.5;
        }

        $province = strtolower(trim($province));

        if (in_array($province, ['phnom penh', 'ភ្នំពេញ', 'pp'])) {
            return 1.5;
        }

        return 2;
    }

    private function resolveOrderAddress($user, Request $request)
    {
        // if ($request->filled('address_id')) {
        //     return Address::where('id', $request->address_id)
        //         ->where('user_id', $user->id)
        //         ->first();
        // }
        if ($request->filled('address_id')) {
            $address = Address::where('id', $request->address_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$address) {
                throw new \InvalidArgumentException("Selected address was not found or does not belong to your account.");
            }

            return $address;
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
    private function prepareCheckoutResponse(Order $order, Payment $payment, ?array $khqrData = null, User $user)
    {
        // Base response structure for all payment types
        $response = [
            'message' => 'Order placed successfully',
            'order' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_price' => $order->total_price,
                'payment_method' => $payment->payment_method,
            ]
        ];
        if ($payment->payment_method === "Cash on Delivery") {
            $order->load(['items.variant.product', 'user', 'address', 'payment']);
            try {
                Mail::to($user->email)->send(new OrderPlaced($order));
                Mail::to(config('mail.admin_email'))->send(new AdminOrderPlaced($order));
            } catch (\Throwable $e) {
                Log::error('Order confirmation email failed to queue', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
            return $response;
        }

        if ($khqrData) {
            $response['khqr'] = $khqrData;
        }

        return $response;
    }
    public function checkout(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'order_id' => 'nullable|exists:orders,id',
            'address_id' => 'nullable|exists:addresses,id',
            'first_name' => 'required_without:address_id|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'address_line' => 'required_without:address_id|string|max:500',
            'city' => 'required_without:address_id|string|max:255',
            'province' => 'required_without:address_id|string|max:255',
            'phone' => 'required_without:address_id|string|max:20',
            'coupon_code' => 'nullable|string',
            'payment_method' => 'required|in:Cash on Delivery,KHQR,ABA,Bakong,Credit Card',
            'note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();

        try {
            // start
            if ($request->filled('order_id')) {
                $order = Order::where('id', $request->order_id)
                    ->where('user_id', $user->id)
                    ->first();

                if (!$order) {
                    return response()->json(['message' => 'Order not found.'], 404);
                }

                if (in_array($order->status, ['confirmed', 'paid', 'completed'])) {
                    return response()->json(['message' => 'This order has already been paid.'], 400);
                }

                if (in_array($order->status, ['cancelled', 'expired'])) {
                    $order->update(['status' => 'pending']);
                }

                $payment = Payment::where('order_id', $order->id)
                    ->where('payment_method', $request->payment_method)
                    ->latest()
                    ->first();

                $khqrData = null;
                if ($request->payment_method === 'KHQR') {
                    $bakongService = new BakongService();
                    $khqrData = $bakongService->generateKhqrPayment($order);
                }

                return response()->json(
                    $this->prepareCheckoutResponse($order, $payment, $khqrData, $user),
                    200
                );
            }
            // end
            // Create new order
            $orderModel = null;

            $result = DB::transaction(function () use ($user, $request, &$orderModel) {

                // address
                $address = $this->resolveOrderAddress($user, $request);
                // create order
                $order = $this->initializeOrder($user, $address);

                if ($request->has('note')) {
                    $order->update(['note' => $request->note]);
                }
                // items
                $totals = $this->processOrderItems($order, $request->items);

                $originalTotal = $totals['original_total'];
                $subtotal = $totals['final_total'];


                // SHIPPING
                $shipping = (float) $this->calculateShipping($address->province);


                // ── COUPON ─────────────────────────────────────────
                $couponDiscount = 0;
                $couponCode = null;

                // auto-read from cache if not passed in request
                $couponInput = $request->filled('coupon_code')
                    ? $request->coupon_code
                    : cache()->get('coupon_' . $user->id);

                if ($couponInput) {

                    $coupon = Coupon::where('code', $couponInput)->first();

                    if (!$coupon) {
                        throw new \Exception("Invalid coupon code");
                    }

                    if ($coupon->start_date && now()->lt($coupon->start_date)) {
                        throw new \Exception("Coupon not active yet");
                    }

                    if ($coupon->end_date && now()->gt($coupon->end_date)) {
                        throw new \Exception("Coupon expired");
                    }

                    if ($coupon->usage_limit && $coupon->used >= $coupon->usage_limit) {
                        throw new \Exception("Coupon usage limit reached");
                    }

                    if ($coupon->min_amount && $subtotal < $coupon->min_amount) {
                        throw new \Exception("Minimum order not reached");
                    }

                    $couponDiscount = min($coupon->calculateDiscount($subtotal), $subtotal);
                    $coupon->increment('used');
                    $couponCode = $coupon->code;

                    // ── Clear cache after successful use ───────────
                    cache()->forget('coupon_' . $user->id);
                }


                // TOTAL CALCULATION
                $totalAmount = max(0, round(($subtotal - $couponDiscount) + $shipping, 2));

                // UPDATE ORDER
                $order->update([
                    'original_price' => round($originalTotal, 2),
                    'sub_total_price' => round($subtotal, 2),

                    'total_price' => $totalAmount,
                    'shipping_fee' => $shipping,

                    'coupon_code' => $couponCode,
                    'coupon_discount' => $couponDiscount,
                ]);

                // PAYMENT
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'payment_method' => $request->payment_method,
                    'amount' => $totalAmount,
                    'status' => 'unpaid',
                    'payment_reference' => 'REF-' . time() . strtoupper(Str::random(4)),
                    'paid_at' => null,
                ]);
                // KHQR
                $khqrData = null;
                if ($request->payment_method === 'KHQR') {
                    $bakongService = new BakongService();
                    $khqrData = $bakongService->generateKhqrPayment($order);
                }
                $orderModel = $order;

                return $this->prepareCheckoutResponse($order, $payment, $khqrData, $user);
            });
            // system log
            $this->systemLogService->log(
                action: 'created',
                module: 'Order',
                description: "Order {$orderModel->order_number} was created",
                entity: $orderModel,
                oldValues: null
            );
            return response()->json($result, 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'checkout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function initializeOrder($user, $address)
    {
        return Order::create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'order_number' => 'ODR-' . strtoupper(Str::random(10)),
            'status' => 'pending',
            'total_price' => 0,
            'discount_amount' => 0,
            'coupon_code' => null,
            'coupon_discount' => 0
        ]);
    }
    private function processOrderItems(Order $order, array $frontendItems)
    {
        $finalTotal = 0;
        $originalTotal = 0;
        $totalDiscount = 0;

        foreach ($frontendItems as $item) {

            $variant = ProductVariant::with('product')
                ->where('id', $item['product_variant_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($variant->stock < $item['quantity']) {
                throw new \Exception("Insufficient stock for {$variant->product->name}");
            }

            $product = $variant->product;

            $originalPrice = (float) $product->price;
            $finalPrice = (float) $product->getFinalPrice();

            $qty = $item['quantity'];

            $originalLine = $originalPrice * $qty;
            $finalLine = $finalPrice * $qty;

            $originalTotal += $originalLine;
            $finalTotal += $finalLine;

            $totalDiscount += max(0, $originalLine - $finalLine);

            $variant->decrement('stock', $qty);

            $order->items()->create([
                'product_variant_id' => $variant->id,
                'product_name' => $product->productName,
                'product_image' => $product->image,
                'price' => $finalPrice,
                'quantity' => $qty,
                'size' => $variant->size,
                'color' => $variant->color,
            ]);
        }
 
        $order->update([
            'discount_amount' => round($totalDiscount, 2)
        ]);

        return [
            'original_total' => $originalTotal,
            'final_total' => $finalTotal
        ];
    }
    //Apply Coupon 
    public function apply(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Login required'], 401);
        }
        $request->validate([
            'code' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // ── 1. Calculate subtotal ──────────────────────────────────
        $subtotal = 0;

        foreach ($request->items as $item) {
            $variant = ProductVariant::with('product')->findOrFail($item['product_variant_id']);
            $price = $variant->product->getFinalPrice();
            $subtotal += $price * $item['quantity'];
        }

        // ── 2. Find coupon ─────────────────────────────────────────
        $coupon = Coupon::where('code', $request->code)->first();

        if (!$coupon) {
            return response()->json(['message' => 'Invalid coupon code'], 404);
        }

        // ── 3. Validate coupon ─────────────────────────────────────
        if ($coupon->start_date && now()->lt($coupon->start_date)) {
            return response()->json(['message' => 'Coupon not active yet'], 400);
        }

        if ($coupon->end_date && now()->gt($coupon->end_date)) {
            return response()->json(['message' => 'Coupon expired'], 400);
        }

        if ($coupon->usage_limit && $coupon->used >= $coupon->usage_limit) {
            return response()->json(['message' => 'Coupon usage limit reached'], 400);
        }

        if ($coupon->min_amount && $subtotal < $coupon->min_amount) {
            return response()->json([
                'message' => 'Minimum order amount is ' . $coupon->min_amount,
                'min_amount' => $coupon->min_amount,
            ], 400);
        }

        // ── 4. Calculate discount ──────────────────────────────────
        $discount = min($coupon->calculateDiscount($subtotal), $subtotal);
        //   Get province from user
        $address = $user->addresses()->where('is_default', true)->first()
            ?? $user->addresses()->latest()->first();
        $province = $address->province ?? null;
        // ── 5. Shipping preview (no address yet) ───────────────────
        $shipping = (float) $this->calculateShipping($province);

        // ── 6. Final total ─────────────────────────────────────────
        $total = max(0, round(($subtotal - $discount) + $shipping, 2));

        // ── 7. Store coupon in cache (1 hour) ──────────────────────
        cache()->put('coupon_' . $user->id, $coupon->code, now()->addHour());

        return response()->json([
            'message' => 'Coupon applied successfully',
            'coupon' => $coupon->code,
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'shipping' => round($shipping, 2),
            'total_price' => $total,
        ]);
    }
    // remove coupon
    public function clear()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Login required'], 401);
        }

        cache()->forget('coupon_' . $user->id);

        return response()->json(['message' => 'Coupon removed successfully']);
    }
    public function myOrders()
    {
        $user = Auth::user();
        $orders = $user->orders()->with(['items.variant.product', 'payment', 'address'])->where('created_at', '>=', now()->subMonths(3))->latest()->get();

        return response()->json([
            'message' => $orders->isEmpty() ? 'No orders found' : 'Orders retrieved successfully',
            'orders' => $orders->map(fn($order) => $this->formatOrder($order))
        ]);
    }
    // show order details
    public function show($orderId)
    {
        $user = Auth::user();
        $order = $user->orders()->with(['items.variant.product', 'payment', 'address'])->find($orderId);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }
        return response()->json([
            'message' => 'Order details retrieved successfully',
            'order' => $this->formatOrder($order)
        ]);
    }
    private function formatOrder($order)
    {
        $shipping = (float) ($order->shipping_fee ?? $this->calculateShipping($order->address->province ?? ''));

        // -------------------------
        // ORIGINAL SUBTOTAL (before discount)
        // -------------------------
        $originalSubtotal = $order->items->sum(function ($item) {
            return (float) ($item->variant->product->price ?? 0) * $item->quantity;
        });

        // -------------------------
        // FINAL SUBTOTAL (after product discount)
        // -------------------------
        $finalSubtotal = $order->items->sum(function ($item) {
            return (float) $item->price * $item->quantity;
        });

        // -------------------------
        // DISCOUNTS
        // -------------------------
        $productDiscount = max(0, $originalSubtotal - $finalSubtotal);
        $couponDiscount = (float) ($order->coupon_discount ?? 0);

        // -------------------------
        // TOTAL
        // -------------------------
        $totalPayment = max(0, ($finalSubtotal - $couponDiscount) + $shipping);

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'original_price' => number_format((float) $originalSubtotal, 2, '.', ''),
            'product_discount' => number_format((float) $productDiscount, 2, '.', ''),
            'sub_total_price' => number_format((float) $finalSubtotal, 2, '.', ''),
            'coupon_discount' => number_format((float) $couponDiscount, 2, '.', ''),
            'shipping_fee' => number_format((float) $shipping, 2, '.', ''),
            'total_price' => number_format((float) $totalPayment, 2, '.', ''),
            'save' => number_format((float) $productDiscount + $couponDiscount, 2, '.', ''),
            'coupon' => [
                'code' => $order->coupon_code,
                'discount' => $couponDiscount,
            ],
            'created_at' => $order->created_at->format('d-m-Y'),
            'time' => $order->created_at->format('h:i:s A'),
            'payment' => [
                'method' => $order->payment->payment_method ?? null,
                'status' => $order->payment->status ?? null,
                'reference' => $order->payment->payment_reference ?? null,
                'paid_at' => $order->payment?->paid_at?->format('d-m-Y h:i:s A'),
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

            'items' => $order->items->map(function ($item) {
                $originalPrice = (float) ($item->variant->product->price ?? 0);
                $finalPrice = (float) $item->price;
                $returnedQuantity = $item->returnItems()->sum('quantity');

                return [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'returned_quantity' => $returnedQuantity,
                    'remaining_quantity' => max(
                        0,
                        $item->quantity - $returnedQuantity
                    ),
                    'save' => number_format((float) max(0, $originalPrice - $finalPrice), 2, '.', ''),
                    'subtotal' => number_format((float) ($finalPrice * $item->quantity), 2, '.', ''),

                    'variant' => [
                        'id' => $item->product_variant_id,
                        'size' => $item->size,
                        'color' => $item->color,
                        'price' => $originalPrice,
                        'discount_price' => number_format((float) $item->price, 2, '.', '') ?? 0,
                        'product' => [
                            'id' => $item->variant->product->id ?? null,
                            'name' => $item->product_name,
                            'slug' => $item->variant->product->slug ?? null,
                            'image' => $item->variant->product->image ?? null,
                        ]
                    ]
                ];
            })
        ];
    }
    public function cancel($orderId)
    {
        $user = Auth::user();

        $order = $user->orders()
            ->with(['items.variant', 'payment'])
            ->find($orderId);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Only pending orders can be canceled'], 400);
        }

        //  Prevent cancel after payment
        if ($order->payment && $order->payment->status === 'paid') {
            return response()->json([
                'message' => 'Paid orders cannot be canceled'
            ], 400);
        }
        $oldValues = [
            'order_status' => $order->status,
            'payment_status' => $order->payment?->status,
        ];
        DB::transaction(function () use ($order) {

            foreach ($order->items as $item) {
                $variant = $item->variant()->lockForUpdate()->first();
                if ($variant) {
                    $variant->increment('stock', $item->quantity);
                }
            }

            // update order
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // update payment
            $order->payment?->update([
                'status' => 'cancelled'
            ]);
        });
        $newValues = [
            'status' => $order->status,
            'payment_status' => $order->payment?->status,
        ];
        // system log
        $this->systemLogService->log(
            action: 'cancelled',
            module: 'Order',
            description: "Order {$order->order_number} was cancelled",
            entity: $order,
            oldValues: $oldValues,
            newValues: $newValues
        );
        return response()->json([
            'message' => 'Order cancelled successfully',
            'order' => $this->formatOrder($order->fresh())
        ]);
    }
    public function destroy($orderId)
    {
        if (auth()->user()->role !== 'super-admin') {
            return response()->json(['message' => 'Only super admin can action this.'], 403);
        }

        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }
        // Save information before deletion
        $oldValues = [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total_price' => $order->total_price,
            'payment_method' => $order->payment?->payment_method,
            'payment_status' => $order->payment?->status,
        ];
        DB::transaction(function () use ($order) {
            $order->items()->delete();
            $order->payment()?->delete();
            $order->delete();
        });
        // Log AFTER successful deletion
        $this->systemLogService->log(
            action: 'deleted',
            module: 'Order',
            description: "Order {$oldValues['order_number']} was deleted",
            entity: $order,
            oldValues: $oldValues
        );
        return response()->json(['message' => 'Order deleted successfully', 'order_id' => $orderId]);
    }
    public function regenerateKhqr(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        $user = Auth::user();

        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if (in_array($order->status, ['confirmed', 'paid', 'completed'])) {
            return response()->json(['message' => 'This order has already been paid.'], 400);
        }

        try {
            $bakongService = new BakongService();
            $khqrData = $bakongService->generateKhqrPayment($order);

            return response()->json([
                'status' => 'success',
                'message' => 'KHQR Code regenerated successfully',
                'order_id' => $order->id,
                'khqr' => $khqrData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate KHQR code.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function downloadReceipt(Request $request, Order $order)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'The download link has expired.');
        }
        if (auth()->id() !== $order->user_id) {
            abort(403, 'Unauthorized access to this invoice.');
        }
        $order->load(['items.variant.product', 'user', 'address', 'payment']);

        $pdf = Pdf::loadView('invoices.receipt', [
            'order' => $order,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Vendo-Receipt-' . $order->order_number . '.pdf');
    }


}