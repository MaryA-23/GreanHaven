<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminCreationNotification extends Notification
{
    use Queueable;

    public array $mail_details;

    public function __construct(array $mail_details)
    {
        $this->mail_details = $mail_details;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->mail_details['name'];
        $email = $this->mail_details['email'];
        $password = $this->mail_details['password'];

        $frontendUrl = rtrim(
            config('app.frontend_url', 'http://localhost:4200'),
            '/'
        );

        $loginUrl = $frontendUrl . '/super-admin/login';

        return (new MailMessage)
            ->subject('Your GreenHaven administrator account')
            ->greeting('Hello ' . $name . ',')
            ->line('An administrator account has been created for you on GreenHaven.')
            ->line('Use the following details to sign in:')
            ->line('Email: ' . $email)
            ->line('Password: ' . $password)
            ->action('Sign in to GreenHaven', $loginUrl)
            ->line('Keep these login details private.')
            ->salutation('The GreenHaven Team');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}