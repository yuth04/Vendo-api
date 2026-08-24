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
use App\Models\ReturnImage;
use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use ImageKit\ImageKit;
use App\Traits\NotifiesUsers;
class ReturnOrderController extends Controller
{
    use NotifiesUsers;
    // view all return (Admin)
    public function getAll()
    {
        $returns = ReturnOrder::with([
            'items.orderItem'
        ])->get();

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
        $returnOrder = ReturnOrder::with('user', 'items')->where('user_id', Auth::id())->orderBy('id', 'asc')->find($id);

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
                        'quantity' => $item->orderItem->quantity,
                        'subtotal' => $item->orderItem->price * $item->quantity,
                        'size' => $item->orderItem->size,
                        'color' => $item->orderItem->color,
                        'product_image' => $item->orderItem->product_image
                            ?? null,
                    ];
                }),
            ]
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'reason' => 'required|string',
            'note' => 'nullable|string',
            'order_item_id' => 'required|exists:order_items,id',
            'quantity' => 'required|integer|min:1',
            'image' => 'nullable|image|mimes:jpg,png|max:2048'
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found for this user'
            ], 404);
        }

        if ($order->status !== 'completed') {
            return response()->json([
                'message' => 'Only completed orders can be returned.'
            ], 400);
        }

        if (!$order->completed_at) {
            return response()->json([
                'message' => 'Order completion date not found.'
            ], 400);
        }

        if ($order->completed_at->copy()->addDays(7)->isPast()) {
            return response()->json([
                'message' => 'Return period expired (7 days only).'
            ], 400);
        }

        if (ReturnOrder::where('order_id', $order->id)->exists()) {
            return response()->json([
                'message' => 'Return request already exists for this order.'
            ], 400);
        }

        $orderItem = OrderItem::where('id', $request->order_item_id)
            ->where('order_id', $order->id)
            ->first();

        if (!$orderItem) {
            return response()->json([
                'message' => 'Invalid order item.'
            ], 400);
        }

        // ✅ Prevent over-return
        if ($request->quantity > $orderItem->quantity) {
            return response()->json([
                'message' => 'Return quantity exceeds purchased quantity.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $imageUrl = null;

            // ✅ Upload AFTER validation
            if ($request->hasFile('image')) {
                $imageKit = new ImageKit(
                    config('imagekit.public_key'),
                    config('imagekit.private_key'),
                    config('imagekit.url_endpoint')
                );

                $upload = $imageKit->upload([
                    'file' => fopen($request->file('image')->getRealPath(), "r"),
                    'fileName' => $request->file('image')->getClientOriginalName(),
                    'folder' => '/return-images',
                ]);

                if (isset($upload->error)) {
                    return response()->json([
                        'message' => 'Upload failed',
                        'error' => $upload->error->message,
                    ], 400);
                }

                $imageUrl = $upload->result->url;
            }

            $returnOrder = ReturnOrder::create([
                'user_id' => Auth::id(),
                'order_id' => $order->id,
                'reason' => $request->reason,
                'note' => $request->note,
                'status' => 'requested',
                'image' => $imageUrl
            ]);

            ReturnItem::create([
                'return_order_id' => $returnOrder->id,
                'order_item_id' => $request->order_item_id,
                'quantity' => $request->quantity,
                'reason' => $request->reason,
            ]);

            DB::commit();
            $this->initializeNotification(
                $returnOrder->user,
                'Return Requested',
                'Your return for order #' . $returnOrder->order_number . ' has been submitted.',
                'return order',
                'shopping-bag',
                "/account/returns/{$returnOrder->id}"
            );

            $adminEmail = config('mail.admin_email');

            Mail::to($adminEmail)->send(
                new AdminReturnRequestMail($returnOrder->load('user'))
            );

            Mail::to($order->user->email)->send(
                new ReturnOrderRequestMail($returnOrder)
            );

            return response()->json([
                'message' => 'Return request submitted successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // destroy
    public function destroy($id)
    {
        $return = ReturnOrder::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$return) {
            return response()->json([
                'message' => 'Return order not found.'
            ], 404);
        }

        // Only allow cancel if status is still 'requested'
        if ($return->status !== 'requested') {
            return response()->json([
                'message' => 'Only requested returns can be cancelled.'
            ], 400);
        }

        $return->delete();

        return response()->json([
            'message' => 'Return request cancelled successfully.'
        ]);
    }
    // Approve
    public function approve($id)
    {
        $return = ReturnOrder::with('items.orderItem')->findOrFail($id);
        if ($return->status === 'approved') {
            return response()->json([
                'message' => 'Return request is already approved'
            ], 400);
        }
        if ($return->status !== 'requested') {
            return response()->json([
                'message' => 'Return request is not requested'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $refund = 0;

            foreach ($return->items as $item) {

                $orderItem = $item->orderItem;

                if (!$orderItem) {
                    continue;
                }

                // safer calculation
                $refund += $item->quantity * $orderItem->price;
            }

            $return->update([
                'status' => 'approved',
                'approved_at' => now(),
                'refund_amount' => $refund
            ]);

            DB::commit();
            $this->initializeNotification(
                $return->user,
                'Return Approved',
                'Your return for order #' . $return->order->order_number . ' has been approved.',
                'return order',
                'shopping-bag',
                "/account/orders/{$return->id}"
            );
            try {
                broadcast(new OrderStatusUpdated(
                    $return->order_id,
                    'approved',
                    'unpaid',
                    true
                ));
            } catch (\Exception $e) {
                \Log::error('Broadcast failed: ' . $e->getMessage());
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
    // Reject
    public function reject($id)
    {
        $return = ReturnOrder::findOrFail($id);

        if ($return->status !== 'requested') {
            return response()->json(['message' => 'Only requested returns can be rejected'], 400);
        }

        $return->update([
            'status' => 'rejected',
            'rejected_at' => now()
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
            broadcast(new OrderStatusUpdated(
                $return->order_id,
                'reject',
                'unpaid',
                true
            ));
        } catch (\Exception $e) {
            \Log::error('Broadcast failed: ' . $e->getMessage());
        }
        return response()->json(['message' => 'Rejected']);
    }
    public function complete($id)
    {
        $return = ReturnOrder::with([
            'items.orderItem.variant',
            'items.orderItem.product',
            'user',
            'order'
        ])->findOrFail($id);

        if ($return->status === 'completed') {
            return response()->json(['message' => 'Already completed'], 400);
        }

        if ($return->status !== 'approved') {
            return response()->json(['message' => 'Not approved'], 400);
        }

        DB::beginTransaction();

        try {
            foreach ($return->items as $item) {
                if (!$item->orderItem || !$item->orderItem->variant) {
                    continue;
                }

                $variant = $item->orderItem->variant;
                $variant->increment('stock', $item->quantity);
            }

            $return->update([
                'status' => 'completed',
                'completed_at' => now(),
                'refund_status' => 'refunded',
            ]);

            // Update the order itself to reflect the refund
            $return->order()->update([
                'status' => 'refunded',
            ]);

            DB::commit();

            $this->initializeNotification(
                $return->user,
                'Return Completed',
                'Your return for order #' . $return->order->order_number . ' is completed. Refund has been issued.',
                'return order',
                'shopping-bag',
                "/account/orders/{$return->id}"
            );

            try {
                broadcast(new OrderStatusUpdated(
                    $return->order_id,
                    'completed',
                    'refunded',
                    true
                ));
            } catch (\Exception $e) {
                \Log::error('Broadcast failed: ' . $e->getMessage());
            }

            DB::afterCommit(function () use ($return) {
                if ($return->user && $return->user->email) {
                    Mail::to($return->user->email)->send(new ReturnCompletedMail($return));
                }
            });

            return response()->json([
                'message' => 'Return completed successfully',
                'data' => $return->fresh([
                    'items.orderItem.variant',
                    'items.orderItem.product',
                    'order',
                ])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to complete return',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // public function complete($id)
    // {
    //     $return = ReturnOrder::with([
    //         'items.orderItem.variant',
    //         'items.orderItem.product',
    //         'user'
    //     ])->findOrFail($id);

    //     if ($return->status === 'completed') {
    //         return response()->json(['message' => 'Already completed'], 400);
    //     }

    //     if ($return->status !== 'approved') {
    //         return response()->json(['message' => 'Not approved'], 400);
    //     }

    //     DB::beginTransaction();

    //     try {
    //         foreach ($return->items as $item) {
    //             if (!$item->orderItem || !$item->orderItem->variant) {
    //                 continue;
    //             }

    //             $variant = $item->orderItem->variant;
    //             $variant->increment('stock', $item->quantity);
    //         }

    //         $return->update([
    //             'status' => 'completed',
    //             'completed_at' => now(),
    //             'refund_status' => 'refunded',
    //         ]);
    //         $return->order()->update([
    //             'status' => 'returned',
    //         ]);
    //         DB::commit();
    //         $this->initializeNotification(
    //             $return->user,
    //             'Return Completed',
    //             'Your return for order #' . $return->order->order_number . ' is completed. Refund has been issued.',
    //             'return order',
    //             'shopping-bag',
    //             "/account/orders/{$return->id}"
    //         );
    //         try {
    //             broadcast(new OrderStatusUpdated(
    //                 $return->order_id,
    //                 'completed',
    //                 'unpaid',
    //                 true
    //             ));
    //         } catch (\Exception $e) {
    //             \Log::error('Broadcast failed: ' . $e->getMessage());
    //         }
    //         DB::afterCommit(function () use ($return) {
    //             if ($return->user && $return->user->email) {
    //                 Mail::to($return->user->email)->send(new ReturnCompletedMail($return));
    //             }
    //         });

    //         return response()->json([
    //             'message' => 'Return completed successfully',
    //             'data' => $return->fresh([
    //                 'items.orderItem.variant',
    //                 'items.orderItem.product',
    //                 // 'user'
    //             ])
    //         ]);

    //     } catch (\Exception $e) {
    //         DB::rollBack();

    //         return response()->json([
    //             'message' => 'Failed to complete return',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

}
