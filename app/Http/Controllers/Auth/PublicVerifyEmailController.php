<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class PublicVerifyEmailController extends Controller
{
    public function __invoke(Request $request, $id, $hash): Response
    {
        $user = User::findOrFail($id);

        // ONLY ONE CHECK (keep it simple)
        if ($hash !== sha1($user->getEmailForVerification())) {
            return response('Invalid verification link', 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response(
            '<h2>Email verified successfully</h2><p>You can now login.</p>',
            200
        )->header('Content-Type', 'text/html');
    }
}