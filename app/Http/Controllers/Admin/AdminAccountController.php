<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;

class AdminAccountController extends Controller
{
    public function index(Request $request)
    {
        $currentAdmin = $request->user();

        if (
            !($currentAdmin instanceof Admin) ||
            $currentAdmin->role !== 'super_admin'
        ) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Only a Super Admin can view admin accounts.',
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
}