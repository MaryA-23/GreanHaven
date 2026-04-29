<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $user;

    public function __construct($order, $user = null)
    {
        $this->order = $order;
        $this->user = $user ?? $order->user;
    }

    public function build()
    {
        return $this->subject('Greenhaven Order Confirmation')
            ->view('emails.order_created')
            ->with([
                'order' => $this->order,
                'user' => $this->user,
            ]);
    }
}