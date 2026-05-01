<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Response;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    // public function __invoke(EmailVerificationRequest $request): RedirectResponse
    // {
    //     if ($request->user()->hasVerifiedEmail()) {
    //         return redirect()->intended(
    //             config('app.frontend_url').RouteServiceProvider::HOME.'?verified=1'
    //         );
    //     }

    //     if ($request->user()->markEmailAsVerified()) {
    //         event(new Verified($request->user()));
    //     }

    //     return redirect()->intended(
    //         config('app.frontend_url').RouteServiceProvider::HOME.'?verified=1'
    //     );
    // }
     public function __invoke(EmailVerificationRequest $request): Response
{
    if (! $request->user()->hasVerifiedEmail()) {
        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }
    }

    return response(
        '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Email Verified</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f4f6f9;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                }
                .card {
                    background: #ffffff;
                    padding: 30px;
                    border-radius: 10px;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
                    text-align: center;
                    max-width: 400px;
                }
                h2 {
                    color: #28a745;
                }
                p {
                    color: #555;
                }
                a {
                    display: inline-block;
                    margin-top: 15px;
                    padding: 10px 20px;
                    background-color: #28a745;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                }
                a:hover {
                    background-color: #218838;
                }
            </style>
        </head>
        <body>
            <div class="card">
                <h2>✅ Email Verified</h2>
                <p>Your email has been successfully verified.</p>
                <p>You can now return to Greenhaven and log in.</p>
                <a href="https://cornbread-iciness-matrix.ngrok-free.dev">Go Back</a>
            </div>
        </body>
        </html>
        ',
        200
    )->header('Content-Type', 'text/html');
}
}
    

