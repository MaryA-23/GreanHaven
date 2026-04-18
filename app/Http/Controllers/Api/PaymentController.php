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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Display a listing of the authenticated user's payments.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Payment::with('order'); // eager load order

        if ($user->role === 'admin') {
            // Admin sees all payments
        } elseif ($user->role === 'company') {
            $query->whereHas('order', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        } else {
            $query->where('user_id', $user->id);
        }

        // Optional: filter by date
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
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
            Log::error('Paystack initialize failed', ['message' => $e->getMessage()]);
            return response()->json([
                'error' => 'Payment initialization failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record a new payment.
     */
    private function recordPayment(Order $order, float $amount, string $reference, string $method = 'Paystack')
    {
        // Prevent duplicate payments (unique gateway_reference)
        $existing = Payment::where('gateway_reference', $reference)->first();
        if ($existing) {
            return $existing;
        }

        $payment = Payment::create([
            'order_id'          => $order->id,
            'user_id'           => $order->user_id,
            'amount'            => $amount,
            'status'            => 'paid',
            'payment_method'    => $method,
            'gateway_reference' => $reference,
            'paid_at'           => now(),
        ]);

        // Update order
       if ($order->status !== 'confirmed') {
        $order->update(['status' => 'confirmed']);

        foreach ($order->items as $item) {
            $item->product->decrement('quantity', $item->quantity);
        }
        }

    
        return $payment;
    }

    /**
     * Paystack callback to verify payment.
     *
     * IMPORTANT:
     * - This method first extracts the 'reference' value (query param or JSON payload).
     * - If there's no reference, it returns a clear error (so we never call Paystack verify with empty string).
     * - It then calls Paystack verify endpoint with the reference and processes the verified response.
     */
    public function callback(Request $request)
    {
        // 1) try to get reference from query param (redirect), fallback to JSON payload
        $reference = $request->query('reference') 
                     ?? $request->input('reference') 
                     ?? data_get($request->all(), 'data.reference');

        if (!$reference) {
            // No reference provided — bail out
            Log::warning('Paystack callback called without reference', ['request' => $request->all()]);
            return response()->json(['error' => 'No transaction reference provided.'], 400);
        }

        // 2) verify transaction with Paystack using HTTP (explicit and clear)
        try {
            $paystackSecret = env('PAYSTACK_SECRET_KEY') ?: config('services.paystack.secret') ?? null;
            if (!$paystackSecret) {
                Log::error('Paystack secret key not configured.');
                return response()->json(['error' => 'Payment provider secret not configured.'], 500);
            }

            $response = Http::withToken($paystackSecret)
                ->get("https://api.paystack.co/transaction/verify/{$reference}");

        } catch (\Throwable $e) {
            Log::error('Paystack verify HTTP call failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to verify payment', 'message' => $e->getMessage()], 500);
        }

        if ($response->failed()) {
            // Paystack returned an error (400/404/whatever)
            Log::warning('Paystack verify returned failed', ['status' => $response->status(), 'body' => $response->body()]);
            return response()->json(['error' => 'Payment verification failed', 'detail' => $response->body()], $response->status());
        }

        $body = $response->json();
        $data = $body['data'] ?? null;

        if (! $data || ! ($body['status'] ?? false)) {
            Log::warning('Paystack verify returned no data or status false', ['body' => $body]);
            return response()->json(['error' => 'Payment verification returned invalid data', 'detail' => $body], 400);
        }

        // 3) process payment data
        $orderId   = $data['metadata']['order_id'] ?? null;
        $amount    = $data['amount'] / 100;
        $reference = $data['reference']; // the same reference we verified

        if (! $orderId) {
            Log::warning('Paystack verify returned no order_id in metadata', ['data' => $data]);
            // optionally record the payment without order, or just return error
            return response()->json(['error' => 'No order_id found in payment metadata.'], 400);
        }

        $order = Order::find($orderId);
        if (! $order) {
            Log::warning('Order not found for payment callback', ['order_id' => $orderId]);
            return response()->json(['error' => 'Order not found.'], 404);
        }

        // record payment if not exists
        $payment = Payment::where('gateway_reference', $reference)->first();
        if (! $payment) {
            $payment = $this->recordPayment($order, $amount, $reference, 'Paystack');
        } else {
            // ensure status is paid and paid_at set
            if ($payment->status !== 'paid') {
                $payment->update(['status' => 'paid', 'paid_at' => now()]);
            }
            // ensure order status is confirmed
            $order->update(['status' => 'confirmed']);
            if ($order->Product_request_id) {
                $order->ProductRequest()->update(['status' => 'processing']);
            }
        }

        return response()->json([
            'message' => 'Payment verified and recorded',
            'payment' => $payment ? new PaymentResource($payment->load('order')) : null,
            'raw' => $data,
        ]);
    }

    /**
     * Store a newly created payment for an order (manual entry).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'order_id'         => 'required|exists:orders,id',
            'user_id'          => 'required|exists:users,id',
            'amount'           => 'required|numeric|min:0',
            'status'           => 'in:unpaid,paid,pending,failed',
            'payment_method'   => 'nullable|string',
            'gateway_reference'=> 'nullable|string|unique:payments,gateway_reference',
            'paid_at'          => 'nullable|date',
            'notes'            => 'nullable|string',
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

        $data = $request->only(['amount', 'status', 'payment_method', 'gateway_reference', 'notes']);
        if ($request->status === 'paid') {
            $data['paid_at'] = $request->paid_at ?? now();
        }

        $payment = Payment::create([
            'order_id'          => $order->id,
            'user_id'           => $user->id,
            'amount'            => $data['amount'],
            'status'            => $data['status'] ?? 'unpaid',
            'payment_method'    => $data['payment_method'],
            'gateway_reference' => $data['gateway_reference'] ?? null,
            'paid_at'           => $data['paid_at'] ?? null,
            'notes'             => $data['notes'],
        ]);

        if ($data['status'] === 'paid') {
            $order->update(['status' => 'confirmed']);
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
            'amount'           => 'sometimes|numeric|min:0',
            'status'           => 'sometimes|in:unpaid,paid,pending,failed',
            'payment_method'   => 'nullable|string',
            'gateway_reference'=> 'required_if:status,paid|nullable|string|unique:payments,gateway_reference,' . $payment->id,
            'paid_at'          => 'nullable|date',
            'notes'            => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payment->update($request->only([
            'amount',
            'status',
            'payment_method',
            'gateway_reference',
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
