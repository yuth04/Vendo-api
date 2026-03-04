<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetSuccess extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function build()
    {
        return $this->subject('Your Password Has Been Reset')
                    ->view('emails.password_reset_success')
                    ->with([
                        'name' => $this->user->first_name ?? $this->user->email,
                    ]);
    }
}