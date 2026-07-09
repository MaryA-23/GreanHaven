<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminCreationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */

    public $mail_details;
    /**
     * Create a new notification instance.
     * @return void
     */
    public function __construct($mail_details)
    {
        $this->mail_details = $mail_details;
    }   


    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('You have been registered as an Admin on Greenhaven platform.')
                    ->view('emails.admin-creation', ['mail_details' => $this->mail_details] );  
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
