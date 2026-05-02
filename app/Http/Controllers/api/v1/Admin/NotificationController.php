<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    function timeAgoShort($time)
    {
        $time = Carbon::parse($time);
        $now = Carbon::now();

        $diffInSeconds = $now->diffInSeconds($time);
        $diffInMinutes = $now->diffInMinutes($time);
        $diffInHours = $now->diffInHours($time);
        $diffInDays = $now->diffInDays($time);

        if ($diffInSeconds < 60) {
            return 'just now';
        }

        if ($diffInMinutes < 60) {
            return "last {$diffInMinutes} min";
        }

        if ($diffInHours < 24) {
            return "last {$diffInHours}h";
        }

        return "last {$diffInDays}d";
    }
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
                    'time' => $order->time_ago,
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
