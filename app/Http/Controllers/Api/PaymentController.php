<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Order;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Unicodeveloper\Paystack\Facades\Paystack;

class PaymentController extends Controller
{
    /**
     * Display a listing of the authenticated user's payments.
     */
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::with('order')
            ->where('user_id', $request->user()->id)
            ->paginate(10);

        return response()->json($payments);
    }

    /**
     * Initialize a payment and get Paystack authorization URL.
     */
    public function initialize(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::with('user')->findOrFail($request->order_id);

        $paymentData = [
            'amount' => $order->total_price * 100, // Paystack expects amount in kobo
            'email' => $order->user->email,
            'metadata' => ['order_id' => $order->id],
        ];

        try {
            $authorization = Paystack::getAuthorizationUrl($paymentData);

            return response()->json([
                'authorization_url' => $authorization->url
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Payment initialization failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Paystack callback to verify payment.
     */
    // Paystack callback to verify payment
   // Paystack callback to verify payment

   private function recordPayment($order, $amount, $transactionId, $method = 'Paystack')
{
    // Prevent duplicate payments
    if (Payment::where('transaction_id', $transactionId)->exists()) {
        return;
    }

    Payment::create([
        'order_id'       => $order->id,
        'user_id'        => $order->user_id,
        'amount'         => $amount,
        'status'         => 'paid',
        'payment_method' => $method,
        'transaction_id' => $transactionId,
        'paid_at'        => now(),
    ]);

    // ✅ Update order
    $order->update(['status' => 'confirmed']);

    // ✅ If linked to vegetable request
    if ($order->vegetable_request_id) {
        $order->vegetableRequest()->update(['status' => 'processing']);
    }
}
  public function callback(Request $request)
{
    $paymentDetails = Paystack::getPaymentData();
    $data = $paymentDetails['data'] ?? null; // ✅ safe shorthand

    if ($data && $data['status'] === 'success') {
        $orderId       = $data['metadata']['order_id'] ?? null;
        $amount        = $data['amount'] / 100;
        $transactionId = $data['reference']; // ✅ unique Paystack reference

        if ($orderId) {
            $order = Order::find($orderId);

            // prevent duplicate transactions
            $existingPayment = Payment::where('transaction_id', $transactionId)->first();
            if (!$existingPayment) {
                $this->recordPayment($order, $amount, $transactionId, 'Paystack');
            }

            // update order status
            $order->update(['status' => 'confirmed']);

            // if linked to a vegetable request, update it too
            if ($order->vegetable_request_id) {
                $order->vegetableRequest()->update(['status' => 'processing']);
            }
        }

        return response()->json([
            'message' => 'Payment successful',
            'data'    => $data
        ]);
    }

    return response()->json(['error' => 'Payment failed'], 400);
}



    /**
     * Store a newly created payment for an order (manual entry).
     */
   public function store(Request $request): JsonResponse
{
    $user = $request->user();

    $validator = Validator::make($request->all(), [
        'order_id'       => 'required|exists:orders,id',
        'amount'         => 'required|numeric|min:0',
        'status'         => 'in:unpaid,paid,pending,failed',
        'payment_method' => 'nullable|string',
        'transaction_id' => 'nullable|string|unique:payments,transaction_id', // not strictly required
        'paid_at'        => 'nullable|date',
        'notes'          => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $order = Order::find($request->order_id);
    if ($order->user_id !== $user->id) {
        return response()->json(['error' => 'Unauthorized: You do not own this order'], 403);
    }

    if ($request->amount != $order->total_price) {
        return response()->json(['error' => 'Amount must match order total_price'], 400);
    }

    $data = $request->only(['amount', 'status', 'payment_method', 'transaction_id', 'notes']);
    if ($request->status === 'paid') {
        $data['paid_at'] = $request->paid_at ?? now();
    }

    $payment = Payment::create([
        'order_id'       => $order->id,
        'user_id'        => $user->id,
        'amount'         => $data['amount'],
        'status'         => $data['status'] ?? 'unpaid',
        'payment_method' => $data['payment_method'],
        'transaction_id' => $data['transaction_id'] ?? null, // allow null here
        'paid_at'        => $data['paid_at'] ?? null,
        'notes'          => $data['notes'],
    ]);

    if ($data['status'] === 'paid') {
        $order->update(['status' => 'confirmed']);

        if ($order->vegetable_request_id) {
            $order->vegetableRequest()->update(['status' => 'processing']);
        }
    }

    return response()->json([
        'message' => 'Payment created successfully',
        'payment' => new PaymentResource($payment->load('order')),
    ], 201);
}


    /**
     * Display the specified payment.
     */
    public function show(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($payment->load('order'));
    }

    /**
     * Update the specified payment.
     */
    public function update(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount'         => 'sometimes|numeric|min:0',
            'status'         => 'sometimes|in:unpaid,paid,pending,failed',
            'payment_method' => 'nullable|string',
            'transaction_id' => 'required_if:status,paid|nullable|string|unique:payments,transaction_id,' . $payment->id,
            'paid_at'        => 'nullable|date',
            'notes'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payment->update($request->only([
            'amount',
            'status',
            'payment_method',
            'transaction_id',
            'paid_at',
            'notes',
        ]));

        if ($request->has('status') && $request->status === 'paid') {
            $payment->order->update(['status' => 'completed']);
        }

        return response()->json([
            'message' => 'Payment updated successfully',
            'payment' => $payment->fresh()->load('order'),
        ]);
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payment->delete();

        return response()->json(['message' => 'Payment deleted successfully']);
    }
}
