<?php

namespace App\Mail;

use App\Models\ReturnOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReturnCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
public $returnOrder;

    public function __construct(ReturnOrder $returnOrder)
    {
        $this->returnOrder = $returnOrder;
    }

    public function build()
    {
        return $this->subject('Your Return Order #' . $this->returnOrder->id . ' is Complete')
                    ->view('emails.returns.return_completed');
    }
    /**
     * Get the message envelope.
     */

    /**
     * Get the message content definition.
     */
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
