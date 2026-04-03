<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShippedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $order;
<<<<<<< HEAD
    
    public function __construct(Order $order,)
    {
        $this->order = $order;
       
=======

    public function __construct(Order $order)
    {
        $this->order = $order;
>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
    }

    public function build()
    {
<<<<<<< HEAD
        return $this->subject('🚚 Your Order Has Been Shipped!')
=======
        return $this->subject('Your Order has been Confirmed!')
>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
            ->view('emails.order_shipped');
    }
    /**
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
