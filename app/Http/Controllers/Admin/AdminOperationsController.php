<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOperationsController extends Controller
{
    public function __construct()
    {
        $this->middleware([
            'auth:sanctum',
            'role:admin,super_admin',
        ]);
    }

    public function status(int $id)
    {
        $company = Company::findOrFail($id);

        return response()->json([
            'is_suspended' => (bool) $company->is_suspended,
            'reason' => $company->suspension_reason,
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'is_suspended' => ['required', 'boolean'],
            'reason' => [
                'required_if:is_suspended,true',
                'nullable',
                'string',
                'min:3',
                'max:1000',
            ],
        ]);

        return DB::transaction(function () use ($request, $id, $data) {
            $company = Company::lockForUpdate()->findOrFail($id);
            $suspended = (bool) $data['is_suspended'];

            if ((bool) $company->is_suspended !== $suspended) {
                $company->is_suspended = $suspended;

                $company->suspension_reason = $suspended
                    ? trim($data['reason'])
                    : null;

                $company->save();

                if ($suspended) {
                    $users = $company->users()
                        ->where('role', 'company')
                        ->get();

                    foreach ($users as $user) {
                        $user->tokens()->delete();
                    }
                }

                DB::table('admin_alerts')->insert([
                    'title' => $suspended
                        ? 'Tenant suspended'
                        : 'Tenant reactivated',

                    'message' => $company->name
                        . ' — changed by administrator #'
                        . $request->user()->id,

                    'path' => '/super-admin/tenants',
                    'created_at' => now(),
                ]);
            }

            return response()->json([
                'is_suspended' => (bool) $company->is_suspended,
                'reason' => $company->suspension_reason,
            ]);
        });
    }

    private function adminId(Request $request): int
    {
        abort_unless($request->user() instanceof Admin, 403);

        return (int) $request->user()->id;
    }

    public function notifications(Request $request)
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $adminId = $this->adminId($request);

        $query = DB::table('admin_alerts as alerts')
            ->leftJoin(
                'admin_alert_reads as reads',
                function ($join) use ($adminId) {
                    $join->on('reads.alert_id', '=', 'alerts.id')
                        ->where('reads.admin_id', $adminId);
                }
            );

        $unread = (clone $query)
            ->whereNull('reads.read_at')
            ->count();

        $items = $query
            ->select('alerts.*', 'reads.read_at')
            ->orderByDesc('alerts.id')
            ->paginate(10);

        return response()->json([
            'unread' => $unread,
            'items' => $items,
        ]);
    }

    public function readNotification(Request $request, int $id)
    {
        $adminId = $this->adminId($request);

        abort_unless(
            DB::table('admin_alerts')->where('id', $id)->exists(),
            404
        );

        DB::table('admin_alert_reads')->insertOrIgnore([
            'admin_id' => $adminId,
            'alert_id' => $id,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
        ]);
    }
}