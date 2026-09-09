<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');

        $this->middleware('role:admin,super_admin')
            ->only(['ordersSummary', 'paymentsSummary']);

        $this->middleware('role:admin,super_admin,company,user')
            ->only(['salesSummary']);
    }

    public function ordersSummary(Request $request)
    {
        $dates = $this->validatedDates($request);

        $query = Order::query();

        if (!empty($dates['date_from'])) {
            $query->whereDate(
                'created_at',
                '>=',
                $dates['date_from']
            );
        }

        if (!empty($dates['date_to'])) {
            $query->whereDate(
                'created_at',
                '<=',
                $dates['date_to']
            );
        }

        return response()->json([
            'total_orders' => (clone $query)->count(),

            'pending' => (clone $query)
                ->where('status', 'pending')
                ->count(),

            'confirmed' => (clone $query)
                ->where('status', 'confirmed')
                ->count(),

            'delivered' => (clone $query)
                ->where('status', 'delivered')
                ->count(),
        ]);
    }

    public function salesSummary(Request $request)
    {
        $dates = $this->validatedDates($request);
        $user = $request->user();

        $query = Order::query();

        if ($user->role === 'user') {
            $query->where('user_id', $user->id);
        } elseif ($user->role === 'company') {
            if (!$user->company_id) {
                return response()->json([
                    'message' => 'Company account required.',
                ], 403);
            }

            $query->where('company_id', $user->company_id);
        }

        if (!empty($dates['date_from'])) {
            $query->whereDate(
                'created_at',
                '>=',
                $dates['date_from']
            );
        }

        if (!empty($dates['date_to'])) {
            $query->whereDate(
                'created_at',
                '<=',
                $dates['date_to']
            );
        }

        // Preserves the existing report definition:
        // total order value for the selected period.
        return response()->json([
            'total_sales' => (float) (clone $query)
                ->sum('total_price'),

            'total_orders' => (clone $query)->count(),
        ]);
    }

    public function paymentsSummary(Request $request)
    {
        $dates = $this->validatedDates($request);

        $query = Payment::query();

        if (!empty($dates['date_from'])) {
            $query->whereDate(
                'created_at',
                '>=',
                $dates['date_from']
            );
        }

        if (!empty($dates['date_to'])) {
            $query->whereDate(
                'created_at',
                '<=',
                $dates['date_to']
            );
        }

        return response()->json([
            'paid' => (clone $query)
                ->where('status', 'paid')
                ->count(),

            'unpaid' => (clone $query)
                ->where('status', 'unpaid')
                ->count(),

            'pending' => (clone $query)
                ->where('status', 'pending')
                ->count(),

            'failed' => (clone $query)
                ->where('status', 'failed')
                ->count(),
        ]);
    }

    private function validatedDates(Request $request): array
    {
        $endDateRules = [
            'nullable',
            'date_format:Y-m-d',
        ];

        if ($request->filled('date_from')) {
            $endDateRules[] = 'after_or_equal:date_from';
        }

        return $request->validate([
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'date_to' => $endDateRules,
        ]);
    }
}