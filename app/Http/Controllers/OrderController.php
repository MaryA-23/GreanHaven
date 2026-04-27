<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Services\InventoryService;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * User: view own orders.
     * Company: view company orders.
     * Admin: view all orders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Order::with(['items.product', 'payment', 'user', 'company'])
            ->latest();

        if ($user->role === 'company') {
            $query->where('company_id', $user->company_id);
        }

        if ($user->role === 'user') {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * User only: create order.
     * Stock is checked but NOT deducted here.
     * Stock deduction happens only after successful payment callback.
     */
   public function store(Request $request, InventoryService $inventoryService): JsonResponse
    {
        if ($request->user()->role !== 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Only users can create orders.',
            ], 403);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();

        $existingOrder = Order::where('user_id', $user->id)
            ->where('status', 'pending_payment')
            ->whereHas('payment', function ($query) {
                $query->where('status', 'pending')
                    ->where('expires_at', '>', now());
            })
            ->first();

        if ($existingOrder) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending unpaid order. Please complete payment or wait for it to expire.',
                'data' => $existingOrder->load(['items.product', 'payment']),
            ], 409);
        }

        $mergedItems = [];

        foreach ($validated['items'] as $item) {
            $productId = $item['product_id'];
            $quantity = (int) $item['quantity'];

            $mergedItems[$productId] = ($mergedItems[$productId] ?? 0) + $quantity;
        }

        DB::beginTransaction();

        try {
            $total = 0;

            $order = Order::create([
                'user_id' => $user->id,
                'company_id' => $user->company_id ?? null,
                'status' => 'pending_payment',
                'total_price' => 0,
            ]);

            foreach ($mergedItems as $productId => $quantity) {
            $product = Product::where('id', $productId)
                ->lockForUpdate()
                ->firstOrFail();

            $inventoryService->syncStatus($product);
            $product->save();

            $inventoryService->checkStock($product, $quantity);

            $subtotal = $product->price * $quantity;

            $order->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $product->price,
                'subtotal' => $subtotal,
                ]);

                $total += $subtotal;
            }

            $order->update([
                'total_price' => $total,
            ]);

            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'amount' => $total,
                'status' => 'pending',
                'payment_method' => 'paystack',
                'gateway_reference' => null,
                'paid_at' => null,
                'expires_at' => now()->addMinutes(15),
                'expired_at' => null,
            ]);

            DB::commit();

            $order = $order->fresh(['items.product', 'payment', 'user']);

            try {
                $paymentUrl = url("/payment/initialize?order_id=" . $order->id);

                Mail::send('emails.payment_pending', [
                    'order' => $order,
                    'payment' => $payment,
                    'paymentUrl' => $paymentUrl,
                ], function ($message) use ($order) {
                    $message->to($order->user->email);
                    $message->subject('Greenhaven Order Payment Details');
                });
            } catch (\Exception $e) {
                Log::error('Payment pending email failed', [
                    'message' => $e->getMessage(),
                    'order_id' => $order->id,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully. Payment is pending.',
                'data' => $order,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Order creation failed', [
                'message' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Order creation failed.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Show single order depending on role.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $order = Order::with(['items.product', 'payment', 'user', 'company'])
            ->findOrFail($id);

        if ($user->role === 'user' && $order->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($user->role === 'company' && $order->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * User can cancel only unpaid pending orders.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Only users can cancel their own unpaid orders.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $order = Order::with('payment')
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($order->status, ['pending_payment'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending unpaid orders can be cancelled.',
                ], 400);
            }

            if ($order->payment && $order->payment->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Paid orders cannot be cancelled here.',
                ], 400);
            }

            $order->update([
                'status' => 'cancelled',
            ]);

            if ($order->payment && $order->payment->status === 'pending') {
                $order->payment->update([
                    'status' => 'cancelled',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully.',
                'data' => $order->fresh(['items.product', 'payment']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Order cancellation failed.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Admin: mark paid order as processing.
     */
    public function markAsProcessing(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can update fulfilment status.',
            ], 403);
        }

        $order = Order::with('payment')->findOrFail($id);

        if ($order->status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Only paid orders can be moved to processing.',
            ], 400);
        }

        $order->update([
            'status' => 'processing',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order marked as processing.',
            'data' => $order->fresh(['items.product', 'payment']),
        ]);
    }

    /**
     * Admin: complete processing order.
     */
    public function markAsCompleted(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can complete orders.',
            ], 403);
        }

        $order = Order::findOrFail($id);

        if ($order->status !== 'processing') {
            return response()->json([
                'success' => false,
                'message' => 'Only processing orders can be completed.',
            ], 400);
        }

        $order->update([
            'status' => 'completed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order completed successfully.',
            'data' => $order->fresh(['items.product', 'payment']),
        ]);
    }

    /**
     * Admin: cancel unpaid/problematic order.
     */
    public function adminCancel(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can cancel orders.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $order = Order::with('payment')
                ->lockForUpdate()
                ->findOrFail($id);

            if (in_array($order->status, ['paid', 'processing', 'completed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paid, processing, or completed orders should not be cancelled from this endpoint.',
                ], 400);
            }

            $order->update([
                'status' => 'cancelled',
            ]);

            if ($order->payment && $order->payment->status === 'pending') {
                $order->payment->update([
                    'status' => 'cancelled',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully.',
                'data' => $order->fresh(['items.product', 'payment']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Admin cancellation failed.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Admin: expire unpaid order manually.
     */
    public function adminExpire(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can expire orders.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $order = Order::with('payment')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($order->status !== 'pending_payment') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending payment orders can be expired.',
                ], 400);
            }

            $order->update([
                'status' => 'expired',
            ]);

            if ($order->payment && $order->payment->status === 'pending') {
                $order->payment->update([
                    'status' => 'expired',
                    'expired_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order expired successfully.',
                'data' => $order->fresh(['items.product', 'payment']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Order expiry failed.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}