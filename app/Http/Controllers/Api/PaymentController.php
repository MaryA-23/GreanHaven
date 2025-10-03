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
     * Redirect user to Paystack payment page.
     */
     // Initialize a payment and get Paystack authorization URL
    public function initialize(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::with('user')->findOrFail($request->order_id);

        // Prepare payment data for Paystack
        $paymentData = [
            'amount' => $order->total_price * 100, // Paystack expects amount in kobo
            'email' => $order->user->email,
            'metadata' => [
                'order_id' => $order->id
            ]
        ];

        try {
            // Redirect user to Paystack payment page
            return Paystack::getAuthorizationUrl($paymentData)->redirectNow();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Payment initialization failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Paystack callback to verify payment
    public function callback(Request $request)
    {
        $paymentDetails = Paystack::getPaymentData();

        if ($paymentDetails['data']['status'] === 'success') {
            $metadata = $paymentDetails['data']['metadata'];
            $orderId = $metadata['order_id'] ?? null;
            $amount = $paymentDetails['data']['amount'] / 100;

            if ($orderId) {
                // Record payment in payments table
                Payment::create([
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'status' => 'paid',
                    'payment_method' => 'Paystack',
                ]);

                // Optionally, update order status
                $order = Order::find($orderId);
                $order->update(['status' => 'confirmed']);
            }

            return response()->json([
                'message' => 'Payment successful',
                'data' => $paymentDetails
            ]);
        }

        return response()->json(['error' => 'Payment failed'], 400);
    }

    /**
     * Store a newly created payment for an order.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'order_id'       => 'required|exists:orders,id',
            'amount'         => 'required|numeric|min:0',
            'status'         => 'in:unpaid,paid,pending,failed',
            'payment_method' => 'nullable|string',
            'transaction_id' => 'required_if:status,paid|nullable|string', // Enforce for 'paid'
            'paid_at'        => 'nullable|date',
            'notes'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify order ownership
        $order = Order::find($request->order_id);
        if ($order->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized: You do not own this order'], 403);
        }

        // Ensure amount matches order total_price
        if ($request->amount != $order->total_price) {
            return response()->json(['error' => 'Amount must match order total_price'], 400);
        }

        // Auto-set paid_at and validate for 'paid' status
        $data = $request->only(['amount', 'status', 'payment_method', 'transaction_id', 'notes']);
        if ($request->status === 'paid') {
            if (empty($data['transaction_id'])) {
                return response()->json(['error' => 'Transaction ID is required for paid status'], 422);
            }
            $data['paid_at'] = $request->paid_at ?? now(); // Auto-set if not provided
        }

        $payment = Payment::create([
            'order_id'       => $order->id,
            'user_id'        => $user->id,
            'amount'         => $data['amount'],
            'status'         => $data['status'] ?? 'unpaid',
            'payment_method' => $data['payment_method'],
            'transaction_id' => $data['transaction_id'],
            'paid_at' => $request->input('status') === 'paid' ? now() : null,
            'notes'          => $data['notes'],
        ]);

        // Update order status if paid
        if ($data['status'] === 'paid') {
            $order->update(['status' => 'confirmed']);
        
            // If this order came from a vegetable request, update its status too
            if ($order->vegetable_request_id) {
                $order->vegetableRequest()->update(['status' => 'processing']);
            }
        }
        

        // Use Resource for formatted response
        return response()->json([
            'message' => 'Payment created successfully',
            'payment' => new PaymentResource($payment->load('order')), // Wrap in resource
        ], 201);
    }

    /**
     * Display the specified payment.
     */
    public function show(Request $request, Payment $payment): JsonResponse
    {
        // Authorization: user must own the payment
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
        // Authorization: user must own the payment
        if ($payment->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:unpaid,paid,pending,failed',
            'payment_method' => 'nullable|string',
           'transaction_id' => 'required_if:status,paid|nullable|string|unique:payments,transaction_id',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string',
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

        // Optional: Update order status if payment marked paid
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
        // Authorization: user must own the payment
        if ($payment->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payment->delete();

        return response()->json(['message' => 'Payment deleted successfully']);
    }
}
