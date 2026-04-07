<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderDeliveredMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $order;
    public $shipping;
    public $transaction_id;
    public $payment_reference;

    public function __construct(Order $order, $shipping, $transaction_id, $payment_reference)
    {
        $this->order = $order;
        $this->shipping = $shipping;
        $this->transaction_id = $transaction_id;
        $this->payment_reference = $payment_reference;

    }

    public function build()
    {
        return $this->subject('🎉 Your Order Has Been Delivered!')
            ->view('emails.order_delivered');
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
