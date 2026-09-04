<?php

namespace App\Mail;

use App\Models\ReturnOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReturnReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public ReturnOrder $return;

    /**
     * Create a new message instance.
     */
    public ReturnOrder $returnOrder; /** * Create a new message instance. */
    public function __construct(ReturnOrder $returnOrder)
    {
        $this->returnOrder = $returnOrder;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this
            ->subject(
                'Exchange Received - Return #' .
                $this->returnOrder->return_number
            )
            ->markdown('emails.returns.received');
    }
}

