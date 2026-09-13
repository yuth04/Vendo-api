<?php

namespace App\Http\Controllers\api\v1\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private function timeAgoShort($time)
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

    /**
     * Get recent unseen order notifications formatted for redirection.
     */
    public function notifications()
    {
        $orders = Order::with('user')
            ->where('is_seen', false)
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($order) {
                $customerName = trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? ''));

                return [
                    'id' => $order->id,
                    'title' => 'New Order Received',
                    'message' => 'Order #' . $order->id . ' placed by ' . ($customerName ?: 'Customer'),
                    'total' => $order->total_price,
                    'url' => '/admin/orders/' . $order->id,
                    'is_seen' => (bool) $order->is_seen,
                    'time_ago' => $this->timeAgoShort($order->created_at),
                    'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
                ];
            });

        return response()->json([
            'orders' => $orders,
            'unseen_count' => $orders->count(),
        ]);
    }

    /**
     * Mark an order as seen when clicked by the admin.
     */
    public function markAsSeen($id)
    {
        $order = Order::findOrFail($id);
        $order->update(['is_seen' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Order marked as seen.',
            'redirect_url' => '/admin/orders/' . $id
        ]);
    }

    /**
     * Get system notifications using your custom Notification model.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $notifications = Notification::where('user_id', $userId)
            ->latest()
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'url' => $notification->url,
                    'icon' => $notification->icon,
                    'is_read' => (bool) $notification->is_read,
                    'time_ago' => $this->timeAgoShort($notification->created_at),
                    'created_at' => Carbon::parse($notification->created_at)->format('d-m-Y g:i:s A'),
                ];
            });

        $unreadCount = Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'message' => 'Notifications retrieved successfully',
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notification marked as read',
            'redirect_url' => $notification->url
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    /**
     * Delete a specific notification.
     */
    public function destroy(Request $request, $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->delete();

        return response()->json(['message' => 'Notification deleted']);
    }
}