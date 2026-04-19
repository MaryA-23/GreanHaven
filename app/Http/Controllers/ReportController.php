<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum'); // Only authenticated users
        $this->middleware('can:viewReports'); // Admin/superadmin role
    }

    // Orders summary with optional date/company filter
     public function ordersSummary(Request $request)
    {
        $user = $request->user();
        $query = Order::query();

        // Restrict based on role
        if ($user->role === 'user') {
            $query->where('user_id', $user->id);
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $summary = [
            'total_orders' => $query->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'confirmed' => (clone $query)->where('status', 'confirmed')->count(),
            'delivered' => (clone $query)->where('status', 'delivered')->count(),
        ];

        return response()->json($summary);
    }

    // Sales summary with optional date/company filter
     public function salesSummary(Request $request)
    {
        $user = $request->user();
        $query = Order::query();

        if ($user->role === 'user') {
            $query->where('user_id', $user->id);
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return response()->json([
            'total_sales' => $query->sum('total_price'),
            'total_orders' => $query->count(),
        ]);
    }


    // Payments summary with optional date/company filter
     public function paymentsSummary(Request $request)
    {
        $user = $request->user();
        $query = Payment::query();

        if ($user->role === 'user') {
            $query->whereHas('order', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $summary = [
            'paid' => (clone $query)->where('status', 'paid')->count(),
            'unpaid' => (clone $query)->where('status', 'unpaid')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
        ];

        return response()->json($summary);
    }
}
