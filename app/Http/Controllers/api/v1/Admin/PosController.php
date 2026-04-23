<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class PosController extends Controller
{
    // Order History
    public function orderHistory()
    {
        $orders = Order::with(['user', 'items'])->latest()->get();
        $data = $orders->map(function ($order) {
            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'date' => $order->created_at->format('Y-m-d'),
                'first_name' => $order->user->first_name,
                'last_name' => $order->user->last_name,
                'email' => $order->user->email,
                'avatar'=> $order->user->image,
                'total_price' => $order->total_price,
                // 'total' => '$' . number_format($order->total_price, 0),
                'items_count' => $order->items->count(),
                'status' => $order->status,
                'payment' => $order->payment->status

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
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'date' => $order->created_at->format('Y-m-d H:i:s'),
                'status' => $order->status,
                'payment_status' => optional($order->payment)->status,
                'payment_method' => optional($order->payment)->payment_method,
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
                        'order_item_id' => $item->id,
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
}
