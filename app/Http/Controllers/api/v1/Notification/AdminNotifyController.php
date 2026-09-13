<?php

namespace App\Http\Controllers\api\v1\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Http\Request;

class AdminNotifyController extends Controller
{
    /**
     * Get all admin notifications (new orders + system notifications), combined and sorted.
     */
    public function getAll()
    {
        $orderNotifications = Order::with('user')
            ->where('is_seen', false)
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($order) {
                $customerName = trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? ''));

                return [
                    'id' => $order->id,
                    'type' => 'order',
                    'title' => 'New Order Received',
                    'message' => 'Order #' . $order->id . ' placed by ' . ($customerName ?: 'Customer'),
                    'icon' => 'order',
                    'url' => '/admin/online-orders/' . $order->id,
                    'is_read' => (bool) $order->is_seen,
                    'created_at' => $order->created_at->format('M d, Y h:i A'),
                    'created_at_human' => $order->created_at->diffForHumans(),
                    'sort_time' => $order->created_at,
                ];
            });

        $systemNotifications = Notification::where('user_id', auth()->id())
            ->latest()
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'type' => $n->type ?? 'system',
                    'title' => $n->title,
                    'message' => $n->message,
                    'icon' => $n->icon,
                    'url' => $n->url,
                    'is_read' => (bool) $n->is_read,
                    'created_at' => $n->created_at->format('M d, Y h:i A'),
                    'created_at_human' => $n->created_at->diffForHumans(),
                    'sort_time' => $n->created_at,
                ];
            });

        $notifications = $orderNotifications
            ->concat($systemNotifications)
            ->sortByDesc('sort_time')
            ->values()
            ->map(function ($n) {
                unset($n['sort_time']);
                return $n;
            });

        return response()->json([
            'message' => $notifications->isEmpty()
                ? 'No notifications found'
                : 'Retrieved notifications successfully',
            'data' => $notifications,
        ]);
    }

    // unread (orders not seen + system notifications not read)
    public function unreadCount()
    {
        $orderCount = Order::where('is_seen', false)->count();

        $systemCount = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        return response()->json([
            'count' => $orderCount + $systemCount,
        ]);
    }

    // read (handles both order and system notifications by type)
    public function markAsRead(Request $request, $id)
    {
        $type = $request->query('type', 'system');

        if ($type === 'order') {
            $order = Order::findOrFail($id);

            if ($order->is_seen) {
                return response()->json(['message' => 'Notification already mark.']);
            }

            $order->update(['is_seen' => true]);

            return response()->json(['message' => 'Mark read successfully']);
        }

        $notification = Notification::where('user_id', auth()->id())->findOrFail($id);

        if ($notification->is_read) {
            return response()->json(['message' => 'Notification already mark.']);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Mark read successfully']);
    }

    // redirect to notification url
    public function redirect(Request $request, string $id)
    {
        $type = $request->query('type', 'system');

        if ($type === 'order') {
            $order = Order::findOrFail($id);

            if (!$order->is_seen) {
                $order->update(['is_seen' => true]);
            }

            return response()->json([
                'url' => '/admin/orders/' . $order->id,
            ]);
        }

        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (!$notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        return response()->json([
            'url' => $notification->url ?? null,
        ]);
    }

    // read all
    public function markAllAsRead()
    {
        Order::where('is_seen', false)->update(['is_seen' => true]);

        Notification::where('user_id', auth()->id())
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Success']);
    }

    // delete single notification (system notifications only — orders aren't deletable this way)
    public function delete($id)
    {
        $notification = Notification::where('user_id', auth()->id())
            ->where('id', $id)
            ->firstOrFail();

        $notification->delete();

        return response()->json(['message' => 'Notification deleted successfully']);
    }

    // Clear all notifications (system notifications only)
    public function clearAll()
    {
        $deleted = Notification::where('user_id', auth()->id())->delete();

        return response()->json([
            'message' => $deleted
                ? 'All notifications cleared successfully'
                : 'No notifications to clear',
        ]);
    }
}