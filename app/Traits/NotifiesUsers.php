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
            $users = User::get();

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
        string $url = '/'
    ): void {
        try {

            $userId = null;

            if (is_object($user) && isset($user->id)) {
                $userId = $user->id;
            } elseif (is_numeric($user)) {
                $userId = $user;
            }

            if (!$userId) {
                \Log::error('Notification failed: invalid user');
                return;
            }

            Notification::create([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'icon' => $icon,
                'type' => $type,
                'url' => $url,
                'is_read' => false,
            ]);

        } catch (\Exception $e) {
            \Log::error('Notification failed: ' . $e->getMessage());
        }
    }
}