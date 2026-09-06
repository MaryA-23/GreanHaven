<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Payment;
use Illuminate\Http\Request;

class TenantDashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();

        $companyId = $user->company_id;

        // Safety check
        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant company not found.',
            ], 403);
        }

        // Products are currently shared platform products
        $totalProducts = Product::count();

        // Only orders belonging to this tenant/company
        $totalOrders = Order::where(
            'company_id',
            $companyId
        )->count();

        $pendingOrders = Order::where(
            'company_id',
            $companyId
        )
        ->where('status', 'pending_payment')
        ->count();

        $completedOrders = Order::where(
            'company_id',
            $companyId
        )
        ->where('status', 'completed')
        ->count();

        // Payments belonging to this company's orders
        $totalPayments = Payment::whereHas(
            'order',
            function ($query) use ($companyId) {
                $query->where(
                    'company_id',
                    $companyId
                );
            }
        )->count();

        return response()->json([
            'success' => true,

            'data' => [
                'total_products' => $totalProducts,
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'completed_orders' => $completedOrders,
                'total_payments' => $totalPayments,
            ],
        ]);
    }
}