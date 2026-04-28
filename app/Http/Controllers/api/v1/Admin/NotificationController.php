<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function notifications()
    {
        $orders = Order::with(['items.product', 'user'])
            ->where('is_seen', false)
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'message' => 'New order #' . $order->id,
                    'first_name' => $order->user->first_name,
                    'last_name' => $order->user->last_name,
                    'total' => $order->total_price,
                    'products' => $order->items->map(function ($item) {
                        return [
                            'name' => $item->product_name ?? ($item->product->name ?? 'Unknown'),
                            'qty' => $item->quantity,
                            'size' => $item->size,
                            'color' => $item->color,
                        ];
                    })
                ];
            });

        return response()->json([
            'orders' => $orders
        ]);
    }

    public function markAsSeen($id)
    {
        Order::where('id', $id)->update([
            'is_seen' => true
        ]);

        return response()->json(['success' => true]);
    }
}
