<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function build()
    {
        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $this->user->id,
                'hash' => sha1(
                    $this->user->getEmailForVerification()
                ),
            ]
        );

        return $this->subject('Welcome to GreenHaven')
            ->view('emails.welcome')
            ->with([
                'user' => $this->user,
                'verifyUrl' => $verifyUrl,
            ]);
    }
}