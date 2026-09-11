<?php

namespace App\Http\Controllers\api\v1\Orders;

use App\Events\OrderStatusUpdated;
use App\Events\ReturnStatusUpdated;
use App\Http\Controllers\Controller;
use App\Mail\AdminReturnRequestMail;
use App\Mail\ReturnOrderRequestMail;
use App\Models\Exchange;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDamage;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\ReturnImage;
use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use App\Services\ExchangeService;
use App\Services\RefundService;
use App\Services\SystemLogService;
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
    protected $ExchangeService;

    private const RETURN_WINDOW_DAYS = 7;

    private const CONDITIONS = [
        'good',
        'damaged',
    ];
    public function __construct(
        RefundService $refundService,
        ExchangeService $exchangeService,
        protected SystemLogService $systemLogService
    ) {
        $this->RefundService = $refundService;
        $this->ExchangeService = $exchangeService;
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
            'items.orderItem.variant.product',
            'items.replacementVariant.product',
            'user',
            'order.payment',
            'items.refund',
            'items.exchange',
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
            'items.orderItem.variant.product',
            'items.replacementVariant.product',
            'user',
            'items.refund',
            'items.exchange',
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

                // 'refund' => $return->refund ? [
                //     'id' => $return->refund->id,
                //     'amount' => $return->refund->amount,
                //     'method' => $return->refund->method,
                //     'provider' => $return->refund->provider,
                //     'status' => $return->refund->status,
                //     'reference' => $return->refund->refund_reference,
                //     'processed_at' => $return->refund->processed_at,
                // ] : null,

                // 'exchange' => $return->exchange ? [
                //     'id' => $return->exchange->id,
                //     'status' => $return->exchange->status,
                //     'price_difference' => $return->exchange->price_difference,
                //     'tracking_number' => $return->exchange->tracking_number,
                //     'carrier' => $return->exchange->carrier,
                //     'processing_at' => $return->exchange->processing_at?->format('d-m-Y g:i:s A'),
                //     'shipped_at' => $return->exchange->shipped_at?->format('d-m-Y g:i:s A'),
                //     'received_at' => $return->exchange->received_at?->format('d-m-Y g:i:s A'),
                // ] : null,

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
    public function show($id)
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
        ])
            ->where('user_id', Auth::id())
            ->find($id);

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
                'timestamps' => [
                    'approved_at' => $return->approved_at
                            ?->format('d-m-Y h:i:s A'),
                    'received_at' => $return->received_at
                            ?->format('d-m-Y h:i:s A'),
                    'inspected_at' => $return->inspected_at
                            ?->format('d-m-Y h:i:s A'),
                    'completed_at' => $return->completed_at
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
                            'tracking_number' =>
                                $exchange->tracking_number,
                            'carrier' =>
                                $exchange->carrier,
                            'processing_at' =>
                                $exchange->processing_at
                                        ?->format('d-m-Y h:i:s A'),

                            'shipped_at' =>
                                $exchange->shipped_at
                                        ?->format('d-m-Y h:i:s A'),

                            'received_at' =>
                                $exchange->received_at
                                        ?->format('d-m-Y h:i:s A'),
                        ] : null,

                        /*
                        |--------------------------------------------------------------------------
                        | Item Details
                        |--------------------------------------------------------------------------
                        */

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

                                'id' =>
                                    $orderItem?->variant?->product?->id,

                                'name' =>
                                    $orderItem?->product_name
                                    ?? $orderItem?->variant?->product?->name,

                                'slug' =>
                                    $orderItem?->variant?->product?->slug,

                                'image' =>
                                    $orderItem?->product_image
                                    ?? $orderItem?->variant?->product?->image,
                            ],
                        ],

                        /*
                        |--------------------------------------------------------------------------
                        | Replacement Variant
                        |--------------------------------------------------------------------------
                        */

                        'replacement_variant' =>
                            $item->replacementVariant ? [

                                'id' =>
                                    $item->replacementVariant->id,

                                'size' =>
                                    $item->replacementVariant->size,

                                'color' =>
                                    $item->replacementVariant->color,

                                'image' =>
                                    $replacementImage,

                                'unit_price' =>
                                    number_format(
                                        (float) (
                                            $item->replacementVariant
                                                ->product
                                                    ?->price ?? 0
                                        ),
                                        2,
                                        '.',
                                        ''
                                    ),

                                'stock' =>
                                    $item->replacementVariant->stock,

                            ] : null,
                    ];
                })->values(),
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
            'items.*.order_item_id' => [
                'required',
                'integer',
                'distinct',
                'exists:order_items,id',
            ],
            'items.*.return_type' => [
                'required',
                Rule::in([
                    ReturnItem::TYPE_REFUND,
                    ReturnItem::TYPE_EXCHANGE,
                ]),
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
            'items.*.reason' => [
                'required',
                'string',
            ],
            'items.*.replacement_variant_id' => [
                'nullable',
                'required_if:items.*.return_type,' . ReturnItem::TYPE_EXCHANGE,
                'integer',
                'exists:product_variants,id',
            ],
            'items.*.exchange_quantity' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'images' => [
                'nullable',
                'array',
                'max:6',
            ],
            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png',
                'max:2048',
            ],
        ]);
        $order = Order::where('id', $request->order_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found for this user.',
            ], 404);
        }
        // Check Order Status
        if ($order->status !== 'completed') {
            return response()->json([
                'message' => 'Only completed orders can be returned.',
            ], 400);
        }

        //  Check Completed Date
        if (!$order->completed_at) {
            return response()->json([
                'message' => 'Order completion date not found.',
            ], 400);
        }

        // Check Return Window
        if (
            $order->completed_at
                ->copy()
                ->addDays(self::RETURN_WINDOW_DAYS)
                ->isPast()
        ) {
            return response()->json([
                'message' =>
                    'Return period expired (' .
                    self::RETURN_WINDOW_DAYS .
                    ' days only).',
            ], 400);
        }
        try {
            $return = DB::transaction(function () use ($request, $order) {
                // Get Order Items
                $orderItemIds = collect($request->items)
                    ->pluck('order_item_id')
                    ->unique()
                    ->values();

                $orderItems = OrderItem::where('order_id', $order->id)
                    ->whereIn('id', $orderItemIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // Generate Return Number
                $returnNumber =
                    'RET-' .
                    date('Ymd') .
                    '-' .
                    strtoupper(Str::random(4));

                // Create Return Order
                $return = ReturnOrder::create([
                    'user_id' => Auth::id(),
                    'order_id' => $order->id,
                    'return_number' => $returnNumber,
                    'note' => $request->note,
                    'status' => ReturnOrder::STATUS_REQUESTED,
                ]);

                // Create Return Items
                foreach ($request->items as $line) {

                    $orderItem = $orderItems->get(
                        $line['order_item_id']
                    );
                    if (!$orderItem) {
                        throw new \RuntimeException(
                            "Order item #{$line['order_item_id']} does not belong to this order."
                        );
                    }
                    // Check Already Returned Quantity
                    $alreadyReturned = ReturnItem::where(
                        'order_item_id',
                        $orderItem->id
                    )
                        ->whereHas('returnOrder', function ($query) {
                            $query->whereNotIn('status', [
                                ReturnOrder::STATUS_REJECTED,
                                ReturnOrder::STATUS_CANCELLED,
                            ]);
                        })
                        ->sum('quantity');
                    $remaining =
                        $orderItem->quantity -
                        $alreadyReturned;

                    if ($line['quantity'] > $remaining) {
                        throw new \RuntimeException(
                            "Return quantity exceeds remaining returnable quantity for item #{$orderItem->id}."
                        );
                    }

                    // Return Item Data
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

                    //  Exchange Information
                    $replacement = null;
                    $exchangeQuantity = null;
                    $difference = 0;

                    if (
                        $line['return_type'] ===
                        ReturnItem::TYPE_EXCHANGE
                    ) {
                        if (empty($line['replacement_variant_id'])) {
                            throw new \RuntimeException(
                                'Replacement variant is required for exchange.'
                            );
                        }
                        $exchangeQuantity =
                            $line['exchange_quantity']
                            ?? $line['quantity'];

                        // Lock Replacement Variant
                        $replacement = ProductVariant::with('product')
                            ->lockForUpdate()
                            ->find(
                                $line['replacement_variant_id']
                            );

                        if (!$replacement) {
                            throw new \RuntimeException(
                                'Replacement product variant not found.'
                            );
                        }

                        // Check Replacement Stock
                        if ($replacement->stock < $exchangeQuantity) {
                            throw new \RuntimeException(
                                'Replacement product is out of stock.'
                            );
                        }
                        // Calculate Price Difference (via ExchangeService)
                        $difference = $this->ExchangeService->calculatePriceDifference(
                            $orderItem,
                            $replacement,
                            (int) $line['quantity'],
                            (int) $exchangeQuantity
                        );

                        $returnItemData[
                            'replacement_variant_id'
                        ] = $replacement->id;

                        $returnItemData[
                            'exchange_quantity'
                        ] = $exchangeQuantity;

                        $returnItemData[
                            'exchange_price_difference'
                        ] = $difference;
                    }
                    // Create Return Item
                    $returnItem = ReturnItem::create(
                        $returnItemData
                    );
                    // CREATE REFUND
                    if (
                        $returnItem->return_type ===
                        ReturnItem::TYPE_REFUND
                    ) {
                        $refundAmount =
                            (float) $orderItem->price *
                            (int) $returnItem->quantity;
                        Refund::create([
                            'order_id' => $order->id,
                            'return_order_id' => $return->id,
                            'return_item_id' => $returnItem->id,
                            'payment_id' => $order->payment?->id,
                            'user_id' => Auth::id(),
                            'amount' => round(
                                $refundAmount,
                                2
                            ),
                            'method' => 'khqr',
                            'provider' => 'bakong',
                            'status' => Refund::STATUS_PENDING,
                            'refund_reference' => null,
                            'processed_at' => null,
                            'failed_at' => null,
                            'failure_reason' => null,
                            'khqr_receiver_number' => null,
                            'khqr_account_name' => null,
                            'khqr_qr_code' => null,
                            'khqr_transaction_id' => null,
                        ]);
                    }
                    // CREATE EXCHANGE
                    if (
                        $returnItem->return_type ===
                        ReturnItem::TYPE_EXCHANGE
                    ) {
                        Exchange::create([
                            'return_order_id' => $return->id,
                            'return_item_id' => $returnItem->id,
                            'order_id' => $order->id,
                            'user_id' => Auth::id(),
                            'status' => Exchange::STATUS_PENDING,
                            'price_difference' => $difference,
                            'tracking_number' => null,
                            'carrier' => null,
                            'processing_at' => null,
                            'shipped_at' => null,
                            'received_at' => null,
                            'failed_at' => null,
                            'failure_reason' => null,
                        ]);
                    }
                }

                return $return;
            });

        } catch (\RuntimeException $e) {

            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
        // Upload Return Images to ImageKit
        if ($request->hasFile('images')) {
            try {
                $imageKit = new ImageKit(
                    config('imagekit.public_key'),
                    config('imagekit.private_key'),
                    config('imagekit.url_endpoint')
                );
                foreach ($request->file('images') as $file) {
                    $upload = $imageKit->upload([
                        'file' => fopen(
                            $file->getRealPath(),
                            'r'
                        ),
                        'fileName' =>
                            $file->getClientOriginalName(),
                        'folder' => '/return-images',
                    ]);
                    if (isset($upload->error)) {
                        Log::error(
                            'Return image upload failed: ' .
                            $upload->error->message
                        );
                        continue;
                    }
                    ReturnImage::create([
                        'return_order_id' => $return->id,
                        'url' => $upload->result->url,
                    ]);

                    if (!$return->image) {

                        $return->update([
                            'image' => $upload->result->url,
                        ]);
                    }
                }
            } catch (\Exception $e) {

                Log::error(
                    'Return image upload exception: ' .
                    $e->getMessage()
                );
            }
        }
        // Load Relationships
        $return->load([
            'user',
            'items.orderItem.productVariant',
            'items.replacementVariant',
            'items.refund',
            'items.exchange',
        ]);

        // Notification
        $this->initializeNotification(
            $return->user,
            'Return Requested',
            'Your return request for order #' .
            $order->order_number .
            ' has been submitted.',
            'return order',
            'shopping-bag',
            "/account/returns/{$return->id}"
        );

        // Send Emails
        try {

            $adminEmail = config('mail.admin_email');

            Mail::to($adminEmail)->send(
                new AdminReturnRequestMail(
                    $return->load('user', 'items')
                )
            );

            Mail::to($order->user->email)->send(
                new ReturnOrderRequestMail(
                    $return->load('items')
                )
            );

        } catch (\Exception $e) {

            Log::error(
                'Return request email failed: ' .
                $e->getMessage()
            );
        }

        return response()->json([
            'message' =>
                'Return request submitted successfully.',
            'data' => $return,
        ], 200);
    }
    // Customer: Cancel Return
    public function cancel($id)
    {
        $return = ReturnOrder::where('id', $id)
            ->where('user_id', Auth::id())
            ->with('items')
            ->first();

        if (!$return) {
            return response()->json([
                'message' => 'Return order not found.'
            ], 404);
        }

        if ($return->status !== ReturnOrder::STATUS_REQUESTED) {
            return response()->json([
                'message' => 'Only requested returns can be cancelled.'
            ], 400);
        }

        DB::transaction(function () use ($return) {

            // Cancel related refunds
            Refund::where('return_order_id', $return->id)
                ->where('status', Refund::STATUS_PENDING)
                ->update([
                    'status' => Refund::STATUS_FAILED,
                    'failure_reason' => 'Return request was cancelled by customer.',
                ]);

            // Cancel related exchanges
            Exchange::where('return_order_id', $return->id)
                ->where('status', Exchange::STATUS_PROCESSING)
                ->update([
                    'status' => Exchange::STATUS_FAILED,
                    'failure_reason' => 'Return request was cancelled by customer.',
                    'failed_at' => now(),
                ]);

            // Cancel return
            $return->update([
                'status' => ReturnOrder::STATUS_CANCELLED,
            ]);
        });

        return response()->json([
            'message' => 'Return request cancelled successfully.'
        ]);
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
                        [
                            'return_order_id' => $return->id,
                            'return_item_id' => $return->items->first()->id,
                        ],
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
                        [
                            'return_order_id' => $return->id,
                            'return_item_id' => $return->items->first()->id,
                        ],
                        [
                            'order_id' => $return->order_id,
                            'user_id' => $return->user_id,
                            'status' => Exchange::STATUS_PENDING,
                            'price_difference' => round($priceDifference, 2),
                            'processing_at' => null,
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
                broadcast(new ReturnStatusUpdated(
                    $return->id,
                    ReturnOrder::STATUS_APPROVED,
                    'Your return request for order #' .
                    $return->order->order_number .
                    ' has been approved. Please ship the item(s) back.'
                ));
            } catch (\Exception $e) {
                Log::error('Broadcast failed: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Return approved successfully.',
                'data' => $return->fresh(['items', 'items.refund', 'items.exchange']),
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
        broadcast(new ReturnStatusUpdated(
            $return->id,
            ReturnOrder::STATUS_RECEIVED,
            'Your returned package has been received.'
        ));
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
            'items.*.return_item_id' => [
                'required',
                'integer',
                'exists:return_items,id',
            ],
            'items.*.condition' => ['required', Rule::in(self::CONDITIONS),],
        ]);

        try {
            $return = DB::transaction(function () use ($request, $id) {
                $return = ReturnOrder::with(['items.orderItem.variant', 'user', 'order'])
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
                    // damage product
                    if ($condition === 'damaged' && $item->orderItem && $item->orderItem->variant) {
                        $product = $item->orderItem->variant->product;
                        if ($product) {
                            ProductDamage::create([
                                'product_id' => $product->id,
                                'user_id' => $return->user_id,
                                'reason' => 'Product returned damaged',
                                'quantity' => $item->quantity,
                                'status' => 'pending',
                            ]);
                        }
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
            broadcast(new ReturnStatusUpdated(
                $return->id,
                ReturnOrder::STATUS_INSPECTED,
                'Your returned item has been inspected.'
            ));
            return response()->json([
                'message' => 'Return inspected successfully.',
                'data' => $return->fresh(['items', 'items.refund', 'items.exchange']),
            ]);
        } catch (\Exception $e) {
            Log::error('Return inspection failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to inspect return.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    // Admin Stage 4: Mark Complete (Close return order once every item is done)
    public function completeReturn($id)
    {
        $this->ensureAdmin();

        try {
            $return = DB::transaction(function () use ($id) {

                $return = ReturnOrder::with([
                    'items.refund',
                    'items.exchange',
                    'user',
                    'order',
                ])
                    ->lockForUpdate()
                    ->find($id);

                if (!$return) {
                    throw new \RuntimeException('Return order not found.');
                }

                if ($return->status === ReturnOrder::STATUS_COMPLETED) {
                    throw new \RuntimeException('Return order is already completed.');
                }

                if (
                    !in_array(
                        $return->status,
                        [ReturnOrder::STATUS_RECEIVED, ReturnOrder::STATUS_INSPECTED],
                        true
                    )
                ) {
                    throw new \RuntimeException('Return must be received or inspected before it can be completed.');
                }

                if ($return->items->isEmpty()) {
                    throw new \RuntimeException('Return request has no items.');
                }
                $pendingItems = [];

                foreach ($return->items as $item) {

                    if ($item->return_type === ReturnItem::TYPE_REFUND) {

                        if (!$item->refund || $item->refund->status !== Refund::STATUS_COMPLETED) {
                            $pendingItems[] = "Return item #{$item->id} (refund) is not completed yet.";
                        }

                        continue;
                    }

                    if ($item->return_type === ReturnItem::TYPE_EXCHANGE) {

                        if (!$item->exchange || $item->exchange->status !== Exchange::STATUS_COMPLETED) {
                            $pendingItems[] = "Return item #{$item->id} (exchange) is not completed yet.";
                        }

                        continue;
                    }

                    $pendingItems[] = "Return item #{$item->id} has an unknown return type.";
                }

                if (!empty($pendingItems)) {
                    throw new \RuntimeException(implode(' ', $pendingItems));
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
            broadcast(new ReturnStatusUpdated(
                $return->id,
                ReturnOrder::STATUS_COMPLETED,
                'Your return has been completed.'
            ));
            return response()->json([
                'message' => 'Return order marked as completed successfully.',
                'data' => $return->fresh(['items.refund', 'items.exchange']),
            ]);
        } catch (\Exception $e) {
            Log::error('Complete return failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error completing return order.',
                'error' => $e->getMessage(),
            ], 400);
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
        broadcast(new ReturnStatusUpdated(
            $return->id,
            ReturnOrder::STATUS_REJECTED,
            'Your return has been rejected.'
        ));
        return response()->json(['message' => 'Return rejected successfully.']);
    }
}