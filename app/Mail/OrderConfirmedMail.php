<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\URL;

class OrderConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $order;

    public $downloadUrl;

    public function __construct(Order $order)
    {
        $this->order = $order;

        // Generates a secure, expiring link for the receipt
        $this->downloadUrl = URL::temporarySignedRoute(
            'order.receipt.download',
            now()->addDays(7),
            ['order' => $order->id]
        );
    }
    public function build()
    {
        return $this->subject('🎉 Your Order Has Been Delivered!')
            ->view('emails.order_confirmed');
    }
    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }


}