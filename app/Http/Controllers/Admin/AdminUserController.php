<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $currentAdmin = $request->user();

        if (
            !($currentAdmin instanceof Admin) ||
            !in_array($currentAdmin->role, ['admin', 'super_admin'], true) ||
            $currentAdmin->status !== 'active'
        ) {
            return response()->json([
                'status' => 'failed',
                'message' => 'An active Admin account is required.',
            ], 403);
        }

        $validated = $request->validate([
            'website_uuid' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
        ]);

        try {
            $users = DB::connection('system')
                ->table($validated['website_uuid'] . '.users')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Users fetched successfully',
                'total_users' => $users->count(),
                'users' => AdminUserResource::collection($users),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Admin users fetch failed', [
                'message' => $e->getMessage(),
                'admin_uuid' => $currentAdmin->uuid,
                'website_uuid' => $validated['website_uuid'],
            ]);

            return response()->json([
                'status' => 'failed',
                'message' => 'Unable to fetch users.',
            ], 500);
        }
    }

    public function show(Request $request, $user_id)
    {
        $currentAdmin = $request->user();

        if (
            !($currentAdmin instanceof Admin) ||
            !in_array($currentAdmin->role, ['admin', 'super_admin'], true) ||
            $currentAdmin->status !== 'active'
        ) {
            return response()->json([
                'status' => 'failed',
                'message' => 'An active Admin account is required.',
            ], 403);
        }

        $validated = $request->validate([
            'website_uuid' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
        ]);

        if (!ctype_digit((string) $user_id) || (int) $user_id < 1) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid user ID.',
            ], 422);
        }

        try {
            $user = DB::connection('system')
                ->table($validated['website_uuid'] . '.users')
                ->where('id', (int) $user_id)
                ->first();

            if (!$user) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'User not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User fetched successfully',
                'data' => new AdminUserResource($user),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Admin user fetch failed', [
                'message' => $e->getMessage(),
                'admin_uuid' => $currentAdmin->uuid,
                'website_uuid' => $validated['website_uuid'],
                'user_id' => (int) $user_id,
            ]);

            return response()->json([
                'status' => 'failed',
                'message' => 'Unable to fetch user.',
            ], 500);
        }
    }
}
