<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ResendNewCode extends Mailable
{
    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function build()
    {
        return $this->subject('Your Verification Code')
            ->view('emails.verify_otp')
            ->with([
                'code' => $this->code
            ]);
    }
}