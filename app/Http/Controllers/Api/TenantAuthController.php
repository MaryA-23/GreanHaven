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
                'message' =>
                    'Tenant registration failed.',
            ], 500);
        }
    }
}