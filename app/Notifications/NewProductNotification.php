<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class NewProductNotification extends Notification
{
    protected $product;

    public function __construct($product)
    {
        $this->product = $product;
    }

    public function via($notifiable)
    {
        $channels = ['database'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toDatabase($notifiable)
    {
        return $this->notificationData();
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->notificationData());
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('🛍️ New Product Available!')
            ->body("{$this->product->productName} is now available!")
            ->icon($this->product->image ?? '/logo.png')
            ->data([
                'product_id' => $this->product->id,
                'product_slug' => $this->product->slug,
                'url' => '/products/' . $this->product->slug,
            ])
            ->action('View Product', 'view_product');
    }

    private function notificationData()
    {
        return [
            'title' => '🛍️ New Product Available!',
            'body' => "{$this->product->productName} is now available!",
            'product_id' => $this->product->id,
            'product_slug' => $this->product->slug,
            'image' => $this->product->image,
            'price' => number_format((float) $this->product->price, 2),
        ];
    }
}