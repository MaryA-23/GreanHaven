<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Notifications\AdminRoleChangedNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class AdminAccountController extends Controller
{
    public function index(Request $request)
    {
        $currentAdmin = $request->user();

        if (
            !($currentAdmin instanceof Admin) ||
            $currentAdmin->role !== 'super_admin' ||
            $currentAdmin->status !== 'active'
        ) {
            return response()->json([
                'status' => 'failed',
                'message' => 'An active Super Admin account is required.',
            ], 403);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim($validated['search'] ?? '');

        $admins = Admin::query()
            ->select([
                'uuid',
                'surname',
                'othernames',
                'fullname',
                'email',
                'phone',
                'role',
                'status',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('fullname', 'like', '%' . $search . '%')
                        ->orWhere('surname', 'like', '%' . $search . '%')
                        ->orWhere('othernames', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('fullname')
            ->orderBy('uuid')
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'message' => 'Admin accounts retrieved successfully.',
            'data' => $admins,
        ]);
    }

    public function update(Request $request, string $uuid)
    {
        $currentAdmin = $request->user();

        if (
            !($currentAdmin instanceof Admin) ||
            $currentAdmin->role !== 'super_admin' ||
            $currentAdmin->status !== 'active'
        ) {
            return response()->json([
                'status' => 'failed',
                'message' => 'An active Super Admin account is required.',
            ], 403);
        }

        $validated = $request->validate([
            'role' => [
                'required_without:status',
                Rule::in(['admin', 'super_admin']),
            ],
            'status' => [
                'required_without:role',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        $roleChanged = false;
        $updatedAdmin = null;

        $response = $currentAdmin->getConnection()->transaction(
            function () use (
                $currentAdmin,
                $uuid,
                $validated,
                &$roleChanged,
                &$updatedAdmin
            ) {
                // Serialize account changes so simultaneous requests
                // cannot remove every active Super Admin.
                $admins = Admin::query()
                    ->orderBy('uuid')
                    ->lockForUpdate()
                    ->get();

                $actor = $admins->firstWhere(
                    'uuid',
                    $currentAdmin->uuid
                );

                if (
                    !$actor ||
                    $actor->role !== 'super_admin' ||
                    $actor->status !== 'active'
                ) {
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'Your account can no longer manage admins.',
                    ], 403);
                }

                $admin = $admins->firstWhere('uuid', $uuid);

                if (!$admin) {
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'Admin account not found.',
                    ], 404);
                }

                if ($admin->uuid === $actor->uuid) {
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'You cannot change your own role or status.',
                    ], 403);
                }

                $newRole = $validated['role'] ?? $admin->role;
                $newStatus = $validated['status'] ?? $admin->status;

                $activeSuperAdmins = $admins->filter(
                    fn ($account) =>
                        $account->role === 'super_admin' &&
                        $account->status === 'active'
                )->count();

                $removesActiveSuperAdmin =
                    $admin->role === 'super_admin' &&
                    $admin->status === 'active' &&
                    (
                        $newRole !== 'super_admin' ||
                        $newStatus !== 'active'
                    );

                if ($removesActiveSuperAdmin && $activeSuperAdmins <= 1) {
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'At least one active Super Admin must remain.',
                    ], 422);
                }

                $roleChanged = $admin->role !== $newRole;
                $statusChanged = $admin->status !== $newStatus;

                if (!$roleChanged && !$statusChanged) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'No changes were needed.',
                        'data' => $this->accountDetails($admin),
                    ]);
                }

                $admin->role = $newRole;
                $admin->status = $newStatus;
                $admin->save();

                // Existing sessions must not retain their old access.
                $admin->tokens()->delete();

                $updatedAdmin = $admin;

                return response()->json([
                    'status' => 'success',
                    'message' => 'Admin account updated successfully.',
                    'data' => $this->accountDetails($admin),
                ]);
            }
        );

        // Send email after the database transaction completes.
        if ($roleChanged && $updatedAdmin) {
            try {
                $updatedAdmin->notifyNow(
                    new AdminRoleChangedNotification([
                        'name' => $updatedAdmin->othernames,
                        'role' => $updatedAdmin->role === 'super_admin'
                            ? 'Super Admin'
                            : 'Admin',
                    ])
                );
            } catch (Throwable $exception) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Account updated, but the role notification email could not be sent.',
                    'data' => $this->accountDetails($updatedAdmin),
                ]);
            }
        }

        return $response;
    }

    // Keep the existing role-change endpoint using the same checks.
    public function changeRole(Request $request)
    {
        $validated = $request->validate([
            'admin' => ['required', 'string'],
            'role' => ['required', Rule::in(['admin', 'super_admin'])],
        ]);

        return $this->update($request, $validated['admin']);
    }

    private function accountDetails(Admin $admin): array
    {
        return $admin->only([
            'uuid',
            'surname',
            'othernames',
            'fullname',
            'email',
            'phone',
            'role',
            'status',
        ]);
    }
}
