<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Order;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Unicodeveloper\Paystack\Facades\Paystack;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;  
use App\Mail\PaymentSuccessMail;

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

        $user = $request->user();

        $order = Order::with(['user', 'payment'])
            ->where('id', $request->order_id)
            ->firstOrFail();

        if ((int) $order->user_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized payment attempt.'
            ], 403);
        }

        if ($order->status !== 'pending_payment') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending payment orders can be paid.'
            ], 400);
        }

        $payment = $order->payment;

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment record not found for this order.'
            ], 404);
        }

        if ($payment->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'This order has already been paid.'
            ], 400);
        }

        if (
            $payment->status === 'expired' ||
            ($payment->expires_at && now()->greaterThan($payment->expires_at))
        ) {
            $payment->update([
                'status' => 'expired',
                'expired_at' => now(),
            ]);

            $order->update([
                'status' => 'expired',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment link has expired. Please create a new order.'
            ], 400);
        }

        $paymentData = [
            'amount' => $order->total_price * 100,
            'email' => $order->user->email,
            'metadata' => [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
            ],
            'callback_url' => url('/api/payments/paystack/callback'),
        ];

        try {
            $authorization = Paystack::getAuthorizationUrl($paymentData);

            $paymentUrl = $authorization->url;

            Log::info('Payment URL sent to email', [
                'payment_url' => $paymentUrl,
                'order_id' => $order->id,
                'user_email' => $order->user->email,
            ]);
            try {
                Mail::send('emails.payment_pending', [
                    'order' => $order,
                    'payment' => $payment,
                    'paymentUrl' => $paymentUrl,
                ], function ($message) use ($order) {
                    $message->to($order->user->email);
                    $message->subject('Greenhaven Order Payment Details');
                });
            } catch (\Exception $mailException) {
                Log::error('Payment email failed', [
                    'message' => $mailException->getMessage(),
                    'order_id' => $order->id,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment link generated. If email is configured correctly, it has been sent to your mail.',
                'authorization_url' => $paymentUrl,
                'payment_expires_at' => $payment->expires_at,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Paystack initialize failed', [
                'message' => $e->getMessage(),
                'order_id' => $order->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment initialization failed.',
                'error' => $e->getMessage()
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
    public function callback(Request $request, InventoryService $inventoryService)
    {
        $reference = $request->query('reference');

        if (!$reference) {
            return response()->json([
                'error' => 'Payment reference is missing'
            ], 400);
        }

        try {
            $paymentDetails = Http::withToken(env('PAYSTACK_SECRET_KEY'))
            ->get("https://api.paystack.co/transaction/verify/{$reference}")
            ->json();

             if (!$paymentDetails || ($paymentDetails['status'] ?? false) !== true || !isset($paymentDetails['data'])) {
                    return response()->json([
                        'error' => 'Unable to verify payment',
                        'details' => $paymentDetails,
                    ], 400);
                }

               $data = $paymentDetails['data'];

            if ($data['status'] !== 'success') {
                return response()->json([
                    'error' => 'Payment was not successful'
                ], 400);
            }

            $orderId = $data['metadata']['order_id'] ?? null;
            $userId = $data['metadata']['user_id'] ?? null;

            if (!$orderId || !$userId) {
                return response()->json([
                    'error' => 'Invalid payment metadata'
                ], 400);
            }

            DB::beginTransaction();

            $order = Order::with(['items.product', 'payment'])
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ((int) $order->user_id !== (int) $userId) {
                DB::rollBack();

                return response()->json([
                    'error' => 'Payment does not belong to this user'
                ], 403);
            }

            $payment = Payment::where('order_id', $order->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                DB::rollBack();

                return response()->json([
                    'error' => 'Payment record not found'
                ], 404);
            }

            if ($payment->status === 'paid') {
                DB::rollBack();

                return response()->json([
                    'message' => 'Payment already verified',
                    'order' => $order,
                    'payment' => $payment
                ], 200);
            }

            if (
                $payment->status === 'expired' ||
                ($payment->expires_at && now()->greaterThan($payment->expires_at))
            ) {
                $payment->update([
                    'status' => 'expired',
                    'expired_at' => now(),
                ]);

                $order->update([
                    'status' => 'expired',
                ]);

                DB::commit();

                return response()->json([
                    'error' => 'Payment has expired. Please create a new order.'
                ], 400);
            }

            $paidAmount = $data['amount'] / 100;

            if ((float) $paidAmount !== (float) $order->total_price) {
                DB::rollBack();

                return response()->json([
                    'error' => 'Payment amount does not match order amount'
                ], 400);
            }

           foreach ($order->items as $item) {
            $product = Product::where('id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if (!$product) {
                DB::rollBack();

                return response()->json([
                    'error' => 'Product not found for order item'
                ], 404);
            }

            $inventoryService->deductStock($product, $item->quantity);
        }

            $payment->update([
                'amount' => $paidAmount,
                'status' => 'paid',
                'payment_method' => 'paystack',
                'gateway_reference' => $data['reference'],
                'paid_at' => now(),
            ]);

            $order->update([
                'status' => 'paid',
            ]);

             DB::commit();

                       try {
                Mail::to($order->user->email)->send(
                    new PaymentSuccessMail(
                        $order->fresh(['items.product', 'payment', 'user']),
                        $payment->fresh(),
                        $order->user
                    )
                );
            } catch (\Exception $mailException) {
                Log::error('Payment success email failed', [
                    'message' => $mailException->getMessage(),
                    'order_id' => $order->id,
                ]);
            }
            return response()->json([
                'message' => 'Payment verified successfully',
                'order' => $order->fresh(['items.product', 'payment']),
                'payment' => $payment->fresh(),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Paystack callback error', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return response()->json([
                'error' => 'Payment verification failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Store a newly created payment for an order (manual entry).
     */
   public function store(Request $request): JsonResponse
    {
        $user = $request->user();

         if ($request->gateway_reference) {
        $exists = Payment::where('gateway_reference', $request->gateway_reference)->first();

        if ($exists) {
            return response()->json([
                'error' => 'This gateway reference has already been used'
            ], 409);
        }
    }

        $request->validate([
            'order_id'         => 'required|exists:orders,id',
            'user_id'          => 'required|exists:users,id',
            'amount'           => 'required|numeric|min:0',
            'payment_method'   => 'nullable|string',
            'gateway_reference'=> 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        $order = Order::find($request->order_id);

        if ($order->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($request->amount != $order->total_price) {
            return response()->json(['error' => 'Amount mismatch'], 400);
        }

        //  FIND EXISTING PAYMENT (ONE PER ORDER)
        $payment = Payment::where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$payment) {
            // CREATE FIRST TIME (NO REFERENCE)
            $payment = Payment::create([
                'order_id'          => $order->id,
                'user_id'           => $user->id,
                'amount'            => $request->amount,
                'status'            => 'pending',
                'payment_method'    => $request->payment_method,
                'gateway_reference' => null,
                'notes'             => $request->notes,
            ]);
        } else {

            //  RULE 1: IF REFERENCE ALREADY EXISTS AND USER IS TRYING TO CHANGE IT
            if ($payment->gateway_reference && $request->gateway_reference && $payment->gateway_reference !== $request->gateway_reference) {
                return response()->json([
                    'error' => 'Gateway reference already exists. Cannot override existing reference.'
                ], 400);
            }

            //  UPDATE PAYMENT (ALLOW NULL OR NEW REFERENCE)
            $payment->update([
                'amount'            => $request->amount,
                'payment_method'    => $request->payment_method ?? $payment->payment_method,
                'gateway_reference' => $request->gateway_reference, // can become null or be updated
                'notes'             => $request->notes ?? $payment->notes,
            ]);
        }

        return response()->json([
            'message' => 'Payment processed successfully',
            'payment' => $payment->load('order'),
        ]);
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
        if ($request->user()->role !== 'admin') {
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
        $payment->order->update(['status' => 'paid']);
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
            if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $payment->delete();

        return response()->json(['message' => 'Payment deleted successfully']);
    }

    public function reconciliation()
    {
        return response()->json([
            'total_payments' => Payment::count(),
            'paid' => Payment::where('status', 'paid')->count(),
            'pending' => Payment::where('status', 'pending')->count(),
            'failed' => Payment::where('status', 'failed')->count(),

            'unmatched_orders' => Order::whereDoesntHave('payment')->count(),

            'recent_failed_payments' => Payment::where('status', 'failed')
                ->latest()
                ->take(10)
                ->get(),

            'revenue' => Payment::where('status', 'paid')->sum('amount'),
        ]);
    }

        /**
        * creating payment for webbook.
        */      

    public function webhook(Request $request)
    {
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        $expectedSignature = hash_hmac('sha512', $payload, env('PAYSTACK_SECRET_KEY'));

        if (!$signature || !hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'error' => 'Invalid webhook signature'
            ], 403);
        }

        $event = json_decode($payload, true);

        if (!$event || !isset($event['event'])) {
            return response()->json([
                'error' => 'Invalid webhook payload'
            ], 400);
        }

        if ($event['event'] !== 'charge.success') {
            return response()->json([
                'message' => 'Event ignored'
            ], 200);
        }

        $reference = $event['data']['reference'] ?? null;

        if (!$reference) {
            return response()->json([
                'error' => 'Missing payment reference'
            ], 400);
        }

        try {
            $paymentDetails = Http::withToken(env('PAYSTACK_SECRET_KEY'))
                ->get("https://api.paystack.co/transaction/verify/{$reference}")
                ->json();

            if (
                !$paymentDetails ||
                ($paymentDetails['status'] ?? false) !== true ||
                !isset($paymentDetails['data'])
            ) {
                return response()->json([
                    'error' => 'Unable to verify webhook payment'
                ], 400);
            }

            $data = $paymentDetails['data'];

            $orderId = $data['metadata']['order_id'] ?? null;
            $userId = $data['metadata']['user_id'] ?? null;

            if (!$orderId || !$userId) {
                return response()->json([
                    'error' => 'Invalid metadata'
                ], 400);
            }

            DB::beginTransaction();

            $order = Order::with(['items.product', 'payment'])
                ->lockForUpdate()
                ->findOrFail($orderId);

            $payment = Payment::where('order_id', $order->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                DB::rollBack();

                return response()->json([
                    'error' => 'Payment record not found'
                ], 404);
            }

            if ($payment->status === 'paid') {
                DB::rollBack();

                return response()->json([
                    'message' => 'Payment already processed'
                ], 200);
            }

            if (
                $payment->status === 'expired' ||
                ($payment->expires_at && now()->greaterThan($payment->expires_at))
            ) {
                $payment->update([
                    'status' => 'expired',
                    'expired_at' => now(),
                ]);

                $order->update([
                    'status' => 'expired',
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'Expired payment ignored'
                ], 200);
            }

            $paidAmount = $data['amount'] / 100;

            if ((float) $paidAmount !== (float) $order->total_price) {
                DB::rollBack();

                return response()->json([
                    'error' => 'Amount mismatch'
                ], 400);
            }

            $inventoryService = app(InventoryService::class);

            foreach ($order->items as $item) {
                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    DB::rollBack();

                    return response()->json([
                        'error' => 'Product not found'
                    ], 404);
                }

                $inventoryService->deductStock($product, $item->quantity);
            }

            $payment->update([
                'amount' => $paidAmount,
                'status' => 'paid',
                'payment_method' => 'paystack',
                'gateway_reference' => $data['reference'],
                'paid_at' => now(),
            ]);

            $order->update([
                'status' => 'paid',
            ]);

            DB::commit();

            try {
                Mail::to($order->user->email)->send(
                    new PaymentSuccessMail(
                        $order->fresh(['items.product', 'payment', 'user']),
                        $payment->fresh(),
                        $order->user
                    )
                );
            } catch (\Exception $mailException) {
                Log::error('Payment success email failed via webhook', [
                    'message' => $mailException->getMessage(),
                    'order_id' => $order->id,
                ]);
            }

            return response()->json([
                'message' => 'Webhook processed successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Paystack webhook error', [
                'message' => $e->getMessage(),
                'payload' => $event,
            ]);

            return response()->json([
                'error' => 'Webhook processing failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

}
