<?php

namespace App\Mail;
use Illuminate\Support\Facades\URL;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $order;
    public $downloadUrl;
    public function __construct(Order $order)
    {
        $this->order = $order;
    }


    public function build()
    {
        return $this->subject('Your Vendo Order #' . $this->order->order_number)
            ->view('emails.place_ordered')
            ->with([
                'order' => $this->order,

            ]);
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
