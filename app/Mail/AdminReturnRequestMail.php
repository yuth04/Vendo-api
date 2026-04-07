<?php

namespace App\Mail;

use App\Models\ReturnOrder; // Assuming this is your model name
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminReturnRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The return order instance.
     * Note: Public properties are automatically available in the view.
     */
    public $returnOrder;

    /**
     * Create a new message instance.
     */
    public function __construct($returnOrder)
    {

        $this->returnOrder = $returnOrder->loadMissing([
            'user',
            'items.orderItem.product'
        ]);
    }

    /**
     * Get the message envelope (Subject line).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🚨 Action Required: New Return Request #' . $this->returnOrder->order_id,
        );
    }

    /**
     * Get the message content definition (View).
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.return-requested',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}