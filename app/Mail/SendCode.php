<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendCode extends Mailable
{
    use Queueable, SerializesModels;

    public int $code;
    public int $expire;

    /**
     * Create a new message instance.
     *
     * @param int $code
     * @param int $expire Minutes until expiration
     */
    public function __construct(int $code, int $expire = 10)
    {
        $this->code = $code;
        $this->expire = $expire;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your Verification Code')
                    ->view('emails.send_code')
                    ->with([
                        'code' => $this->code,
                        'expire' => $this->expire,
                    ]);
    }
}