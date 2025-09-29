<?php

namespace App\Notifications;

use App\Models\Vegetable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class VegetableReadyNotification extends Notification
{
    use Queueable;

    protected $vegetable;

    public function __construct(Vegetable $vegetable)
    {
        $this->vegetable = $vegetable;
    }

    public function via($notifiable)
    {
        return ['mail']; // You can add 'database', 'sms', etc. here
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Your requested vegetable {$this->vegetable->name} is ready!")
            ->greeting("Hello {$this->vegetable->customer_name},")
            ->line("Good news! The vegetable you requested, {$this->vegetable->name}, is now ready for pickup.")
            ->line('Thank you for your patience.')
            ->action('View Vegetable', url('/vegetables/' . $this->vegetable->id))
            ->line('Thank you for using our service!');
    }
}
