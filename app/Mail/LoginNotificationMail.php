<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $user;
    public $time;

    public function __construct($user)
    {
        $this->user = $user;
        // Use the timezone you configured for Vendo
        $this->time = now()->setTimezone('Asia/Phnom_Penh')->format('Y-m-d h:i A');
    }

    public function build()
    {
        return $this->subject('New Login Detected - Vendo')
            ->view('emails.login_notification');
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
