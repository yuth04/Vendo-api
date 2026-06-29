<?php
namespace App\Traits;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;

trait NotifiesUsers
{
    // ─── Broadcast to ALL users ───────────────────────────────
    private function notifyAllUsers(
        string $title,
        string $message,
        string $type,
        string $icon = 'bell',
        string $url = '/',
    ): void {
        try {
            $users = User::whereNotIn('role', ['super-admin', 'admin'])->get();

            if ($users->isEmpty())
                return;

            $now = now();

            $notifications = $users->map(fn($user) => [
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
                'icon' => $icon,
                'type' => $type,
                'url' => $url,
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

            // 1. Save to DB
            Notification::insert($notifications);

            // 2. Broadcast to each user
            foreach ($users as $user) {
                $notification = Notification::where('user_id', $user->id)
                    ->latest()
                    ->first();

                broadcast(new NotificationCreated($notification));
            }

        } catch (\Exception $e) {
            \Log::error('Notification insert failed: ' . $e->getMessage());
        }
    }

    // ─── Send to a SINGLE user (orders, returns) ─────────────
    private function initializeNotification(
        $user,
        string $title,
        string $message,
        string $type = 'order',
        string $icon = 'shopping-bag',
        string $url = '/',
    ): void {
        try {
            // 1. Save to DB
            $notification = Notification::create([
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
                'icon' => $icon,
                'type' => $type,
                'url' => $url,
                'is_read' => false,
            ]);

            // 2. Broadcast real-time
            broadcast(new NotificationCreated($notification));

        } catch (\Exception $e) {
            \Log::error('Notification failed: ' . $e->getMessage());
        }
    }
}