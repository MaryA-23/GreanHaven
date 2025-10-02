<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    // List logged-in user's payments with order info
    public function index(Request $request)
    {
        $payments = Payment::with(['order'])
            ->where('user_id', $request->user()->id)
            ->paginate(10);

        return PaymentResource::collection($payments);
    }

    // Create a payment record for an order (initially unpaid)
    public function store(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->amount != $order->total_price) {
            return response()->json(['error' => 'Amount must match order total'], 400);
        }

        try {
            DB::beginTransaction();

            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'amount' => $request->amount,
                'status' => 'unpaid',
                'payment_method' => $request->payment_method,
            ]);

            // Optional: update order status
            $order->update(['status' => 'processing']);

            DB::commit();

            return new PaymentResource($payment->load('order'));

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create payment'], 500);
        }
    }

    // Show payment for a specific order
    public function show(Order $order)
    {
        if ($order->user_id !== request()->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payment = $order->payment;

        return new PaymentResource($payment ? $payment->load('order') : null);
    }

    // Update payment status (paid/unpaid etc.)
    public function updateStatus(Request $request, Payment $payment)
    {
        if ($payment->order->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:paid,unpaid,pending,failed',
            'transaction_id' => 'nullable|string',
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

        if ($request->status === 'paid') {
            $payment->order->update(['status' => 'completed']);
        }

        return new PaymentResource($payment->fresh()->load('order'));
    }
}
