<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReturnShippedMail extends Mailable
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

    public function build(){
        return $this->subject('Your Replacement Item Has Shipped')->view('emails.return_shipped');
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