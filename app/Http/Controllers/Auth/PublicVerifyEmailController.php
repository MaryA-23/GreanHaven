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

        if ($hash !== sha1($user->getEmailForVerification())) {

            return redirect(
                env('FRONTEND_URL', 'http://localhost:4200')
                . '/email-verified?status=invalid'
            );
        }

        if (! $user->hasVerifiedEmail()) {

            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        return redirect(
            env('FRONTEND_URL', 'http://localhost:4200')
            . '/email-verified?status=success'
        );
    }
}