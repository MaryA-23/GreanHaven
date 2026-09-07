<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    /**
     * Register customer.
     */
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'nullable|in:admin,user',
            'phone' => 'nullable|string|max:30',
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'user',
            'phone' => $request->phone,
        ]);

        try {

            Mail::to($user->email)
                ->send(new WelcomeMail($user));

        } catch (\Exception $mailException) {

            Log::error('Welcome email failed', [
                'message' => $mailException->getMessage(),
                'user_id' => $user->id,
            ]);

        }

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully. Please verify your email before logging in.',
            'user' => $this->formatUser($user),
        ], 201);
    }


    /**
     * Login customer.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where(
            'email',
            $request->email
        )->first();

        if (
            !$user ||
            !Hash::check(
                $request->password,
                $user->password
            )
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user->update([
            'last_login' => now(),
        ]);


        /**
         * Admin bypass.
         */
        if ($user->role === 'admin') {

            $token = $user
                ->createToken('auth_token')
                ->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Admin login successful',
                'user' => $this->formatUser($user),
                'token' => $token,
            ], 200);
        }


        /**
         * Customer/company email verification.
         */
        if (!$user->hasVerifiedEmail()) {

            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before logging in.',
            ], 403);
        }


        $token = $user
            ->createToken('auth_token')
            ->plainTextToken;


        return response()->json([
            'success' => true,
            'message' => 'Login Successful',
            'user' => $this->formatUser($user),
            'token' => $token,
        ], 200);
    }


    /**
     * Logout.
     */
    public function logout(Request $request)
    {
        $request
            ->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out Successfully',
        ]);
    }


    /**
     * Get authenticated user.
     */
    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $this->formatUser(
                $request->user()
            ),
        ]);
    }


    /**
     * Update customer profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'profile_picture' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);


        /**
         * Update normal information.
         */
        $user->first_name =
            $validated['first_name'];

        $user->last_name =
            $validated['last_name'];

        $user->phone =
            $validated['phone'] ?? null;


        /**
         * Upload profile picture.
         */
        if (
            $request->hasFile(
                'profile_picture'
            )
        ) {

            /**
             * Delete old picture.
             */
            if (
                $user->profile_picture &&
                Storage::disk('public')->exists(
                    $user->profile_picture
                )
            ) {

                Storage::disk('public')->delete(
                    $user->profile_picture
                );
            }


            /**
             * Store new picture.
             */
            $path = $request
                ->file('profile_picture')
                ->store(
                    'profile-pictures',
                    'public'
                );


            $user->profile_picture = $path;
        }


        $user->save();


        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user' => $this->formatUser(
                $user->fresh()
            ),
        ]);
    }


    /**
     * Format user response.
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,

            'first_name' =>
                $user->first_name,

            'last_name' =>
                $user->last_name,

            'name' =>
                trim(
                    $user->first_name .
                    ' ' .
                    $user->last_name
                ),

            'email' =>
                $user->email,

            'phone' =>
                $user->phone,

            'gender' =>
                $user->gender,

            'role' =>
                $user->role,

            'status' =>
                $user->status,

            'company_id' =>
                $user->company_id,

            'email_verified_at' =>
                $user->email_verified_at,

            'profile_picture' =>
                $user->profile_picture,

            'profile_picture_url' =>
                $user->profile_picture
                    ? asset(
                        'storage/' .
                        $user->profile_picture
                    )
                    : null,

            'last_login' =>
                $user->last_login,
        ];
    }
}