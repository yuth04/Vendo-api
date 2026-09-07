<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReturnOrderRequestMail extends Mailable
{
    use Queueable, SerializesModels;
    public $returnOrder;
    /**
     * Create a new message instance.
     */
    public function __construct($returnOrder)
    {
        $this->returnOrder = $returnOrder;
    }

    public function build()
    {
        return $this->subject('New Return Order Request')->view('Mail.return.return_shipped');
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
