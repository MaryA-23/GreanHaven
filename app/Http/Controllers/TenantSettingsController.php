<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class TenantSettingsController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Get Settings
    |--------------------------------------------------------------------------
    */

    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'company' || !$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $company = $user->company;

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        $notifications = $company->notification_settings ?? [
            'newOrders' => true,
            'lowStock' => true,
            'payments' => true,
            'emailNotifications' => true,
        ];

        return response()->json([
            'success' => true,

            'data' => [
                'business' => [
                    'businessName' => $company->name,
                    'email' => $company->email ?? $user->email,
                    'phone' => $company->phone ?? $user->phone,
                    'address' => $company->address,
                    'city' => $company->city,
                    'currency' => $company->currency ?? 'GHS',
                ],

                'notifications' => $notifications,

                'account' => [
                    'firstName' => $user->first_name,
                    'lastName' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => 'Active',
                ],
            ],
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Update Business Information
    |--------------------------------------------------------------------------
    */

    public function updateBusiness(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'company' || !$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'businessName' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'city' => [
                'nullable',
                'string',
                'max:255',
            ],

            'currency' => [
                'required',
                'in:GHS,USD',
            ],
        ]);

        $company = $user->company;

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        $company->update([
            'name' => $validated['businessName'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'currency' => $validated['currency'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Business information updated successfully.',

            'data' => [
                'businessName' => $company->name,
                'email' => $company->email,
                'phone' => $company->phone,
                'address' => $company->address,
                'city' => $company->city,
                'currency' => $company->currency,
            ],
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Update Notification Settings
    |--------------------------------------------------------------------------
    */

    public function updateNotifications(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'company' || !$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'newOrders' => [
                'required',
                'boolean',
            ],

            'lowStock' => [
                'required',
                'boolean',
            ],

            'payments' => [
                'required',
                'boolean',
            ],

            'emailNotifications' => [
                'required',
                'boolean',
            ],
        ]);

        $company = $user->company;

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        $company->update([
            'notification_settings' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated successfully.',
            'data' => $validated,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Change Password
    |--------------------------------------------------------------------------
    */

    public function changePassword(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'company') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'current_password' => [
                'required',
                'string',
            ],

            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],
        ]);

        if (
            !Hash::check(
                $validated['current_password'],
                $user->password
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }
}