<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

        if (
            $user->role !== 'company' ||
            !$user->company_id
        ) {
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

        $notifications =
            $company->notification_settings ?? [
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
                    'phone' => $company->phone,
                    'address' => $company->address,
                    'city' => $company->city,
                    'currency' => $company->currency ?? 'GHS',
                ],

                'notifications' => $notifications,

                'account' => [
                    'firstName' => $user->first_name,
                    'lastName' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status ?? 'active',

                    'profilePicture' =>
                        $user->profile_picture,

                    'profilePictureUrl' =>
                        $user->profile_picture
                            ? asset(
                                'storage/' .
                                $user->profile_picture
                            )
                            : null,
                ],
            ],
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Update Business + Account Information
    |--------------------------------------------------------------------------
    |
    | This endpoint keeps the existing business settings route.
    | It can also update the tenant account information and profile picture.
    |
    */

    public function updateBusiness(Request $request)
    {
        $user = $request->user();

        if (
            $user->role !== 'company' ||
            !$user->company_id
        ) {
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

        $validated = $request->validate([

            /*
            |--------------------------------------------------------------------------
            | Business
            |--------------------------------------------------------------------------
            */

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

            'businessPhone' => [
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


            /*
            |--------------------------------------------------------------------------
            | Account Holder
            |--------------------------------------------------------------------------
            */

            'firstName' => [
                'required',
                'string',
                'max:255',
            ],

            'lastName' => [
                'required',
                'string',
                'max:255',
            ],

            'accountPhone' => [
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


        /*
        |--------------------------------------------------------------------------
        | Update Company
        |--------------------------------------------------------------------------
        */

        $company->update([
            'name' =>
                $validated['businessName'],

            'email' =>
                $validated['email'] ?? null,

            'phone' =>
                $validated['businessPhone'] ?? null,

            'address' =>
                $validated['address'] ?? null,

            'city' =>
                $validated['city'] ?? null,

            'currency' =>
                $validated['currency'],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Update Account Holder
        |--------------------------------------------------------------------------
        */

        $user->first_name =
            $validated['firstName'];

        $user->last_name =
            $validated['lastName'];

        $user->phone =
            $validated['accountPhone'] ?? null;


        /*
        |--------------------------------------------------------------------------
        | Profile Picture
        |--------------------------------------------------------------------------
        */

        if (
            $request->hasFile(
                'profile_picture'
            )
        ) {

            /*
            | Delete old picture
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


            /*
            | Store new picture
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

        $user->refresh();
        $company->refresh();


        return response()->json([
            'success' => true,

            'message' =>
                'Business and account information updated successfully.',

            'data' => [

                'business' => [
                    'businessName' => $company->name,
                    'email' => $company->email,
                    'phone' => $company->phone,
                    'address' => $company->address,
                    'city' => $company->city,
                    'currency' => $company->currency,
                ],

                'account' => [
                    'firstName' => $user->first_name,
                    'lastName' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status ?? 'active',

                    'profilePicture' =>
                        $user->profile_picture,

                    'profilePictureUrl' =>
                        $user->profile_picture
                            ? asset(
                                'storage/' .
                                $user->profile_picture
                            )
                            : null,
                ],

                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status,
                    'company_id' => $user->company_id,

                    'profile_picture' =>
                        $user->profile_picture,

                    'profile_picture_url' =>
                        $user->profile_picture
                            ? asset(
                                'storage/' .
                                $user->profile_picture
                            )
                            : null,
                ],

                'company' => $company,
            ],
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Update Notification Settings
    |--------------------------------------------------------------------------
    */

    public function updateNotifications(
        Request $request
    ) {
        $user = $request->user();

        if (
            $user->role !== 'company' ||
            !$user->company_id
        ) {
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
            'notification_settings' =>
                $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Notification settings updated successfully.',
            'data' => $validated,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Change Password
    |--------------------------------------------------------------------------
    */

    public function changePassword(
        Request $request
    ) {
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
                'message' =>
                    'Current password is incorrect.',
            ], 422);
        }


        if (
            Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Your new password must be different from your current password.',
            ], 422);
        }


        $user->password =
            Hash::make(
                $validated['password']
            );

        $user->save();


        return response()->json([
            'success' => true,
            'message' =>
                'Password changed successfully.',
        ]);
    }
}