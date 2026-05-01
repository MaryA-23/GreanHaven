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
    public function __invoke(EmailVerificationRequest $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response('<h3>Already verified</h3>', 200)
                ->header('Content-Type', 'text/html');
        }

        $request->fulfill(); // verifies email

        return response('<h2>Email verified successfully</h2>', 200)
            ->header('Content-Type', 'text/html');
    }
 }
    

