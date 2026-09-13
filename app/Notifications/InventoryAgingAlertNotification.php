<?php

namespace App\Notifications;

use App\Models\Notification as CustomNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InventoryAgingAlertNotification extends Notification
{
    use Queueable;

    protected array $item;

    public function __construct(array $item)
    {
        $this->item = $item;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * Map data to match your custom Notification table columns.
     */
    public function toDatabase($notifiable): array
    {
        $typeLabel = str_replace('_', ' ', $this->item['alert_type'] ?? 'aging stock');

        return [
            'user_id' => $notifiable->id,
            'title' => 'Inventory Alert: ' . ucfirst($typeLabel),
            'message' => "{$this->item['product_name']} ({$this->item['size']} · {$this->item['color']}) is categorized as {$typeLabel}.",
            'type' => $this->item['alert_type'] ?? 'inventory_alert',
            'url' => '/admin/inventory/alerts',
            'is_read' => false,
            'icon' => 'alert-triangle',
        ];
    }
}