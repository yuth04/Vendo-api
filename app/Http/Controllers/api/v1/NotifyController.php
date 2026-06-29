<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotifyController extends Controller
{
    public function getAll()
    {
        $notifications = Notification::where('user_id', auth()->id())->where('is_read', false)->latest()->get();
        return response()->json([
            'message' => $notifications->isEmpty()
                ? 'No notifications found'
                : 'Retrieved notifications successfully',
            'data' => $notifications->map(fn($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->message,
                'icon' => $n->icon,
                'url' => $n->url,
                'is_read' => $n->is_read,
                'created_at' => $n->created_at->format('M d, Y h:i A'),
                'created_at_human' => $n->created_at->diffForHumans(),
            ])
        ]);

    }
    // unread
    public function unreadCount()
    {
        return response()->json([
            'count' => Notification::where(
                'user_id',
                auth()->id()
            )->where('is_read', false)->count()
        ]);
    }
    // read
    public function markAsRead($id)
    {
        $notification = Notification::where(
            'user_id',
            auth()->id()
        )->findOrFail($id);
        if ($notification->is_read == true) {
            return response()->json([
                'message' => "Notification already mark."
            ]);
        }
        $notification->update([
            'is_read' => true
        ]);

        return response()->json([
            'message' => 'Mark read successfully'
        ]);
    }
    // read all
    public function markAllAsRead()
    {
        Notification::where(
            'user_id',
            auth()->id()
        )->update([
                    'is_read' => true
                ]);

        return response()->json([
            'message' => 'Success'
        ]);
    }
}
