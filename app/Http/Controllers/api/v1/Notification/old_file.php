<?php

namespace App\Http\Controllers\api\v1\Notification;

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
    // Get the latest 10 unseen orders with their details
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
                    'message' => 'Order ID ' . $order->id,
                    'first_name' => $order->user->first_name,
                    'last_name' => $order->user->last_name,
                    'image' => $order->user->image,
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
    // Mark a specific order as seen
    public function markAsSeen($id)
    {
        Order::where('id', $id)->update([
            'is_seen' => true
        ]);

        return response()->json(['success' => true]);
    }
    // Get a specific order by ID
    public function getById($id)
    {
        $order = Order::with(['items.product', 'user'])
            ->where('is_seen', false)
            ->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'order' => [
                'id' => $order->id,
                'message' => 'Order ID ' . $order->id,
                'first_name' => $order->user->first_name,
                'last_name' => $order->user->last_name,
                'email' => $order->user->email,
                'image' => $order->user->image,
                'total' => $order->total_price,
                'time' => $order->time_ago,
                'products' => $order->items->map(function ($item) {
                    return [
                        'name' => $item->product_name ?? ($item->product->name ?? 'Unknown'),
                        'qty' => $item->quantity,
                        'size' => $item->size,
                        'color' => $item->color,
                        'image' => $item->product_image
                    ];
                })
            ]
        ]);
    }
    public function index()
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'data' => $notification->data,
                    'read' => !is_null($notification->read_at),
                    'created_at' => \Carbon\Carbon::parse($notification->created_at)
                        ->format('d-m-Y g:i:s A'),
                ];
            });

        return response()->json([
            'message' => 'Notifications retrieved successfully',
            'notifications' => $notifications,
            'unread_count' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
    // Mark a specific notification as read
    public function markAsRead($id)
    {
        auth()->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    }
    // Mark all notifications as read
    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read']);
    }
    // Delete a specific notification
    public function destroy($id)
    {
        auth()->user()->notifications()->findOrFail($id)->delete();
        
        return response()->json(['message' => 'Notification deleted']);
    }
}