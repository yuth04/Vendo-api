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
                'order_number' => $order->order_number,
                'data' => $order->created_at->format('Y-m-d'),
                'first_name' => $order->user->first_name,
                'last_name' => $order->user->last_name,
                'email' => $order->user->email,
                'total_price' => $order->total_price,
                // 'total' => '$' . number_format($order->total_price, 0),
                'items_count' => $order->items->count(),
                'status' => $order->status,
                'payment'=>$order->payment->status

            ];
        });
        return response()->json([
            'message' => 'Retrieved order history successfully',
            'data' => $data
        ], 200);
    }
}
