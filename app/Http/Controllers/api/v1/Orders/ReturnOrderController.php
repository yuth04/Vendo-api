<?php

namespace App\Http\Controllers\api\v1\Orders;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Mail\AdminReturnRequestMail;
use App\Mail\ReturnOrderRequestMail;
use App\Models\Exchange;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\ReturnImage;
use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use App\Services\RefundService;
use App\Traits\NotifiesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use ImageKit\ImageKit;

class ReturnOrderController extends Controller
{
    use NotifiesUsers;

    protected $RefundService;
    private const RETURN_WINDOW_DAYS = 7;

    private const CONDITIONS = [
        'good',
        'damaged',
    ];

    public function __construct(RefundService $refundService)
    {
        $this->RefundService = $refundService;
    }

    // Admin Authorization
    private function ensureAdmin(): void
    {
        $user = Auth::user();

        abort_if(
            !$user || !in_array($user->role ?? null, ['admin', 'super-admin'], true),
            403,
            'Unauthorized.'
        );
    }

    // Admin: Get All Returns
    public function getAll(Request $request)
    {
        $this->ensureAdmin();

        $query = ReturnOrder::with([
            'items.orderItem.productVariant.product',
            'items.replacementVariant.product',
            'user',
            'order.payment',
            'refund',
            'exchange',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('return_type')) {
            $query->whereHas('items', function ($q) use ($request) {
                $q->where('return_type', $request->string('return_type'));
            });
        }

        $returns = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'message' => 'Retrieved return orders successfully',
            'data' => $returns,
        ]);
    }

    // Customer: List My Returns
    public function index()
    {
        $returns = ReturnOrder::with([
            'order.items.variant.product',
            'order.payment',
            'items.orderItem.productVariant.product',
            'items.replacementVariant.product',
            'user',
            'refund',
            'exchange',
        ])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        $data = $returns->map(function ($return) {
            $order = $return->order;

            $originalSubtotal = $order->items->sum(function ($item) {
                return (float) ($item->variant->product->price ?? 0) * $item->quantity;
            });

            $finalSubtotal = $order->items->sum(function ($item) {
                return (float) $item->price * $item->quantity;
            });

            $productDiscount = max(0, $originalSubtotal - $finalSubtotal);
            $couponDiscount = (float) ($order->coupon_discount ?? 0);
            $shipping = (float) ($order->shipping_fee ?? 0);
            $totalPayment = max(0, ($finalSubtotal - $couponDiscount) + $shipping);

            return [
                'return_id' => $return->id,
                'order_id' => $return->order_id,
                'order_number' => $order->order_number,

                'status' => $return->status,
                'return_type' => $return->return_type,

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

                'refund' => $return->refund ? [
                    'id' => $return->refund->id,
                    'amount' => $return->refund->amount,
                    'method' => $return->refund->method,
                    'provider' => $return->refund->provider,
                    'status' => $return->refund->status,
                    'reference' => $return->refund->refund_reference,
                    'processed_at' => $return->refund->processed_at,
                ] : null,

                'exchange' => $return->exchange ? [
                    'id' => $return->exchange->id,
                    'status' => $return->exchange->status,
                    'price_difference' => $return->exchange->price_difference,
                    'tracking_number' => $return->exchange->tracking_number,
                    'carrier' => $return->exchange->carrier,
                    'processing_at' => $return->exchange->processing_at?->format('d-m-Y g:i:s A'),
                    'shipped_at' => $return->exchange->shipped_at?->format('d-m-Y g:i:s A'),
                    'received_at' => $return->exchange->received_at?->format('d-m-Y g:i:s A'),
                ] : null,

                'created_at' => $return->created_at ? $return->created_at->format('d-m-Y h:i:s A') : null,

                'image' => $return->image,

                'payment' => [
                    'payment_method' => optional($order->payment)->payment_method ?? 'N/A',
                    'payment_status' => optional($order->payment)->status ?? 'N/A',
                    'reference' => optional($order->payment)->payment_reference ?? 'N/A',
                    'paid_at' => optional($order->payment)->paid_at
                        ? $order->payment->paid_at->format('d-m-Y h:i:s A')
                        : 'N/A',
                ],

                'customer' => [
                    'first_name' => $return->user->first_name,
                    'last_name' => $return->user->last_name,
                    'email' => $return->user->email,
                    'avatar' => $return->user->image,
                ],

                'address' => $order->address,

                'items' => $return->items->map(function ($item) {
                    $originalPrice = (float) ($item->orderItem->productVariant->product->price ?? 0);
                    $finalPrice = (float) ($item->orderItem->price ?? 0);

                    return [
                        'return_item_id' => $item->id,
                        'quantity' => $item->quantity,
                        'reason' => $item->reason,
                        'condition' => $item->condition,
                        'restocked' => (bool) $item->restocked,
                        'replacement_variant_id' => $item->replacement_variant_id,
                        'exchange_quantity' => $item->exchange_quantity,
                        'exchange_price_difference' => $item->exchange_price_difference,
                        'discount' => round(max(0, $originalPrice - $finalPrice), 2),
                        'subtotal' => round($finalPrice * $item->quantity, 2),
                        'product' => [
                            'product_id' => $item->orderItem->productVariant->product->id ?? null,
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

    // Customer: Show Return
    // public function show($id)
    // {
    //     $return = ReturnOrder::with([
    //         'user',
    //         'items.orderItem.productVariant.product',
    //         'items.replacementVariant.product',
    //         'order.payment',
    //         'refund',
    //         'exchange',
    //     ])
    //         ->where('user_id', Auth::id())
    //         ->find($id);

    //     if (!$return) {
    //         return response()->json([
    //             'message' => 'Return order not found.',
    //         ], 404);
    //     }

    //     return response()->json([
    //         'message' => 'Retrieved return order details successfully',
    //         'data' => [
    //             'id' => $return->id,
    //             'order_number' => $return->order->order_number,
    //             'status' => $return->status,
    //             'return_type' => $return->return_type,
    //             'approved_at' => $return->approved_at->format('d-m-Y g:i:s A'),
    //             'received_at' => $return->received_at->format('d-m-Y g:i:s A'),
    //             'inspected_at' => $return->inspected_at->format('d-m-Y g:i:s A'),
    //             'completed_at' => $return->completed_at->format('d-m-Y g:i:s A'),
    //             'refund' => $return->refund ? [
    //                 'id' => $return->refund->id,
    //                 'amount' => $return->refund->amount,
    //                 'method' => $return->refund->method,
    //                 'provider' => $return->refund->provider,
    //                 'status' => $return->refund->status,
    //                 'reference' => $return->refund->refund_reference,
    //                 'processed_at' => $return->refund->processed_at?->format('d-m-Y g:i:s A'),
    //             ] : null,

    //             'exchange' => $return->exchange ? [
    //                 'id' => $return->exchange->id,
    //                 'status' => $return->exchange->status,
    //                 'price_difference' => $return->exchange->price_difference,
    //                 'tracking_number' => $return->exchange->tracking_number,
    //                 'carrier' => $return->exchange->carrier,
    //                 'processing_at' => $return->exchange->processing_at?->format('d-m-Y g:i:s A'),
    //                 'shipped_at' => $return->exchange->shipped_at?->format('d-m-Y g:i:s A'),
    //                 'received_at' => $return->exchange->received_at?->format('d-m-Y g:i:s A'),
    //             ] : null,

    //             'tracking_number' => $return->tracking_number,
    //             'carrier' => $return->carrier,

    //             'payment_status' => optional($return->order->payment)->status ?? 'N/A',
    //             'payment_method' => optional($return->order->payment)->payment_method ?? 'N/A',
    //             'shipping_fee' => $return->order->shipping_fee,
    //             'total_price' => $return->order->total_price,

    //             'customer' => [
    //                 'first_name' => $return->user->first_name,
    //                 'last_name' => $return->user->last_name,
    //                 'email' => $return->user->email,
    //                 'avatar' => $return->user->image,
    //             ],

    //             'shipping_address' => $return->order->address,

    //             'items' => $return->items->map(function ($item) {
    //                 return [
    //                     'id' => $item->id,
    //                     'product_name' => $item->orderItem->product_name,
    //                     'price' => $item->orderItem->price,
    //                     'quantity' => $item->quantity,
    //                     'subtotal' => number_format((float) $item->orderItem->price * $item->quantity, 2, '.', ''),
    //                     'size' => $item->orderItem->size,
    //                     'color' => $item->orderItem->color,
    //                     'reason' => $item->reason,
    //                     'condition' => $item->condition,
    //                     'restocked' => (bool) $item->restocked,
    //                     'replacement_variant_id' => $item->replacement_variant_id,
    //                     'exchange_quantity' => $item->exchange_quantity,
    //                     'exchange_price_difference' => $item->exchange_price_difference,
    //                     'replacement_variant' => $item->replacementVariant ? [
    //                         'id' => $item->replacementVariant->id,
    //                         'unit_price' => (float) ($item->replacementVariant->product->price ?? 0),
    //                         'stock' => $item->replacementVariant->stock,
    //                     ] : null,
    //                     'product_image' => $item->orderItem->product_image,
    //                 ];
    //             }),
    //         ],
    //     ]);
    // }
    // public function show($id)
    // {
    //     $return = ReturnOrder::with([
    //         'user',
    //         'items.orderItem.variant.product',
    //         'items.replacementVariant.product',
    //         'order.items.variant.product',
    //         'order.payment',
    //         'order.address',
    //         'order.user',
    //         'refund',
    //         'exchange',
    //     ])
    //         ->where('user_id', Auth::id())
    //         ->find($id);

    //     if (!$return) {
    //         return response()->json([
    //             'message' => 'Return order not found.',
    //         ], 404);
    //     }

    //     $order = $return->order;

    //     // Standard Order Calculations
    //     $shipping = (float) ($order->shipping_fee ?? '');

    //     $originalSubtotal = $order->items->sum(function ($item) {
    //         return (float) ($item->variant->product->price ?? 0) * $item->quantity;
    //     });

    //     $finalSubtotal = $order->items->sum(function ($item) {
    //         return (float) $item->price * $item->quantity;
    //     });

    //     $productDiscount = max(0, $originalSubtotal - $finalSubtotal);
    //     $couponDiscount = (float) ($order->coupon_discount ?? 0);
    //     $totalPayment = max(0, ($finalSubtotal - $couponDiscount) + $shipping);

    //     return response()->json([
    //         'message' => 'Retrieved return order details successfully',
    //         'data' => [
    //             'id' => $return->id,
    //             'return_number' => $return->return_number ?? null,
    //             'status' => $return->status,
    //             'return_type' => $return->items()->first()?->return_type,
    //             'tracking_number' => $return->tracking_number,
    //             'carrier' => $return->carrier,
    //             'timestamps' => [
    //                 'approved_at' => $return->approved_at?->format('d-m-Y h:i:s A'),
    //                 'received_at' => $return->received_at?->format('d-m-Y h:i:s A'),
    //                 'inspected_at' => $return->inspected_at?->format('d-m-Y h:i:s A'),
    //                 'completed_at' => $return->completed_at?->format('d-m-Y h:i:s A'),
    //                 'created_at' => $return->created_at?->format('d-m-Y'),
    //                 'time' => $return->created_at?->format('h:i:s A'),
    //             ],

    //             // Refund Details
    //             'refund' => $return->refund ? [
    //                 'id' => $return->refund->id,
    //                 'amount' => number_format((float) $return->refund->amount, 2, '.', ''),
    //                 'method' => $return->refund->method,
    //                 'provider' => $return->refund->provider,
    //                 'status' => $return->refund->status,
    //                 'reference' => $return->refund->refund_reference,
    //                 'processed_at' => $return->refund->processed_at?->format('d-m-Y h:i:s A'),
    //             ] : null,

    //             // Exchange Details
    //             'exchange' => $return->exchange ? [
    //                 'id' => $return->exchange->id,
    //                 'status' => $return->exchange->status,
    //                 'price_difference' => number_format((float) $return->exchange->price_difference, 2, '.', ''),
    //                 'tracking_number' => $return->exchange->tracking_number,
    //                 'carrier' => $return->exchange->carrier,
    //                 'processing_at' => $return->exchange->processing_at?->format('d-m-Y h:i:s A'),
    //                 'shipped_at' => $return->exchange->shipped_at?->format('d-m-Y h:i:s A'),
    //                 'received_at' => $return->exchange->received_at?->format('d-m-Y h:i:s A'),
    //             ] : null,

    //             // Order payload formatted identical to formatOrder()
    //             'order' => [
    //                 'order_id' => $order->id,
    //                 'order_number' => $order->order_number,
    //                 'status' => $order->status,
    //                 'original_price' => number_format((float) $originalSubtotal, 2, '.', ''),
    //                 'product_discount' => number_format((float) $productDiscount, 2, '.', ''),
    //                 'sub_total_price' => number_format((float) $finalSubtotal, 2, '.', ''),
    //                 'coupon_discount' => number_format((float) $couponDiscount, 2, '.', ''),
    //                 'shipping_fee' => number_format((float) $shipping, 2, '.', ''),
    //                 'total_price' => number_format((float) $totalPayment, 2, '.', ''),
    //                 'save' => number_format((float) ($productDiscount + $couponDiscount), 2, '.', ''),
    //                 'coupon' => [
    //                     'code' => $order->coupon_code,
    //                     'discount' => $couponDiscount,
    //                 ],
    //                 'created_at' => $order->created_at->format('d-m-Y'),
    //                 'time' => $order->created_at->format('h:i:s A'),
    //                 'payment' => [
    //                     'method' => $order->payment->payment_method ?? null,
    //                     'status' => $order->payment->status ?? null,
    //                     'reference' => $order->payment->payment_reference ?? null,
    //                     'paid_at' => $order->payment?->paid_at?->format('d-m-Y h:i:s A'),
    //                 ],
    //                 'address' => [
    //                     'first_name' => $order->address->first_name ?? null,
    //                     'last_name' => $order->address->last_name ?? null,
    //                     'address_line' => $order->address->address_line ?? null,
    //                     'city' => $order->address->city ?? null,
    //                     'province' => $order->address->province ?? null,
    //                     'phone' => $order->address->phone ?? $order->user->contact ?? null,
    //                     'note' => $order->note,
    //                 ],
    //             ],

    //             // Customer Details
    //             'customer' => [
    //                 'first_name' => $return->user->first_name,
    //                 'last_name' => $return->user->last_name,
    //                 'email' => $return->user->email,
    //                 'avatar' => $return->user->image,
    //             ],

    //             // Returned Items formatted similarly to Order Items
    //             'return_items' => $return->items->map(function ($item) {
    //                 $orderItem = $item->orderItem;
    //                 $originalPrice = (float) ($orderItem->variant->product->price ?? 0);
    //                 $finalPrice = (float) $orderItem->price;

    //                 return [
    //                     'id' => $item->id,
    //                     'order_item_id' => $item->order_item_id,
    //                     'quantity' => $item->quantity,
    //                     'save' => number_format((float) max(0, $originalPrice - $finalPrice), 2, '.', ''),
    //                     'subtotal' => number_format((float) ($finalPrice * $item->quantity), 2, '.', ''),
    //                     'reason' => $item->reason,
    //                     'condition' => $item->condition,
    //                     'restocked' => (bool) $item->restocked,
    //                     'exchange_quantity' => $item->exchange_quantity,
    //                     'exchange_price_difference' => number_format((float) $item->exchange_price_difference, 2, '.', ''),
    //                     'item_details' => [
    //                         'id' => $orderItem->product_variant_id,
    //                         'size' => $orderItem->size,
    //                         'color' => $orderItem->color,
    //                         'price' => number_format($originalPrice, 2, '.', ''),
    //                         'discount_price' => number_format($finalPrice, 2, '.', ''),
    //                         'product' => [
    //                             'id' => $orderItem->variant->product->id ?? null,
    //                             'name' => $orderItem->product_name,
    //                             'slug' => $orderItem->variant->product->slug ?? null,
    //                             'image' => $orderItem->product_image ?? $orderItem->variant->product->image ?? null,
    //                         ],
    //                     ],
    //                     'replacement_variant' => $item->replacementVariant ? [
    //                         'id' => $item->replacementVariant->id,
    //                         'unit_price' => number_format((float) ($item->replacementVariant->product->price ?? 0), 2, '.', ''),
    //                         'stock' => $item->replacementVariant->stock,
    //                     ] : null,
    //                 ];
    //             }),
    //         ],
    //     ]);
    // }
    public function show($id)
    {
        $return = ReturnOrder::with([
            'user',
            'items.orderItem.variant.product',
            'items.replacementVariant.product',
            'order.items.variant.product',
            'order.payment',
            'order.address',
            'order.user',
            'refund',
            'exchange',
        ])
            ->where('user_id', Auth::id())
            ->find($id);

        if (!$return) {
            return response()->json([
                'message' => 'Return order not found.',
            ], 404);
        }

        $order = $return->order;

        // Standard Order Calculations
        $shipping = (float) ($order->shipping_fee ?? 0);

        $originalSubtotal = $order->items->sum(function ($item) {
            return (float) ($item->variant->product->price ?? 0) * $item->quantity;
        });

        $finalSubtotal = $order->items->sum(function ($item) {
            return (float) $item->price * $item->quantity;
        });

        $productDiscount = max(0, $originalSubtotal - $finalSubtotal);
        $couponDiscount = (float) ($order->coupon_discount ?? 0);
        $totalPayment = max(0, ($finalSubtotal - $couponDiscount) + $shipping);

        return response()->json([
            'message' => 'Retrieved return order details successfully',
            'data' => [
                'id' => $return->id,
                'return_number' => $return->return_number ?? null,
                'status' => $return->status,
                // Fixed: Use collection access instead of relationship method query
                'return_type' => $return->items->first()?->return_type,
                'tracking_number' => $return->tracking_number,
                'carrier' => $return->carrier,
                'timestamps' => [
                    'approved_at' => $return->approved_at?->format('d-m-Y h:i:s A'),
                    'received_at' => $return->received_at?->format('d-m-Y h:i:s A'),
                    'inspected_at' => $return->inspected_at?->format('d-m-Y h:i:s A'),
                    'completed_at' => $return->completed_at?->format('d-m-Y h:i:s A'),
                    'created_at' => $return->created_at?->format('d-m-Y'),
                    'time' => $return->created_at?->format('h:i:s A'),
                ],

                // Refund Details
                'refund' => $return->refund ? [
                    'id' => $return->refund->id,
                    'amount' => number_format((float) $return->refund->amount, 2, '.', ''),
                    'method' => $return->refund->method,
                    'provider' => $return->refund->provider,
                    'status' => $return->refund->status,
                    'reference' => $return->refund->refund_reference,
                    'processed_at' => $return->refund->processed_at?->format('d-m-Y h:i:s A'),
                ] : null,

                // Exchange Details
                'exchange' => $return->exchange ? [
                    'id' => $return->exchange->id,
                    'status' => $return->exchange->status,
                    'price_difference' => number_format((float) $return->exchange->price_difference, 2, '.', ''),
                    'tracking_number' => $return->tracking_number,
                    'carrier' => $return->carrier,
                    'processing_at' => $return->exchange->processing_at?->format('d-m-Y h:i:s A'),
                    'shipped_at' => $return->exchange->shipped_at?->format('d-m-Y h:i:s A'),
                    'received_at' => $return->exchange->received_at?->format('d-m-Y h:i:s A'),
                ] : null,

                // Order payload
                'order' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'original_price' => number_format((float) $originalSubtotal, 2, '.', ''),
                    'product_discount' => number_format((float) $productDiscount, 2, '.', ''),
                    'sub_total_price' => number_format((float) $finalSubtotal, 2, '.', ''),
                    'coupon_discount' => number_format((float) $couponDiscount, 2, '.', ''),
                    'shipping_fee' => number_format((float) $shipping, 2, '.', ''),
                    'total_price' => number_format((float) $totalPayment, 2, '.', ''),
                    'save' => number_format((float) ($productDiscount + $couponDiscount), 2, '.', ''),
                    'coupon' => [
                        'code' => $order->coupon_code,
                        'discount' => $couponDiscount,
                    ],
                    'created_at' => $order->created_at?->format('d-m-Y'),
                    'time' => $order->created_at?->format('h:i:s A'),
                    'payment' => [
                        'method' => $order->payment?->payment_method,
                        'status' => $order->payment?->status,
                        'reference' => $order->payment?->payment_reference,
                        'paid_at' => $order->payment?->paid_at?->format('d-m-Y h:i:s A'),
                    ],
                    'address' => [
                        'first_name' => $order->address?->first_name,
                        'last_name' => $order->address?->last_name,
                        'address_line' => $order->address?->address_line,
                        'city' => $order->address?->city,
                        'province' => $order->address?->province,
                        'phone' => $order->address?->phone ?? $order->user?->contact,
                        'note' => $order->note,
                    ],
                ],

                // Customer Details
                'customer' => [
                    'first_name' => $return->user?->first_name,
                    'last_name' => $return->user?->last_name,
                    'email' => $return->user?->email,
                    'avatar' => $return->user?->image,
                ],

                // Returned Items
                'return_items' => $return->items->map(function ($item) {
                    $orderItem = $item->orderItem;
                    $originalPrice = (float) ($orderItem?->variant?->product?->price ?? 0);
                    $finalPrice = (float) ($orderItem?->price ?? 0);

                    return [
                        'id' => $item->id,
                        'order_item_id' => $item->order_item_id,
                        'quantity' => $item->quantity,
                        // Fixed: Multiplied savings by item quantity for accurate item-level savings
                        'save' => number_format((float) max(0, $originalPrice - $finalPrice) * $item->quantity, 2, '.', ''),
                        'subtotal' => number_format((float) ($finalPrice * $item->quantity), 2, '.', ''),
                        'reason' => $item->reason,
                        'condition' => $item->condition,
                        'restocked' => (bool) $item->restocked,
                        'exchange_quantity' => $item->exchange_quantity,
                        'exchange_price_difference' => number_format((float) $item->exchange_price_difference, 2, '.', ''),
                        'item_details' => [
                            'id' => $orderItem?->product_variant_id,
                            'size' => $orderItem?->size,
                            'color' => $orderItem?->color,
                            'price' => number_format($originalPrice, 2, '.', ''),
                            'discount_price' => number_format($finalPrice, 2, '.', ''),
                            'product' => [
                                'id' => $orderItem?->variant?->product?->id,
                                'name' => $orderItem?->product_name,
                                'slug' => $orderItem?->variant?->product?->slug,
                                'image' => $orderItem?->product_image ?? $orderItem?->variant?->product?->image,
                            ],
                        ],
                        'replacement_variant' => $item->replacementVariant ? [
                            'id' => $item->replacementVariant->id,
                            'size' => $item->replacementVariant->size ?? null,
                            'color' => $item->replacementVariant->color ?? null ,
                            'image'=> $item->replacementVariant->images()->first()->image ?? null,
                            'unit_price' => number_format((float) ($item->replacementVariant->product->price ?? 0), 2, '.', ''),
                            'stock' => $item->replacementVariant->stock,
                        ] : null,
                    ];
                }),
            ],
        ]);
    }
    // Customer: Submit Return Request
    public function store(Request $request)
    {
        $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct', 'exists:order_items,id'],
            'items.*.return_type' => ['required', Rule::in([ReturnItem::TYPE_REFUND, ReturnItem::TYPE_EXCHANGE])],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['required', 'string'],
            'items.*.replacement_variant_id' => ['nullable', 'required_if:items.*.return_type,' . ReturnItem::TYPE_EXCHANGE, 'integer', 'exists:product_variants,id'],
            'items.*.exchange_quantity' => ['nullable', 'integer', 'min:1'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found for this user.'], 404);
        }

        if ($order->status !== 'completed') {
            return response()->json(['message' => 'Only completed orders can be returned.'], 400);
        }

        if (!$order->completed_at) {
            return response()->json(['message' => 'Order completion date not found.'], 400);
        }

        if ($order->completed_at->copy()->addDays(self::RETURN_WINDOW_DAYS)->isPast()) {
            return response()->json(['message' => 'Return period expired (' . self::RETURN_WINDOW_DAYS . ' days only).'], 400);
        }

        try {
            $return = DB::transaction(function () use ($request, $order) {
                $orderItemIds = collect($request->items)->pluck('order_item_id')->unique();

                $orderItems = OrderItem::where('order_id', $order->id)
                    ->whereIn('id', $orderItemIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $returnNumber = 'RET-' . date('Ymd') . '-' . strtoupper(Str::random(4));
                $return = ReturnOrder::create([
                    'user_id' => Auth::id(),
                    'order_id' => $order->id,
                    'return_number' => $returnNumber,
                    'note' => $request->note,
                    'status' => ReturnOrder::STATUS_REQUESTED,
                ]);

                foreach ($request->items as $line) {
                    $orderItem = $orderItems->get($line['order_item_id']);

                    if (!$orderItem) {
                        throw new \RuntimeException("Order item #{$line['order_item_id']} does not belong to this order.");
                    }

                    $alreadyReturned = ReturnItem::where('order_item_id', $orderItem->id)
                        ->whereHas('returnOrder', function ($query) {
                            $query->whereNotIn('status', [
                                ReturnOrder::STATUS_REJECTED,
                                ReturnOrder::STATUS_CANCELLED,
                            ]);
                        })->sum('quantity');

                    $remaining = $orderItem->quantity - $alreadyReturned;

                    if ($line['quantity'] > $remaining) {
                        throw new \RuntimeException("Return quantity exceeds remaining returnable quantity for item #{$orderItem->id}.");
                    }

                    $returnItemData = [
                        'return_order_id' => $return->id,
                        'order_item_id' => $orderItem->id,
                        'quantity' => $line['quantity'],
                        'return_type' => $line['return_type'],
                        'reason' => $line['reason'],
                        'condition' => null,
                        'restocked' => false,
                        'replacement_variant_id' => null,
                        'exchange_quantity' => null,
                        'exchange_price_difference' => null,
                    ];

                    if ($line['return_type'] === ReturnItem::TYPE_EXCHANGE) {
                        if (empty($line['replacement_variant_id'])) {
                            throw new \RuntimeException('Replacement variant is required for exchange.');
                        }

                        $exchangeQuantity = $line['exchange_quantity'] ?? $line['quantity'];
                        $replacement = ProductVariant::lockForUpdate()->find($line['replacement_variant_id']);

                        if (!$replacement) {
                            throw new \RuntimeException('Replacement product variant not found.');
                        }

                        if ($replacement->stock < $exchangeQuantity) {
                            throw new \RuntimeException('Replacement product is out of stock.');
                        }

                        $originalAmount = (float) $orderItem->price * (int) $line['quantity'];
                        $replacementAmount = (float) $replacement->price * (int) $exchangeQuantity;
                        $difference = $replacementAmount - $originalAmount;

                        $returnItemData['replacement_variant_id'] = $replacement->id;
                        $returnItemData['exchange_quantity'] = $exchangeQuantity;
                        $returnItemData['exchange_price_difference'] = round($difference, 2);
                    }

                    ReturnItem::create($returnItemData);
                }

                return $return;
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
                        'return_order_id' => $return->id,
                        'url' => $upload->result->url,
                    ]);

                    if (!$return->image) {
                        $return->update(['image' => $upload->result->url]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Return image upload exception: ' . $e->getMessage());
            }
        }

        $return->load([
            'user',
            'items.orderItem.productVariant',
            'items.replacementVariant',
            'refund',
            'exchange',
        ]);

        $this->initializeNotification(
            $return->user,
            'Return Requested',
            'Your return request for order #' . $order->order_number . ' has been submitted.',
            'return order',
            'shopping-bag',
            "/account/returns/{$return->id}"
        );

        try {
            $adminEmail = config('mail.admin_email');
            Mail::to($adminEmail)->send(new AdminReturnRequestMail($return->load('user', 'items')));
            Mail::to($order->user->email)->send(new ReturnOrderRequestMail($return->load('items')));
        } catch (\Exception $e) {
            Log::error('Return request email failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Return request submitted successfully.',
            'data' => $return,
        ], 200);
    }

    // Customer: Cancel Return
    public function cancel($id)
    {
        $return = ReturnOrder::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$return) {
            return response()->json(['message' => 'Return order not found.'], 404);
        }

        if ($return->status !== ReturnOrder::STATUS_REQUESTED) {
            return response()->json(['message' => 'Only requested returns can be cancelled.'], 400);
        }

        $return->update(['status' => ReturnOrder::STATUS_CANCELLED]);

        return response()->json(['message' => 'Return request cancelled successfully.']);
    }

    // Admin Stage 1: Approve Return Request
    public function approve($id)
    {
        $this->ensureAdmin();

        try {
            $return = DB::transaction(function () use ($id) {
                $return = ReturnOrder::with([
                    'items.orderItem',
                    'user',
                    'order.payment',
                ])
                    ->lockForUpdate()
                    ->find($id);

                if (!$return) {
                    throw new \RuntimeException('Return order not found.');
                }

                if ($return->status !== ReturnOrder::STATUS_REQUESTED) {
                    throw new \RuntimeException('Return request is not in requested status.');
                }

                if ($return->items->isEmpty()) {
                    throw new \RuntimeException('Return request has no items.');
                }

                $hasRefund = $return->items->contains('return_type', ReturnItem::TYPE_REFUND);
                $hasExchange = $return->items->contains('return_type', ReturnItem::TYPE_EXCHANGE);

                $return->update([
                    'status' => ReturnOrder::STATUS_APPROVED,
                    'approved_at' => now(),
                ]);

                if ($hasRefund) {
                    $refundAmount = $this->RefundService->calculateRefundAmount($return);

                    Refund::updateOrCreate(
                        ['return_order_id' => $return->id],
                        [
                            'order_id' => $return->order_id,
                            'user_id' => $return->user_id,
                            'payment_id' => $return->order->payment?->id,
                            'amount' => $refundAmount,
                            'method' => null,
                            'provider' => null,
                            'status' => Refund::STATUS_PENDING,
                            'refund_reference' => 'REF-' . strtoupper(uniqid()),
                        ]
                    );
                }

                if ($hasExchange) {
                    $priceDifference = $return->items
                        ->where('return_type', ReturnItem::TYPE_EXCHANGE)
                        ->sum(function ($item) {
                            return (float) ($item->exchange_price_difference ?? 0);
                        });

                    Exchange::updateOrCreate(
                        ['return_order_id' => $return->id],
                        [
                            'order_id' => $return->order_id,
                            'user_id' => $return->user_id,
                            'status' => Exchange::STATUS_PROCESSING,
                            'price_difference' => round($priceDifference, 2),
                        ]
                    );
                }

                return $return;
            });

            $this->initializeNotification(
                $return->user,
                'Return Approved',
                'Your return request for order #' . $return->order->order_number . ' has been approved. Please ship the item(s) back.',
                'return order',
                'shopping-bag',
                "/account/returns/{$return->id}"
            );

            try {
                broadcast(new OrderStatusUpdated($return->order_id, 'return_approved', 'unpaid', true));
            } catch (\Exception $e) {
                Log::error('Broadcast failed: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Return approved successfully.',
                'data' => $return->fresh(['refund', 'exchange', 'items']),
            ]);
        } catch (\Exception $e) {
            Log::error('Approve return failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error approving return request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Admin Stage 2: Mark Package Received at Warehouse
    public function markReceived(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->validate([
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'carrier' => ['nullable', 'string', 'max:100'],
        ]);

        $return = ReturnOrder::with(['user', 'order'])->find($id);

        if (!$return) {
            return response()->json(['message' => 'Return order not found.'], 404);
        }

        if ($return->status !== ReturnOrder::STATUS_APPROVED) {
            return response()->json(['message' => 'Only approved return requests can be marked as received.'], 400);
        }

        $return->update([
            'status' => ReturnOrder::STATUS_RECEIVED,
            'received_at' => now(),
            'tracking_number' => $request->tracking_number ?? $return->tracking_number,
            'carrier' => $request->carrier ?? $return->carrier,
        ]);

        $this->initializeNotification(
            $return->user,
            'Package Received',
            'We have received your returned package for order #' . $return->order->order_number . '. It will now undergo quality inspection.',
            'return order',
            'shopping-bag',
            "/account/returns/{$return->id}"
        );

        return response()->json([
            'message' => 'Return order marked as received successfully.',
            'data' => $return->fresh(),
        ]);
    }

    // Admin Stage 3: Inspect Items (Quality Control & Restocking)
    public function inspect(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.return_item_id' => ['required', 'exists:return_items,id'],
            'items.*.condition' => ['required', Rule::in(self::CONDITIONS)],
        ]);

        try {
            $return = DB::transaction(function () use ($request, $id) {
                $return = ReturnOrder::with(['items.orderItem.productVariant', 'user', 'order'])
                    ->lockForUpdate()
                    ->find($id);

                if (!$return) {
                    throw new \RuntimeException('Return order not found.');
                }

                if ($return->status !== ReturnOrder::STATUS_RECEIVED) {
                    throw new \RuntimeException('Return must be received before inspection.');
                }

                $conditionMap = collect($request->items)->keyBy('return_item_id');

                foreach ($return->items as $item) {
                    $data = $conditionMap->get($item->id);

                    if (!$data) {
                        throw new \RuntimeException("Condition for return item #{$item->id} is required.");
                    }

                    $condition = $data['condition'];

                    $item->update([
                        'condition' => $condition,
                    ]);

                    // Restock only good items
                    if (
                        $condition === 'good'
                        && !$item->restocked
                        && $item->orderItem
                        && $item->orderItem->productVariant
                    ) {
                        $item->orderItem->productVariant->increment('stock', $item->quantity);

                        $item->update([
                            'restocked' => true,
                        ]);
                    }
                }

                $return->update([
                    'status' => ReturnOrder::STATUS_INSPECTED,
                    'inspected_at' => now(),
                ]);

                return $return;
            });

            $this->initializeNotification(
                $return->user,
                'Return Inspected',
                'Your returned item for order #' . $return->order->order_number . ' has been inspected.',
                'return order',
                'shopping-bag',
                "/account/returns/{$return->id}"
            );

            return response()->json([
                'message' => 'Return inspected successfully.',
                'data' => $return->fresh(['items', 'refund', 'exchange']),
            ]);
        } catch (\Exception $e) {
            Log::error('Return inspection failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to inspect return.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Admin Stage 4: Mark Complete (Finalize Refund & Exchange Execution)
    public function markCompleted(Request $request, $id)
    {
        $this->ensureAdmin();

        try {
            $return = DB::transaction(function () use ($id) {
                $return = ReturnOrder::with(['refund', 'exchange', 'user', 'order'])
                    ->lockForUpdate()
                    ->find($id);

                if (!$return) {
                    throw new \RuntimeException('Return order not found.');
                }

                if ($return->status !== ReturnOrder::STATUS_INSPECTED) {
                    throw new \RuntimeException('Only inspected return requests can be completed.');
                }

                if ($return->refund && $return->refund->status === Refund::STATUS_PENDING) {
                    $return->refund->update([
                        'status' => Refund::STATUS_COMPLETED,
                        'processed_at' => now(),
                    ]);
                }

                if ($return->exchange && $return->exchange->status === Exchange::STATUS_PROCESSING) {
                    $return->exchange->update([
                        'status' => Exchange::STATUS_RECEIVED,
                        'received_at' => now(),
                    ]);
                }

                $return->update([
                    'status' => ReturnOrder::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);

                return $return;
            });

            $this->initializeNotification(
                $return->user,
                'Return Completed',
                'Your return process for order #' . $return->order->order_number . ' has been completed.',
                'return order',
                'shopping-bag',
                "/account/returns/{$return->id}"
            );

            return response()->json([
                'message' => 'Return order marked as completed successfully.',
                'data' => $return->fresh(['refund', 'exchange']),
            ]);
        } catch (\Exception $e) {
            Log::error('Complete return failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error completing return order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Admin: Reject Return Request
    public function reject(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $return = ReturnOrder::with(['user', 'order'])->find($id);

        if (!$return) {
            return response()->json(['message' => 'Return order not found.'], 404);
        }

        if ($return->status !== ReturnOrder::STATUS_REQUESTED) {
            return response()->json(['message' => 'Only requested returns can be rejected.'], 400);
        }

        $return->update([
            'status' => ReturnOrder::STATUS_REJECTED,
            'rejected_at' => now(),
            'admin_note' => $request->admin_note,
        ]);

        $this->initializeNotification(
            $return->user,
            'Return Rejected',
            'Your return request for order #' . $return->order->order_number . ' has been rejected.',
            'return order',
            'shopping-bag',
            "/account/returns/{$return->id}"
        );

        return response()->json(['message' => 'Return rejected successfully.']);
    }
}