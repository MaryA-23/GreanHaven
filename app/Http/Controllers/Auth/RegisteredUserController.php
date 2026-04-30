<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        } catch (\Exception $mailException) {
            \Log::error('Welcome email failed', [
                'message' => $mailException->getMessage(),
                'user_id' => $user->id,
            ]);
        }

        event(new Registered($user));

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please check your email to verify your account.',
            'user' => $user,
        ], 201);
    }
}