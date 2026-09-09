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

    // Seven characters, including at least one letter and one number.
    // Excludes I, L, O, 0 and 1 to make the password easier to read.
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