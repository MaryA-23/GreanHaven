<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use App\Mail\WelcomeMail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;


class AuthController extends Controller
{
    /**
     * Register a new user.
     */

     public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',   
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'nullable|in:admin,user',
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name, 
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'user',
        ]);

        try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        } catch (\Exception $mailException) {
            Log::error('Welcome email failed', [
                'message' => $mailException->getMessage(),
                'user_id' => $user->id,
            ]);
        }
        event(new Registered($user));
        return response()->json([
            'success' => true,
            'message' => 'User registered successfully. Please verify your email before logging in.',
            'user' => $user,
        ], 201);
    }
     /**
      * login user and return token
      */

     public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

            // ✅ ADMIN BYPASS
        if ($user->role === 'admin') {
            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json([
                'success' => true,
                'message' => 'Admin login successful',
                'user' => $user,
                'token' => $token,
            ], 200);
        }


        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before logging in.',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login Successful',
            'user' => $user,
            'token' => $token,
        ], 200);
    }
    /**
       * logout user (revoke token)
       * optional: call this from frontend on logout
       */
    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success' => true,
            'message'=> 'Logged out Successfully',
            ]);
    }

    /**
     * get authenticated user.
     */

     public function user(Request $request)
     { 
        return response()->json([
            'success'=> true,
            'user'=> $request->user(),
            ]);

     }
}
 