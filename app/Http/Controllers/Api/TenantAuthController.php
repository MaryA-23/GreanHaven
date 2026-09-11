<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\Company;
use App\Models\User;
use App\Services\AdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;

class TenantAuthController extends Controller
{
    /**
     * Register a new tenant/company account.
     */
    public function register(
        Request $request,
        AdminNotificationService $adminNotificationService
    ) {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:8|max:72|confirmed',
        ]);

        DB::beginTransaction();

        try {
            $company = Company::create([
                'name' => $validated['company_name'],
                'email' => $validated['email'],
            ]);

            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => 'company',
                'company_id' => $company->id,
            ]);

            DB::commit();

            /**
             * Notify Admin / Super Admin after the transaction succeeds.
             */
            try {
                $adminNotificationService->newTenant(
                    $company->id,
                    $company->name
                );
            } catch (\Exception $notificationException) {
                Log::error(
                    'Admin new tenant notification failed',
                    [
                        'message' =>
                            $notificationException->getMessage(),

                        'company_id' =>
                            $company->id,
                    ]
                );
            }

            /**
             * Send verification email.
             */
            try {
                Mail::to($user->email)
                    ->send(new WelcomeMail($user));
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
                    'message' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Tenant registration failed.',
            ], 500);
        }
    }

    /**
     * Tenant login.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where(
            'email',
            $validated['email']
        )->first();

        if (
            !$user ||
            !Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        /**
         * This endpoint is only for tenant/company accounts.
         */
        if ($user->role !== 'company') {
            return response()->json([
                'success' => false,
                'message' =>
                    'This account is not a tenant account.',
            ], 403);
        }

        /**
         * Block inactive tenant accounts.
         */
        if (
            isset($user->status) &&
            $user->status === 'inactive'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Your tenant account is inactive.',
            ], 403);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Please verify your email address before logging in.',
            ], 403);
        }

        $user->update([
            'last_login' => now(),
        ]);

        $token = $user
            ->createToken('tenant_auth_token')
            ->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Tenant login successful.',
            'user' => $user->load('company'),
            'token' => $token,
        ], 200);
    }

    /**
     * Tenant logout.
     */
    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Tenant logged out successfully.',
        ]);
    }
}
