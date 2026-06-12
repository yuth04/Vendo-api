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

        $orders = Order::with(['user', 'items'])->latest()->get();
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
                'id' => $order->id,
                'order_number' => $order->order_number,
                'date' => $order->created_at->format('d-m-Y h:i:s A'),
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
   
        $returns = ReturnOrder::with(['user', 'items'])->orderBy('id', 'asc')->latest()->get();
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
                'reason' => $returnOrder->reason,
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
    // Return Details
    public function returnDetails($id)
    {
        // if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'staff') {
        //     return response()->json([
        //         'message' => 'Only super-admin can access order details'
        //     ], 403);
        // }
        $returnOrder = ReturnOrder::with([
            'user',
            'items.orderItem.product',
            'order.payment',
            'order.address',
        ])->find($id);
        if (!$returnOrder) {
            return response()->json([
                'message' => 'Return order not found'
            ], 404);
        }
        return response()->json([
            'message' => 'Retrieved return order details successfully',
            'data' => [
                'id' => $returnOrder->id,
                'order_number' => $returnOrder->order->order_number,
                'date' => $returnOrder->created_at->format('d-m-Y h:i:s A'),
                'reason' => $returnOrder->reason,
                'note' => $returnOrder->note,
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
}
