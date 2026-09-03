<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class PublicVerifyEmailController extends Controller
{
    public function __invoke(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        // Validate verification hash
        if ($hash !== sha1($user->getEmailForVerification())) {
            return redirect(
                env('FRONTEND_URL', 'http://localhost:4200')
                . '/email-verified?status=invalid'
            );
        }

        // Verify only if not already verified
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        // Send customer back to Angular
        return redirect(
            env('FRONTEND_URL', 'http://localhost:4200')
            . '/email-verified?status=success'
        );
    }
}