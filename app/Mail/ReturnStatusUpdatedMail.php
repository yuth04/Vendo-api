<?php

namespace App\Mail;

use App\Models\ReturnOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReturnStatusUpdatedMail extends Mailable
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

    /**
     * Get the message envelope.
     */
    public function build()
    {
        $status = ucfirst($this->returnOrder->status);
        return $this->subject("Your Return Request has been $status")
            ->view('emails.return_status_updated');
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
