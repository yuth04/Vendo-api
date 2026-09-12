<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnOrder;
use Illuminate\Http\Request;

class PosController extends Controller
{
    // Order History
    public function orderHistory()
    {

        $orders = Order::with(['user', 'items', 'payment'])->where('created_at', '>=', now()->subMonths(2))->latest()->get();
        $data = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'date' => $order->created_at->format('d-m-Y'),
                'time' => $order->created_at->format('h:i:s A'),
                'first_name' => $order->user->first_name,
                'last_name' => $order->user->last_name,
                'email' => $order->user->email,
                'avatar' => $order->user->image,
                'total_price' => $order->total_price,
                'payment_method' => $order->payment->payment_method ?? null,
                'items_count' => $order->items->count(),
                'status' => $order->status,
                'payment' => $order->payment->status ?? null,

            ];
        });
        return response()->json([
            'message' => 'Retrieved order history successfully',
            'data' => $data
        ], 200);
    }
    // Order Details
    public function orderDetails($id)
    {

        $order = Order::with([
            'user',
            'items.variant.product',
            'payment',
            'address',
        ])->find($id);
        if (!$order) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }
        return response()->json([
            'message' => 'Retrieved order details successfully',
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'date' => $order->created_at->format('d-m-Y h:i:s A'),
                'status' => $order->status,
                'payment_status' => optional($order->payment)->status,
                'payment_method' => optional($order->payment)->payment_method,
                'discount_amount' => $order->discount_amount ?? 0,
                'coupon_amount' => $order->coupon_discount ?? 0,
                'coupon_code' => $order->coupon_code ?? null,
                'shipping_fee' => $order->shipping_fee,
                'customer' => [
                    'first_name' => $order->user->first_name,
                    'last_name' => $order->user->last_name,
                    'email' => $order->user->email,
                    'avatar' => $order->user->image,
                ],
                'shipping_address' => $order->address,
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->product_name,
                        'price' => $item->price,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->price * $item->quantity,
                        'size' => $item->size,
                        'color' => $item->color,
                        'product_image' => $item->product_image
                            ?? $item->variant?->product?->image
                            ?? null,
                    ];
                }),
            ]
        ]);
    }
    // Return History
    public function returnHistory()
    {

        $returns = ReturnOrder::with(['user', 'items'])->latest()->get();
        $data = $returns->map(function ($returnOrder) {
            return [
                'id' => $returnOrder->id,
                'order_number' => $returnOrder->order->order_number,
                'products' => $returnOrder->items->count(),
                'date' => $returnOrder->created_at->format('d-m-Y'),
                'time' => $returnOrder->created_at->format('h:i:s A'),
                'first_name' => $returnOrder->user->first_name,
                'last_name' => $returnOrder->user->last_name,
                'email' => $returnOrder->user->email,
                'avatar' => $returnOrder->user->image,
                // 'reason' => $returnOrder->reason,
                'note' => $returnOrder->note,
                'status' => $returnOrder->status ?? 'N/A',
                'payment' => $returnOrder->order->payment->status ?? 'N/A',
                'total_price' => $returnOrder->order->total_price,

            ];
        });

        return response()->json([
            'message' => 'Retrieved return history successfully',
            'data' => $data
        ], 200);

    }

    // Admin: Get Return Order Details
    public function returnDetails($id)
    {

        $return = ReturnOrder::with([
            'user',

            // Return Items
            'items.orderItem.variant.product',
            'items.replacementVariant.product',
            'items.replacementVariant.images',

            // Refund & Exchange belong to ReturnItem
            'items.refund',
            'items.exchange',

            // Order
            'order.items.variant.product',
            'order.payment',
            'order.address',
            'order.user',
        ])->find($id);

        if (!$return) {
            return response()->json([
                'message' => 'Return order not found.',
            ], 404);
        }

        $order = $return->order;

        if (!$order) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Order Calculations
        |--------------------------------------------------------------------------
        */
        $shipping = (float) ($order->shipping_fee ?? 0);

        $originalSubtotal = $order->items->sum(function ($item) {
            return (float) (
                $item->variant?->product?->price ?? 0
            ) * (int) $item->quantity;
        });

        $finalSubtotal = $order->items->sum(function ($item) {
            return (float) ($item->price ?? 0)
                * (int) $item->quantity;
        });

        $productDiscount = max(
            0,
            $originalSubtotal - $finalSubtotal
        );

        $couponDiscount = (float) ($order->coupon_discount ?? 0);

        $totalPayment = max(
            0,
            ($finalSubtotal - $couponDiscount) + $shipping
        );

        return response()->json([
            'message' => 'Retrieved return order details successfully',
            'data' => [
                'id' => $return->id,
                'return_number' => $return->return_number,
                'status' => $return->status,
                'return_type' => $return->items->first()?->return_type,
                'tracking_number' => $return->tracking_number,
                'carrier' => $return->carrier,
                'admin_note' => $return->admin_note,

                'timestamps' => [
                    'approved_at' => $return->approved_at
                            ?->format('d-m-Y h:i:s A'),
                    'received_at' => $return->received_at
                            ?->format('d-m-Y h:i:s A'),
                    'inspected_at' => $return->inspected_at
                            ?->format('d-m-Y h:i:s A'),
                    'completed_at' => $return->completed_at
                            ?->format('d-m-Y h:i:s A'),
                    'rejected_at' => $return->rejected_at
                            ?->format('d-m-Y h:i:s A'),
                    'created_at' => $return->created_at
                            ?->format('d-m-Y'),
                    'time' => $return->created_at
                            ?->format('h:i:s A'),
                ],

                'order' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,

                    'original_price' => number_format(
                        $originalSubtotal,
                        2,
                        '.',
                        ''
                    ),

                    'product_discount' => number_format(
                        $productDiscount,
                        2,
                        '.',
                        ''
                    ),

                    'sub_total_price' => number_format(
                        $finalSubtotal,
                        2,
                        '.',
                        ''
                    ),

                    'coupon_discount' => number_format(
                        $couponDiscount,
                        2,
                        '.',
                        ''
                    ),

                    'shipping_fee' => number_format(
                        $shipping,
                        2,
                        '.',
                        ''
                    ),

                    'total_price' => number_format(
                        $totalPayment,
                        2,
                        '.',
                        ''
                    ),

                    'save' => number_format(
                        $productDiscount + $couponDiscount,
                        2,
                        '.',
                        ''
                    ),

                    'coupon' => [
                        'code' => $order->coupon_code,

                        'discount' => number_format(
                            $couponDiscount,
                            2,
                            '.',
                            ''
                        ),
                    ],

                    'created_at' => $order->created_at
                            ?->format('d-m-Y'),

                    'time' => $order->created_at
                            ?->format('h:i:s A'),

                    /*
                    |--------------------------------------------------------------------------
                    | Payment
                    |--------------------------------------------------------------------------
                    */
                    'payment' => [
                        'method' => $order->payment?->payment_method,
                        'status' => $order->payment?->status,
                        'reference' => $order->payment?->payment_reference,
                        'paid_at' => $order->payment?->paid_at
                                ?->format('d-m-Y h:i:s A'),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Address
                    |--------------------------------------------------------------------------
                    */
                    'address' => [
                        'first_name' => $order->address?->first_name,
                        'last_name' => $order->address?->last_name,
                        'address_line' => $order->address?->address_line,
                        'city' => $order->address?->city,
                        'province' => $order->address?->province,
                        'phone' => $order->address?->phone
                            ?? $order->user?->contact,
                        'note' => $order->note,
                        'postal_code' => $order->address?->postal_code,
                    ],
                ],

                'customer' => [
                    'first_name' => $return->user?->first_name,
                    'last_name' => $return->user?->last_name,
                    'email' => $return->user?->email,
                    'avatar' => $return->user?->image,
                ],

                'return_items' => $return->items->map(function ($item) {
                    $orderItem = $item->orderItem;

                    $originalPrice = (float) (
                        $orderItem?->variant?->product?->price ?? 0
                    );

                    $finalPrice = (float) (
                        $orderItem?->price ?? 0
                    );

                    $refund = $item->refund;
                    $exchange = $item->exchange;

                    $replacementImage = $item->replacementVariant
                        ?->images
                            ?->first()?->image;

                    return [
                        'id' => $item->id,
                        'order_item_id' => $item->order_item_id,
                        'quantity' => $item->quantity,

                        'save' => number_format(
                            max(
                                0,
                                $originalPrice - $finalPrice
                            ) * $item->quantity,
                            2,
                            '.',
                            ''
                        ),

                        'subtotal' => number_format(
                            $finalPrice * $item->quantity,
                            2,
                            '.',
                            ''
                        ),

                        'reason' => $item->reason,
                        'condition' => $item->condition,
                        'restocked' => (bool) $item->restocked,
                        'exchange_quantity' => $item->exchange_quantity,

                        'exchange_price_difference' => number_format(
                            (float) (
                                $item->exchange_price_difference ?? 0
                            ),
                            2,
                            '.',
                            ''
                        ),

                        /*
                        |--------------------------------------------------------------------------
                        | REFUND
                        |--------------------------------------------------------------------------
                        */
                        'refund' => $refund ? [
                            'id' => $refund->id,

                            'amount' => number_format(
                                (float) ($refund->amount ?? 0),
                                2,
                                '.',
                                ''
                            ),

                            'method' => $refund->method,
                            'provider' => $refund->provider,
                            'status' => $refund->status,
                            'reference' => $refund->refund_reference,
                            'processed_at' => $refund->processed_at
                                    ?->format('d-m-Y h:i:s A'),
                        ] : null,

                        /*
                        |--------------------------------------------------------------------------
                        | EXCHANGE
                        |--------------------------------------------------------------------------
                        */
                        'exchange' => $exchange ? [
                            'id' => $exchange->id,
                            'status' => $exchange->status,

                            'price_difference' => number_format(
                                (float) (
                                    $exchange->price_difference ?? 0
                                ),
                                2,
                                '.',
                                ''
                            ),

                            'tracking_number' => $exchange->tracking_number,
                            'carrier' => $exchange->carrier,

                            'processing_at' => $exchange->processing_at
                                    ?->format('d-m-Y h:i:s A'),

                            'shipped_at' => $exchange->shipped_at
                                    ?->format('d-m-Y h:i:s A'),

                            'received_at' => $exchange->received_at
                                    ?->format('d-m-Y h:i:s A'),
                        ] : null,
                        // Item Details
                        'item_details' => [
                            'id' => $orderItem?->product_variant_id,
                            'size' => $orderItem?->size,
                            'color' => $orderItem?->color,
                            'price' => number_format(
                                $originalPrice,
                                2,
                                '.',
                                ''
                            ),
                            'discount_price' => number_format(
                                $finalPrice,
                                2,
                                '.',
                                ''
                            ),
                            'product' => [
                                'id' => $orderItem?->variant?->product?->id,
                                'name' => $orderItem?->product_name
                                    ?? $orderItem?->variant?->product?->name,
                                'slug' => $orderItem?->variant?->product?->slug,
                                'image' => $orderItem?->product_image
                                    ?? $orderItem?->variant?->product?->image,
                            ],
                        ],
                        // Replacement Variant
                        'replacement_variant' => $item->replacementVariant ? [
                            'id' => $item->replacementVariant->id,
                            'size' => $item->replacementVariant->size,
                            'color' => $item->replacementVariant->color,
                            'image' => $replacementImage,
                            'unit_price' => number_format(
                                (float) (
                                    $item->replacementVariant
                                        ->product
                                            ?->price ?? 0
                                ),
                                2,
                                '.',
                                ''
                            ),
                            'stock' => $item->replacementVariant->stock,
                        ] : null,
                    ];
                })->values(),
            ],
        ]);
    }









    // Return Details
    // public function returnDetails($id)
    // {

    //     $returnOrder = ReturnOrder::with([
    //         'user',
    //         'items.orderItem.product',
    //         'order.payment',
    //         'order.address',
    //     ])->find($id);
    //     if (!$returnOrder) {
    //         return response()->json([
    //             'message' => 'Return order not found'
    //         ], 404);
    //     }
    //     return response()->json([
    //         'message' => 'Retrieved return order details successfully',
    //         'data' => [
    //             'id' => $returnOrder->id,
    //             'order_number' => $returnOrder->order->order_number,
    //             'date' => $returnOrder->created_at->format('d-m-Y h:i:s A'),
    //             'reason' => $returnOrder->reason,
    //             'note' => $returnOrder->note,
    //             'status' => $returnOrder->status ?? 'N/A',
    //             'payment_status' => optional($returnOrder->order->payment)->status ?? 'N/A',
    //             'payment_method' => optional($returnOrder->order->payment)->payment_method ?? 'N/A',
    //             'shipping_fee' => $returnOrder->order->shipping_fee ?? 'N/A',
    //             'total_price' => $returnOrder->order->total_price,
    //             'customer' => [
    //                 'first_name' => $returnOrder->user->first_name,
    //                 'last_name' => $returnOrder->user->last_name,
    //                 'email' => $returnOrder->user->email,
    //                 'avatar' => $returnOrder->user->image,
    //             ],
    //             'shipping_address' => $returnOrder->order->address,
    //             'items' => $returnOrder->items->map(function ($item) {
    //                 return [
    //                     'id' => $item->id,
    //                     'product_name' => $item->orderItem->product_name,
    //                     'price' => $item->orderItem->price,
    //                     'quantity' => $item->quantity,
    //                     'subtotal' => $item->orderItem->price * $item->quantity,
    //                     'size' => $item->orderItem->size,
    //                     'color' => $item->orderItem->color,
    //                     'product_image' => $item->orderItem->product_image
    //                         ?? null,
    //                 ];
    //             }),
    //         ]
    //     ]);
    // }
}
