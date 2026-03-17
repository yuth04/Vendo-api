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

    public $code;
    public $expired;
    public function __construct($code, $expired)
    {
        $this->code = $code;
        $this->expired = $expired;
    }

    public function build()
    {
        return $this->subject('Reset Your Password')
                    ->view('emails.forgot_password');
    }
}
