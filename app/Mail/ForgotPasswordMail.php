<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable
{
     use Queueable, SerializesModels;

    public $token;
    public $link;

    public function __construct($token, $link)
    {
        $this->token = $token;
        $this->link = $link;
    }

    public function build()
    {
        return $this->subject('Reset Your Password')
                    ->view('emails.forgot_password');
    }
}
