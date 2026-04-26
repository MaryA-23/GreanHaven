<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $payment;
    public $user;

    public function __construct($order, $payment = null, $user = null)
    {
        $this->order = $order;
        $this->payment = $payment;
        $this->user = $user ?? $order->user;
    }

    public function build()
    {
        return $this->subject('Greenhaven Payment Confirmation')
            ->view('emails.payment_success')
            ->with([
                'order' => $this->order,
                'payment' => $this->payment,
                'user' => $this->user,
            ]);
    }
}