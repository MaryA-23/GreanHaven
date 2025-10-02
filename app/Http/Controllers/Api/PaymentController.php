<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    // Create a payment record for an order (initially unpaid)
    public function store(Request $request, Order $order): JsonResponse
    {
        // Authorization: Ensure user owns the order
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ensure amount matches order total (for integrity)
        if ($request->amount != $order->total_price) {
            return response()->json(['error' => 'Amount must match order total'], 400);
        }

        try {
            DB::beginTransaction();

            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'amount' => $request->amount,
                'status' => 'unpaid', // Default
                'payment_method' => $request->payment_method ?? null, // Optional for now
            ]);

            // Update order status if needed (e.g., mark as 'processing')
            $order->update(['status' => 'processing']);

            DB::commit();

            return response()->json([
                'message' => 'Payment record created successfully',
                'payment' => $payment->load('order'), // Eager load for frontend
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create payment'], 500);
        }
    }

    // Get payment for a specific order
    public function show(Order $order): JsonResponse
    {
        if ($order->user_id !== request()->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payment = $order->payment ?? null;

        return response()->json([
            'payment' => $payment,
            'order' => $order,
        ]);
    }

    // Update payment status (e.g., mark as paid after manual verification or webhook)
    public function updateStatus(Request $request, Payment $payment): JsonResponse
    {
        // Authorization: User or admin
        if ($payment->order->user_id !== $request->user()->id && !$request->user()->isAdmin()) { // Assuming you have an isAdmin() method
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:paid,unpaid,pending,failed',
            'transaction_id' => 'nullable|string', // For gateways
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payment->update([
            'status' => $request->status,
            'transaction_id' => $request->transaction_id,
            'paid_at' => $request->status === 'paid' ? now() : null,
            'notes' => $request->notes,
        ]);

        // Optional: Update order status (e.g., 'completed' if paid)
        if ($request->status === 'paid') {
            $payment->order->update(['status' => 'completed']);
        }

        return response()->json([
            'message' => 'Payment status updated',
            'payment' => $payment->fresh()->load('order'),
        ]);
    }

    // List user's payments (with orders)
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::with(['order', 'user'])
            ->where('user_id', $request->user()->id)
            ->paginate(10); // Pagination for Angular

        return response()->json($payments);
    }
}
