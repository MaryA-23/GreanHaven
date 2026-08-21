<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\Hostname;
use Illuminate\Support\Facades\DB;  

class AdminDashboardController extends Controller
{
    public function dashboard(Request $request)
{
    $request->validate([
        'uuid' => 'required',
        'website_uuid' => 'required',
    ]);

    $admin = Admin::where('uuid', $request->uuid)->first();

    if (!$admin) {
        return response()->json([
            'status' => 'failed',
            'message' => 'Admin not found'
        ], 404);
    }

    try {

        // Total Tenancies
        $totalTenancies = Hostname::count();

        // Total Users
        $totalUsers = DB::connection('system')
            ->table($request->website_uuid . '.users')
            ->count();

        // Active Users
        $activeUsers = DB::connection('system')
            ->table($request->website_uuid . '.users')
            ->where('status', 'Active')
            ->count();

        // Inactive Users
        $inactiveUsers = DB::connection('system')
            ->table($request->website_uuid . '.users')
            ->where('status', 'Inactive')
            ->count();

        return response()->json([
            'status' => 'success',
            'dashboard' => [
                'total_tenancies' => $totalTenancies,
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'inactive_users' => $inactiveUsers,
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'failed',
            'message' => $e->getMessage()
        ], 500);
    }
}
}
