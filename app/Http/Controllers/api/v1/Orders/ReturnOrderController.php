<?php

namespace App\Http\Controllers\api\v1\Orders;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Mail\AdminReturnRequestMail;
use App\Mail\ReturnCompletedMail;
use App\Mail\ReturnOrderRequestMail;
use App\Mail\ReturnStatusUpdatedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\ReturnImage;
use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use ImageKit\ImageKit;
use App\Traits\NotifiesUsers;

class ReturnOrderController extends Controller
{
    use NotifiesUsers;


    private const VALID_REASONS = [
        'wrong_item',
        'defective_or_damaged',
        'not_as_described',
        'size_or_fit_issue',
        'no_longer_needed',
        'better_price_found',
        'other',
    ];


    private const RETURN_WINDOW_DAYS = 7;


    private function ensureAdmin(): void
    {
        $user = Auth::user();

        abort_if(
            !$user || !in_array($user->role ?? null, ['admin', 'super-admin'], true),
            403,
            'Unauthorized.'
        );
    }

    // ---------------------------------------------------------------------
    // Admin: list all returns
    // ---------------------------------------------------------------------
    public function getAll(Request $request)
    {
        $this->ensureAdmin();

        $query = ReturnOrder::with(['items.orderItem', 'user'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }


        $returns = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'message' => 'Retrieved return successfully',
            'data' => $returns
        ]);
    }

    public function index()
    {
        $returns = ReturnOrder::with([
            'items.orderItem.productVariant.product'
        ])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        $data = $returns->map(function ($return) {
            $order = $return->order;

            // ORIGINAL SUBTOTAL (before discount)
            $originalSubtotal = $order->items->sum(function ($item) {
                return (float) ($item->variant->product->price ?? 0) * $item->quantity;
            });

            // FINAL SUBTOTAL (after product discount)
            $finalSubtotal = $order->items->sum(function ($item) {
                return (float) $item->price * $item->quantity;
            });

            // DISCOUNTS
            $productDiscount = max(0, $originalSubtotal - $finalSubtotal);
            $couponDiscount = (float) ($order->coupon_discount ?? 0);
            $shipping = (float) ($order->shipping_fee ?? 0);
            $totalPayment = max(0, ($finalSubtotal - $couponDiscount) + $shipping);

            return [
                'return_id' => $return->id,
                'order_id' => $return->order_id,
                'status' => $return->status,
                'order_number' => $return->order->order_number,
                'return_type' => $return->return_type ?? 'refund',
                'reason' => $return->reason,
                'note' => $return->note,
                'original_price' => round($originalSubtotal, 2),
                'product_discount' => round($productDiscount, 2),
                'sub_total_price' => round($finalSubtotal, 2),
                'coupon_discount' => round($couponDiscount, 2),
                'shipping_fee' => $shipping,
                'total_price' => round($totalPayment, 2),
                'save' => round($productDiscount + $couponDiscount, 2),
                'coupon' => [
                    'code' => $order->coupon_code,
                    'discount' => $couponDiscount,
                ],
                'refund' => [
                    'amount' => $return->refund_amount ?? null,
                    'method' => $return->refund_method ?? null,
                    'status' => $return->refund_status ?? null,
                ],
                'created_at' => $return->created_at->format('d-m-Y h:i:s A'),
                'image' => $return->image,
                'payment' => [
                    'payment_method' => optional($return->order->payment)->payment_method ?? 'N/A',
                    'payment_status' => optional($return->order->payment)->status ?? 'N/A',
                    'reference' => $return->order->payment->payment_reference ?? 'N/A',
                    'paid_at' => optional($return->order->payment)->paid_at
                        ? $return->order->payment->paid_at->format('d-m-Y h:i:s A')
                        : 'N/A',
                ],
                'customer' => [
                    'first_name' => $return->user->first_name,
                    'last_name' => $return->user->last_name,
                    'email' => $return->user->email,
                    'avatar' => $return->user->image,
                ],
                'address' => $return->order->address,
                'items' => $return->items->map(function ($item) {
                    $originalPrice = (float) ($item->orderItem->productVariant->product->price ?? 0);
                    $finalPrice = (float) $item->orderItem->price;

                    return [
                        'return_item_id' => $item->id,
                        'quantity' => $item->quantity,
                        'reason' => $item->reason,
                        'condition' => $item->condition ?? null,
                        'discount' => round(max(0, $originalPrice - $finalPrice), 2),
                        'subtotal' => round($finalPrice * $item->quantity, 2),
                        'product' => [
                            'product_id' => $item->orderItem->productVariant->product->id,
                            'name' => $item->orderItem->product_name,
                            'price' => $originalPrice,
                            'discount_price' => round($finalPrice, 2),
                            'size' => $item->orderItem->size,
                            'color' => $item->orderItem->color,
                            'image' => $item->orderItem->product_image,
                        ],
                    ];
                }),
            ];
        });

        return response()->json([
            'message' => 'Return orders retrieved successfully',
            'data' => $data,
        ]);
    }

    public function show($id)
    {
        $returnOrder = ReturnOrder::with('user', 'items.orderItem')
            ->where('user_id', Auth::id())
            ->find($id);

        if (!$returnOrder) {
            return response()->json([
                'message' => 'Return order not found.'
            ], 404);
        }

        return response()->json([
            'message' => 'Retrieved return order details successfully',
            'data' => [
                'id' => $returnOrder->id,
                'order_number' => $returnOrder->order->order_number,
                'date' => $returnOrder->created_at->format('Y-m-d'),
                'time' => $returnOrder->created_at->format('h:i:s A'),
                'status' => $returnOrder->status ?? 'N/A',
                'return_type' => $returnOrder->return_type ?? 'refund',
                'refund_status' => $returnOrder->refund_status ?? 'N/A',
                'refund_amount' => $returnOrder->refund_amount ?? null,
                'refund_method' => $returnOrder->refund_method ?? null,
                'tracking_number' => $returnOrder->tracking_number ?? null,
                'carrier' => $returnOrder->carrier ?? null,
                'payment_status' => optional($returnOrder->order->payment)->status ?? 'N/A',
                'payment_method' => optional($returnOrder->order->payment)->payment_method ?? 'N/A',
                'shipping_fee' => $returnOrder->order->shipping_fee ?? 'N/A',
                'total_price' => $returnOrder->order->total_price,
                'customer' => [
                    'first_name' => $returnOrder->user->first_name,
                    'last_name' => $returnOrder->user->last_name,
                    'email' => $returnOrder->user->email,
                    'avatar' => $returnOrder->user->image,
                ],
                'shipping_address' => $returnOrder->order->address,
                'items' => $returnOrder->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->orderItem->product_name,
                        'price' => $item->orderItem->price,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->orderItem->price * $item->quantity,
                        'size' => $item->orderItem->size,
                        'color' => $item->orderItem->color,
                        'reason' => $item->reason,
                        'condition' => $item->condition ?? null,
                        'product_image' => $item->orderItem->product_image ?? null,
                    ];
                }),
            ]
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'return_type' => 'nullable|string',
            'note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.reason' => ['required', 'string', Rule::in(self::VALID_REASONS)],
            'images' => 'nullable|array|max:6',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found for this user'], 404);
        }
        if ($order->status === 'refunded') {
            return response()->json(['message' => 'This is order already returned.']);
        }
        if ($order->status !== 'completed') {
            return response()->json(['message' => 'Only completed orders can be returned.'], 400);
        }

        if (!$order->completed_at) {
            return response()->json(['message' => 'Order completion date not found.'], 400);
        }

        if ($order->completed_at->copy()->addDays(self::RETURN_WINDOW_DAYS)->isPast()) {
            return response()->json([
                'message' => 'Return period expired (' . self::RETURN_WINDOW_DAYS . ' days only).'
            ], 400);
        }

        try {
            $returnOrder = DB::transaction(function () use ($request, $order) {


                $orderItemIds = collect($request->items)->pluck('order_item_id')->unique();

                $orderItems = OrderItem::where('order_id', $order->id)
                    ->whereIn('id', $orderItemIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($request->items as $line) {
                    $orderItem = $orderItems->get($line['order_item_id']);

                    if (!$orderItem) {
                        throw new \RuntimeException('Invalid order item.');
                    }

                    // multiple requests.
                    $alreadyReturned = ReturnItem::where('order_item_id', $orderItem->id)
                        ->whereHas('returnOrder', function ($q) {
                            $q->whereNotIn('status', ['rejected', 'cancelled']);
                        })
                        ->sum('quantity');

                    $remaining = $orderItem->quantity - $alreadyReturned;

                    if ($line['quantity'] > $remaining) {
                        throw new \RuntimeException(
                            "Return quantity exceeds remaining returnable quantity for item #{$orderItem->id} (remaining: {$remaining})."
                        );
                    }
                }


                $headerReason = $request->items[0]['reason'];

                $returnOrder = ReturnOrder::create([
                    'user_id' => Auth::id(),
                    'order_id' => $order->id,
                    'return_type' => $request->return_type ?? 'refund',
                    'reason' => $headerReason,
                    'note' => $request->note,
                    'status' => 'requested',
                ]);

                foreach ($request->items as $line) {
                    ReturnItem::create([
                        'return_order_id' => $returnOrder->id,
                        'order_item_id' => $line['order_item_id'],
                        'quantity' => $line['quantity'],
                        'reason' => $line['reason'],
                    ]);
                }

                return $returnOrder;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        if ($request->hasFile('images')) {
            try {
                $imageKit = new ImageKit(
                    config('imagekit.public_key'),
                    config('imagekit.private_key'),
                    config('imagekit.url_endpoint')
                );

                foreach ($request->file('images') as $file) {
                    $upload = $imageKit->upload([
                        'file' => fopen($file->getRealPath(), 'r'),
                        'fileName' => $file->getClientOriginalName(),
                        'folder' => '/return-images',
                    ]);

                    if (isset($upload->error)) {
                        Log::error('Return image upload failed: ' . $upload->error->message);
                        continue;
                    }

                    ReturnImage::create([
                        'return_order_id' => $returnOrder->id,
                        'url' => $upload->result->url,
                    ]);

                    // Keep the legacy single-image column populated too.
                    if (!$returnOrder->image) {
                        $returnOrder->update(['image' => $upload->result->url]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Return image upload exception: ' . $e->getMessage());
            }
        }

        $this->initializeNotification(
            $returnOrder->user,
            'Return Requested',
            'Your return for order #' . $order->order_number . ' has been submitted.',
            'return order',
            'shopping-bag',
            "/account/returns/{$returnOrder->id}"
        );

        try {
            $adminEmail = config('mail.admin_email');
            Mail::to($adminEmail)->send(new AdminReturnRequestMail($returnOrder->load('user', 'items')));
            Mail::to($order->user->email)->send(new ReturnOrderRequestMail($returnOrder->load('items')));
        } catch (\Exception $e) {

            Log::error('Return request email failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Return request submitted successfully.',
            'data' => $returnOrder->load('items'),
        ]);
    }

    // Cancel (customer)
    public function cancel($id)
    {
        $return = ReturnOrder::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$return) {
            return response()->json(['message' => 'Return order not found.'], 404);
        }

        if ($return->status !== 'requested') {
            return response()->json([
                'message' => 'Only requested returns can be cancelled. Once approved, contact support.'
            ], 400);
        }

        $return->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Return request cancelled successfully.']);
    }

    // ---------------------------------------------------------------------
    // Admin: approve
    // ---------------------------------------------------------------------
    public function approve($id)
    {
        $this->ensureAdmin();

        $return = ReturnOrder::with('items.orderItem')->lockForUpdate()->findOrFail($id);

        if ($return->status === 'approved') {
            return response()->json(['message' => 'Return request is already approved'], 400);
        }
        if ($return->status !== 'requested') {
            return response()->json(['message' => 'Return request is not requested'], 400);
        }

        DB::beginTransaction();

        try {
            $refund = 0;
            foreach ($return->items as $item) {
                $orderItem = $item->orderItem;
                if (!$orderItem) {
                    continue;
                }
                $refund += $item->quantity * $orderItem->price;
            }

            $return->update([
                'status' => 'approved',
                'approved_at' => now(),
                'refund_amount' => $refund,
                'refund_status' => 'pending',
            ]);

            DB::commit();

            $this->initializeNotification(
                $return->user,
                'Return Approved',
                'Your return for order #' . $return->order->order_number . ' has been approved. Please ship the item(s) back.',
                'return order',
                'shopping-bag',
                "/account/orders/{$return->id}"
            );

            try {
                broadcast(new OrderStatusUpdated($return->order_id, 'approved', 'unpaid', true));
            } catch (\Exception $e) {
                Log::error('Broadcast failed: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Return approved successfully',
                'data' => $return
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error approving return request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ---------------------------------------------------------------------
    // Admin: reject
    // ---------------------------------------------------------------------
    public function reject(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $return = ReturnOrder::findOrFail($id);

        if ($return->status !== 'requested') {
            return response()->json(['message' => 'Only requested returns can be rejected'], 400);
        }

        $return->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'admin_note' => $request->admin_note,
        ]);

        $this->initializeNotification(
            $return->user,
            'Return Rejected',
            'Your return for order #' . $return->order->order_number . ' has been rejected.',
            'return order',
            'shopping-bag',
            "/account/orders/{$return->id}"
        );

        try {
            broadcast(new OrderStatusUpdated($return->order_id, 'rejected', 'unpaid', true));
        } catch (\Exception $e) {
            Log::error('Broadcast failed: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Rejected']);
    }

    public function markReceived(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->validate([
            'tracking_number' => 'nullable|string|max:100',
            'carrier' => 'nullable|string|max:100',
        ]);

        $return = ReturnOrder::findOrFail($id);

        if ($return->status !== 'approved') {
            return response()->json(['message' => 'Return must be approved before it can be marked received.'], 400);
        }

        $return->update([
            'status' => 'received',
            'received_at' => now(),
            'tracking_number' => $request->tracking_number,
            'carrier' => $request->carrier,
        ]);

        return response()->json(['message' => 'Return marked as received', 'data' => $return]);
    }


    public function complete(Request $request, $id)
    {
        $this->ensureAdmin();

        // Normalize refund method
        if ($request->has('refund_method')) {
            $request->merge([
                'refund_method' => strtolower($request->input('refund_method')),
            ]);
        }

        $request->validate([
            'items' => 'nullable|array',
            'items.*.return_item_id' => 'required_with:items|exists:return_items,id',
            'items.*.condition' => [
                'required_with:items',
                Rule::in(['good', 'damaged']),
            ],
            'refund_method' => [
                'nullable',
                'string',
                Rule::in([
                    'original_payment',
                    'store_credit',
                    'khqr',
                    'bank_transfer',
                    'exchange',
                ]),
            ],
        ]);

        DB::beginTransaction();

        try {
            // Lock return to prevent double completion
            $return = ReturnOrder::with([
                'items.orderItem.productVariant',
                'user',
                'order.items',
                'order.payment',
            ])
                ->lockForUpdate()
                ->find($id);

            if (!$return) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Return order not found.',
                ], 404);
            }

            if ($return->status === 'completed') {
                DB::rollBack();

                return response()->json([
                    'message' => 'Return is already completed.',
                ], 400);
            }

            if (!in_array($return->status, ['approved', 'received'], true)) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Return must be approved or received before completing.',
                ], 400);
            }

            $conditionMap = collect($request->items ?? [])
                ->keyBy('return_item_id');

            /*
            |--------------------------------------------------------------------------
            | 1. Validate requested return items
            |--------------------------------------------------------------------------
            */

            foreach ($conditionMap as $returnItemId => $data) {
                $returnItem = $return->items->firstWhere('id', $returnItemId);

                if (!$returnItem) {
                    throw new \RuntimeException(
                        "Return item #{$returnItemId} does not belong to this return."
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Process conditions + restock
            |--------------------------------------------------------------------------
            */

            foreach ($return->items as $item) {

                $condition = $conditionMap
                    ->get($item->id)['condition']
                    ?? $item->condition
                    ?? 'good';

                $item->update([
                    'condition' => $condition,
                ]);

                if (
                    $condition === 'good' &&
                    $item->restocked !== true &&
                    $item->orderItem &&
                    $item->orderItem->productVariant
                ) {
                    $item->orderItem
                        ->productVariant
                        ->increment('stock', $item->quantity);

                    $item->update([
                        'restocked' => true,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Calculate refund
            |--------------------------------------------------------------------------
            */

            $order = $return->order;

            $orderSubtotal = (float) $order->sub_total_price;
            $couponDiscount = (float) ($order->coupon_discount ?? 0);

            $calculatedRefund = $return->items->sum(
                function ($item) use ($orderSubtotal, $couponDiscount) {

                    if (!$item->orderItem) {
                        return 0;
                    }

                    $itemTotal =
                        $item->quantity *
                        (float) $item->orderItem->price;

                    // Pro-rate coupon discount
                    if ($orderSubtotal > 0 && $couponDiscount > 0) {

                        $itemCouponShare =
                            ($itemTotal / $orderSubtotal) *
                            $couponDiscount;

                        return max(
                            0,
                            $itemTotal - $itemCouponShare
                        );
                    }

                    return $itemTotal;
                }
            );

            /*
            |--------------------------------------------------------------------------
            | 4. Never refund more than customer actually paid
            |--------------------------------------------------------------------------
            */

            $finalRefundAmount = min(
                round($calculatedRefund, 2),
                (float) $order->total_price
            );

            $return->update([
                'refund_amount' => $finalRefundAmount,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 5. Process refund
            |--------------------------------------------------------------------------
            */

            $refundMethod =
                $request->refund_method
                ?? $return->refund_method
                ?? 'original_payment';

            $refundRecord = $this->processRefund(
                $return,
                $refundMethod
            );

            /*
            |--------------------------------------------------------------------------
            | 6. Complete return
            |--------------------------------------------------------------------------
            */

            $return->update([
                'status' => 'completed',
                'completed_at' => now(),
                'refund_status' =>
                    $refundRecord->status === 'completed'
                    ? 'refunded'
                    : 'failed',
                'refund_method' => $refundMethod,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 7. Update parent order status
            |--------------------------------------------------------------------------
            */

            $totalOrdered = $order->items->sum('quantity');

            $totalReturned = ReturnItem::whereHas(
                'returnOrder',
                function ($query) use ($return) {
                    $query
                        ->where('order_id', $return->order_id)
                        ->where('status', 'completed');
                }
            )->sum('quantity');

            $newOrderStatus =
                $totalReturned >= $totalOrdered
                ? 'refunded'
                : 'partially_refunded';

            $order->update([
                'status' => $newOrderStatus,
            ]);

            DB::commit();

            /*
            |--------------------------------------------------------------------------
            | 8. Notifications
            |--------------------------------------------------------------------------
            */

            $this->initializeNotification(
                $return->user,
                'Return Completed',
                'Your return for order #' .
                $order->order_number .
                ' is completed. Refund has been issued.',
                'return order',
                'shopping-bag',
                "/account/returns/{$return->id}"
            );

            /*
            |--------------------------------------------------------------------------
            | 9. Real-time update
            |--------------------------------------------------------------------------
            */

            try {
                broadcast(
                    new OrderStatusUpdated(
                        $return->order_id,
                        'completed',
                        $newOrderStatus,
                        true
                    )
                );
            } catch (\Exception $e) {
                Log::error(
                    'Broadcast failed: ' .
                    $e->getMessage()
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 10. Email
            |--------------------------------------------------------------------------
            */

            try {
                if ($return->user?->email) {
                    Mail::to($return->user->email)
                        ->send(new ReturnCompletedMail($return));
                }
            } catch (\Exception $e) {
                Log::error(
                    'Return completion email failed: ' .
                    $e->getMessage()
                );
            }

            return response()->json([
                'message' => 'Return completed successfully',
                'data' => $return->fresh([
                    'items.orderItem.productVariant',
                    'order',
                    'refunds',
                ]),
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error(
                'Failed to complete return #' .
                $id .
                ': ' .
                $e->getMessage()
            );

            return response()->json([
                'message' => 'Failed to complete return',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // public function complete(Request $request, $id)
    // {
    //     $this->ensureAdmin();
    //     if (!ReturnOrder::find($id)) {
    //         return response()->json([
    //             'message' => "Return not found"
    //         ]);
    //     }
    //     $request->validate([

    //         'items' => 'nullable|array',
    //         'items.*.return_item_id' => 'required_with:items|exists:return_items,id',
    //         'items.*.condition' => ['required_with:items', Rule::in(['good', 'damaged'])],
    //         'refund_method' => 'nullable|in:original_payment,store_credit',
    //     ]);

    //     $return = ReturnOrder::with([
    //         'items.orderItem.variant',
    //         'items.orderItem.product',
    //         'user',
    //         'order.items',
    //     ])->findOrFail($id);

    //     if ($return->status === 'completed') {
    //         return response()->json(['message' => 'Already completed'], 400);
    //     }


    //     if (!in_array($return->status, ['approved', 'received'])) {
    //         return response()->json(['message' => 'Return must be approved (and ideally received) before completing.'], 400);
    //     }

    //     $conditionMap = collect($request->items ?? [])->keyBy('return_item_id');

    //     DB::beginTransaction();

    //     try {
    //         foreach ($return->items as $item) {
    //             $condition = $conditionMap->get($item->id)['condition'] ?? 'good';
    //             $item->update(['condition' => $condition]);

    //             if (!$item->orderItem || !$item->orderItem->variant) {
    //                 continue;
    //             }

    //             if ($condition === 'good') {
    //                 $item->orderItem->variant->increment('stock', $item->quantity);

    //                 $item->update([
    //                     'restocked' => true,
    //                 ]);
    //             }


    //             $refundMethod = $request->refund_method ?? $return->refund_method ?? 'original_payment';
    //             $this->processRefund($return, $refundMethod);

    //             $return->update([
    //                 'status' => 'completed',
    //                 'completed_at' => now(),
    //                 'refund_status' => 'refunded',
    //                 'refund_method' => $refundMethod,
    //             ]);

    //             $totalOrdered = $return->order->items->sum('quantity');
    //             $totalReturned = ReturnItem::whereHas('returnOrder', function ($q) use ($return) {
    //                 $q->where('order_id', $return->order_id)->where('status', 'completed');
    //             })->sum('quantity') + $return->items->sum('quantity');

    //             $return->order()->update([
    //                 'status' => $totalReturned >= $totalOrdered ? 'refunded' : 'partially_refunded',
    //             ]);

    //             DB::commit();

    //             $this->initializeNotification(
    //                 $return->user,
    //                 'Return Completed',
    //                 'Your return for order #' . $return->order->order_number . ' is completed. Refund has been issued.',
    //                 'return order',
    //                 'shopping-bag',
    //                 "/account/orders/{$return->id}"
    //             );

    //             try {
    //                 broadcast(new OrderStatusUpdated($return->order_id, 'completed', 'refunded', true));
    //             } catch (\Exception $e) {
    //                 Log::error('Broadcast failed: ' . $e->getMessage());
    //             }

    //             DB::afterCommit(function () use ($return) {
    //                 if ($return->user && $return->user->email) {
    //                     Mail::to($return->user->email)->send(new ReturnCompletedMail($return));
    //                 }
    //             });

    //             return response()->json([
    //                 'message' => 'Return completed successfully',
    //                 'data' => $return->fresh(['items.orderItem.variant', 'items.orderItem.product', 'order']),
    //             ]);
    //         }
    //     } catch (\Exception $e) {
    //         DB::rollBack();

    //         $return->update(['refund_status' => 'failed']);

    //         Log::error('Failed to complete return #' . $return->id . ': ' . $e->getMessage());

    //         return response()->json([
    //             'message' => 'Failed to complete return',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    // private function processRefund(ReturnOrder $return, string $method): void
    // {
    //     if ($method === 'store_credit') {
    //         return;
    //     }
    // }

    private function processRefund(ReturnOrder $return, string $method): Refund
    {
        $amount = $return->refund_amount ?? $return->items->sum(fn($i) => $i->quantity * $i->orderItem->price);
        $originalPayment = $return->order->payment;
        $provider = $originalPayment->payment_method ?? 'manual';

        $refundData = [
            'order_id' => $return->order_id,
            'return_order_id' => $return->id,
            'payment_id' => $originalPayment->id ?? null,
            'user_id' => $return->user_id,
            'amount' => $amount,
            'method' => $method,
            'provider' => $provider,
            'status' => 'completed',
            'refund_reference' => 'REF-' . strtoupper(uniqid()),
            'processed_at' => now(),
        ];

        if ($method === 'store_credit') {
            $return->user->increment('wallet_balance', $amount);
            $refundData['provider'] = 'internal_wallet';
        } elseif ($method === 'khqr') {
            $refundData['provider'] = 'bakong';
        }

        return Refund::create($refundData);
    }
}
