<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Notifications\AdminCreationNotification;
use App\Notifications\AdminRoleChangedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class AdminController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if ($admin->status === 'inactive') {
            return response()->json([
                'status' => 'failed',
                'message' => 'Your admin account is inactive.',
            ], 403);
        }

        $admin->tokens()->delete();

        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'admin' => $admin,
        ]);
    }

    public function logout(Request $request)
    {
        $admin = $request->user();

        if (!($admin instanceof Admin)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Admin account required.',
            ], 403);
        }

        $token = $admin->currentAccessToken();

        if (!($token instanceof PersonalAccessToken)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'A Bearer token is required to sign out.',
            ], 400);
        }

        $token->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }

    // View the currently signed-in admin's own profile.
    public function myProfile(Request $request)
    {
        $admin = $request->user();

        if (!($admin instanceof Admin)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Admin account required.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profile retrieved successfully.',
            'data' => $admin->only([
                'uuid',
                'surname',
                'othernames',
                'fullname',
                'email',
                'phone',
                'role',
                'status',
            ]),
        ]);
    }

    // Update only the signed-in admin's name and phone.
    public function updateProfile(Request $request)
    {
        $admin = $request->user();

        if (!($admin instanceof Admin)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Admin account required.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'surname' => ['required', 'string', 'max:100'],
            'othernames' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $admin->surname = trim($data['surname']);
        $admin->othernames = trim($data['othernames']);
        $admin->fullname = trim(
            $admin->surname . ' ' . $admin->othernames
        );
        $admin->phone = trim($data['phone']);

        $admin->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully.',
            'data' => $admin->only([
                'uuid',
                'surname',
                'othernames',
                'fullname',
                'email',
                'phone',
                'role',
                'status',
            ]),
        ]);
    }

    public function changePassword(Request $request)
    {
        $admin = $request->user();

        if (!($admin instanceof Admin)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Admin account required.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:7',
                'max:72',
                'confirmed',
                'different:current_password',
                'regex:/[A-Za-z]/',
                'regex:/[0-9]/',
            ],
        ], [
            'password.regex' =>
                'The new password must contain a letter and a number.',
            'password.confirmed' =>
                'The password confirmation does not match.',
            'password.different' =>
                'Choose a password different from your current password.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (!Hash::check($data['current_password'], $admin->password)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Your current password is incorrect.',
                'errors' => [
                    'current_password' => [
                        'Your current password is incorrect.',
                    ],
                ],
            ], 422);
        }

        $token = $admin->currentAccessToken();

        if (!($token instanceof PersonalAccessToken)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'A Bearer token is required.',
            ], 400);
        }

        $admin->getConnection()->transaction(
            function () use ($admin, $data, $token) {
                $admin->password = Hash::make($data['password']);
                $admin->save();

                // Keep this session and revoke other admin tokens.
                $admin->tokens()
                    ->where('id', '!=', $token->getKey())
                    ->delete();
            }
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully.',
        ]);
    }

    public function register(Request $request)
    {
        if (
            !app()->environment('local') ||
            !in_array($request->ip(), ['127.0.0.1', '::1'], true)
        ) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Initial registration is only available locally.',
            ], 403);
        }

        $lock = Cache::lock('greenhaven-first-super-admin', 120);

        if (!$lock->get()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Registration is already in progress.',
            ], 409);
        }

        try {
            if (Admin::where('role', 'super_admin')->exists()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'A Super Admin already exists. Please sign in.',
                ], 403);
            }

            return $this->createAdmin($request, 'super_admin');
        } finally {
            $lock->release();
        }
    }

    public function addnewuser(Request $request)
    {
        $currentAdmin = $request->user();

        if (
            !($currentAdmin instanceof Admin) ||
            $currentAdmin->role !== 'super_admin'
        ) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Only a Super Admin can create admin accounts.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'role' => ['required', Rule::in(['admin', 'super_admin'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        return $this->createAdmin($request, $request->input('role'));
    }

    private function generatePassword(): string
    {
        $characters = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $lastIndex = strlen($characters) - 1;

        do {
            $password = '';

            for ($index = 0; $index < 7; $index++) {
                $password .= $characters[random_int(0, $lastIndex)];
            }
        } while (
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[2-9]/', $password)
        );

        return $password;
    }

    private function createAdmin(Request $request, string $role)
    {
        $validator = Validator::make($request->all(), [
            'surname' => ['required', 'string', 'max:100'],
            'othernames' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(Admin::class, 'email'),
            ],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $mailer = config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");

        if (!$transport || in_array($transport, ['log', 'array'], true)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Configure an email service before registering.',
            ], 503);
        }

        $data = $validator->validated();
        $admin = new Admin();

        try {
            $password = $this->generatePassword();

            $admin->getConnection()->transaction(
                function () use ($admin, $data, $password, $role) {
                    $admin->forceFill([
                        'uuid' => (string) Str::uuid(),
                        'surname' => $data['surname'],
                        'othernames' => $data['othernames'],
                        'fullname' => trim(
                            $data['surname'] . ' ' . $data['othernames']
                        ),
                        'email' => $data['email'],
                        'phone' => $data['phone'],
                        'role' => $role,
                        'status' => 'active',
                        'password' => Hash::make($password),
                    ]);

                    $admin->save();

                    $admin->notifyNow(
                        new AdminCreationNotification([
                            'name' => $data['othernames'],
                            'email' => $data['email'],
                            'password' => $password,
                        ])
                    );
                }
            );
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Registration failed. Check the database and email configuration.',
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Account created. Check your email for your login details.',
        ], 201);
    }

    public function changerole(Request $request)
    {
        $currentAdmin = $request->user();

        if (
            !($currentAdmin instanceof Admin) ||
            $currentAdmin->role !== 'super_admin'
        ) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Only a Super Admin can change admin roles.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'admin' => ['required', 'string'],
            'role' => ['required', Rule::in(['admin', 'super_admin'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $admin = Admin::where('uuid', $request->input('admin'))->first();

        if (!$admin) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Admin not found.',
            ], 404);
        }

        if ($admin->uuid === $currentAdmin->uuid) {
            return response()->json([
                'status' => 'failed',
                'message' => 'You cannot change your own role.',
            ], 403);
        }

        $admin->role = $request->input('role');
        $admin->save();

        try {
            $admin->notifyNow(
                new AdminRoleChangedNotification([
                    'name' => $admin->othernames,
                    'role' => $admin->role === 'super_admin'
                        ? 'Super Admin'
                        : 'Admin',
                ])
            );
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'success',
                'message' => 'Role updated, but the notification email could not be sent.',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Admin role updated successfully.',
        ]);
    }

    public function profile(Request $request, $uuid)
    {
        $currentAdmin = $request->user();

        if (
            !($currentAdmin instanceof Admin) ||
            (
                $currentAdmin->role !== 'super_admin' &&
                $currentAdmin->uuid !== $uuid
            )
        ) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Forbidden.',
            ], 403);
        }

        $admin = Admin::where('uuid', $uuid)->first();

        if (!$admin) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Admin not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Admin profile fetched successfully.',
            'data' => $admin,
        ]);
    }
}