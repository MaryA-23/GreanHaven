<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'overview' => $this->overview(),
            'products' => $this->productStats(),
            'sales' => $this->salesStats(),
            'top_products' => $this->topProducts(),
            'recent_orders' => $this->recentOrders(),
            'low_stock_products' => $this->lowStockProducts(),
        ]);
    }

    private function overview()
    {
        return [
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'confirmed_orders' => Order::where('status', 'confirmed')->count(),
             'total_payments' => (float) Payment::sum('amount'),
        ];
    }

    private function productStats()
    {
        return [
            'total_products' => Product::count(),
            'out_of_stock' => Product::where('status', 'out_of_stock')->count(),
            'active_products' => Product::where('status', 'active')->count(),
            'inactive_products' => Product::where('status', 'inactive')->count(),
        ];
    }

    private function salesStats()
    {
        return [
            'today_revenue' => Payment::whereDate('created_at', today())->sum('amount'),
            'weekly_revenue' => Payment::whereBetween('created_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek()
            ])->sum('amount'),
            'monthly_revenue' => Payment::whereMonth('created_at', now()->month)->sum('amount'),
        ];
    }

    private function topProducts()
    {
        return DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                'products.price',
                DB::raw('SUM(order_items.quantity) as total_sold')
            )
            ->groupBy('products.id', 'products.name', 'products.price')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();
    }
    private function recentOrders()
    {
        return Order::with(['user', 'payment'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                   'customer' => $order->user->name ?? 'N/A',,
                    'total' => $order->total_price,
                    'status' => $order->status,
                    'payment_status' => $order->payment->status ?? 'unpaid',
                    'date' => $order->created_at->format('Y-m-d H:i'),
                ];
            });
    }

    private function lowStockProducts()
    {
        return Product::whereColumn('quantity', '<=', 'low_stock_threshold')
            ->orwhere('quantity', '>', 0)
            ->get(['id', 'name', 'quantity', 'low_stock_threshold']);
    }

}