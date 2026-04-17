<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ProductReadyNotification extends Notification
{
    use Queueable;

    protected $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function via($notifiable)
    {
        return ['mail']; // You can add 'database', 'sms', etc. here
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Your requested product {$this->product->name} is ready!")
            ->greeting("Hello {$this->product->customer_name},")
            ->line("Good news! The product you requested, {$this->product->name}, is now ready for pickup.")
            ->line('Thank you for your patience.')
            ->action('View Product', url('/products/' . $this->product->id))
            ->line('Thank you for using our service!');
    }
}
