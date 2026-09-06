<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Mail\WelcomeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TenantAuthController extends Controller
{
    /**
     * Register a new tenant/company account.
     */
    public function register(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',

            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',

            'email' => 'required|email|max:255|unique:users,email',

            'phone' => 'nullable|string|max:30',

            'password' => 'required|string|min:8|confirmed',
        ]);

        DB::beginTransaction();

        try {

            // Create company
            $company = Company::create([
                'name' => $request->company_name,
                'email' => $request->email,
            ]);


            // Create tenant user
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),

                // Important
                'role' => 'company',
                'company_id' => $company->id,
            ]);

            DB::commit();


            // Send verification email
            try {

                Mail::to($user->email)
                    ->send(
                        new WelcomeMail($user)
                    );

            } catch (\Exception $mailException) {

                Log::error(
                    'Tenant welcome email failed',
                    [
                        'message' =>
                            $mailException->getMessage(),

                        'user_id' =>
                            $user->id,
                    ]
                );

            }


            return response()->json([
                'success' => true,

                'message' =>
                    'Tenant registered successfully. Please verify your email before logging in.',

                'user' => $user,

                'company' => $company,

            ], 201);


        } catch (\Exception $e) {

            DB::rollBack();

            Log::error(
                'Tenant registration failed',
                [
                    'message' => $e->getMessage()
                ]
            );

          return response()->json([
            'success' => false,
            'message' => 'Tenant registration failed.',
            'error' => $e->getMessage(),
        ], 500);
        }
    }

        public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)
            ->first();

        if (
            ! $user ||
            ! Hash::check(
                $request->password,
                $user->password
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }


        if ($user->role !== 'company') {
            return response()->json([
                'success' => false,
                'message' => 'This account is not a tenant account.',
            ], 403);
        }


        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Please verify your email address before logging in.',
            ], 403);
        }


        $user->update([
            'last_login' => now(),
        ]);


        $token =
            $user->createToken(
                'tenant_auth_token'
            )->plainTextToken;


        return response()->json([
            'success' => true,
            'message' => 'Tenant login successful.',
            'user' => $user->load('company'),
            'token' => $token,
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()
            ->currentAccessToken()
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tenant logged out successfully.',
        ]);
    }

}