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
            ->where('user_id', $user->id)
            ->firstOrFail();

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

        // Save or refresh local reference before sending to Paystack
        $reference = $payment->reference ?: 'PAY-' . now()->timestamp . '-' . $payment->id;

        $payment->update([
            'reference' => $reference,
        ]);

        $callbackUrl = config('services.paystack.callback_url');

        $paymentData = [
            'amount' => (int) round($payment->amount * 100),
            'email' => $order->user->email,
            'reference' => $reference,
            'metadata' => [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'user_id' => $order->user_id,
            ],
            'callback_url' => $callbackUrl,
        ];

        try {
            $authorization = Paystack::getAuthorizationUrl($paymentData);
            $paymentUrl = $authorization->url;

            Log::info('Paystack init payload', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'payment_url' => $paymentUrl,
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
                    'payment_id' => $payment->id,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment link generated.',
                'authorization_url' => $paymentUrl,
                'reference' => $reference,
                'payment_expires_at' => $payment->expires_at,
                'callback_url' => $callbackUrl,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Paystack initialize failed', [
                'message' => $e->getMessage(),
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'reference' => $reference,
                'callback_url' => $callbackUrl,
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
        public function callback( Request $request,InventoryService $inventoryService) {
            $reference = $request->query('reference');

            if (!$reference) {
                return response()->json([
                    'error' => 'Payment reference is missing'
                ], 400);
            }

            try {

                // Verify directly with Paystack
                $paymentDetails = Http::withToken(
                    env('PAYSTACK_SECRET_KEY')
                )
                ->get(
                    "https://api.paystack.co/transaction/verify/{$reference}"
                )
                ->json();

                if (
                    !$paymentDetails ||
                    ($paymentDetails['status'] ?? false) !== true ||
                    !isset($paymentDetails['data'])
                ) {
                    return response()->json([
                        'error' => 'Unable to verify payment',
                        'details' => $paymentDetails,
                    ], 400);
                }

                $data = $paymentDetails['data'];

                // Paystack transaction itself must be successful
                if (($data['status'] ?? null) !== 'success') {
                    return response()->json([
                        'error' => 'Payment was not successful',
                        'paystack_status' => $data['status'] ?? null
                    ], 400);
                }

                $orderId =
                    $data['metadata']['order_id'] ?? null;

                $userId =
                    $data['metadata']['user_id'] ?? null;

                $paymentId =
                    $data['metadata']['payment_id'] ?? null;

                $paidAmount =
                    isset($data['amount'])
                        ? $data['amount'] / 100
                        : null;

                if (
                    !$orderId ||
                    !$userId ||
                    is_null($paidAmount)
                ) {
                    return response()->json([
                        'error' => 'Invalid payment metadata'
                    ], 400);
                }

                DB::beginTransaction();

                try {

                    $order = Order::with([
                        'items.product',
                        'payment',
                        'user'
                    ])
                    ->lockForUpdate()
                    ->findOrFail($orderId);

                    if (
                        (int) $order->user_id !==
                        (int) $userId
                    ) {
                        DB::rollBack();

                        return response()->json([
                            'error' => 'Order ownership mismatch'
                        ], 403);
                    }

                    $paymentQuery = Payment::where(
                        'order_id',
                        $order->id
                    )
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->lockForUpdate();

                    if ($paymentId) {
                        $paymentQuery->where(
                            'id',
                            $paymentId
                        );
                    }

                    $payment = $paymentQuery->first();

                    if (!$payment) {
                        DB::rollBack();

                        return response()->json([
                            'error' => 'Payment record not found'
                        ], 404);
                    }

                    // Prevent processing twice
                    if ($payment->status === 'paid') {

                        DB::commit();

                        return response()->json([
                            'success' => true,
                            'message' => 'Payment already processed.',
                            'order_status' => $order->status,
                            'payment_status' => $payment->status,
                            'reference' => $reference
                        ], 200);
                    }

                    // Make sure amount paid matches order/payment
                    if (
                        round((float) $paidAmount, 2) !==
                        round((float) $payment->amount, 2)
                    ) {
                        DB::rollBack();

                        return response()->json([
                            'error' => 'Payment amount mismatch'
                        ], 400);
                    }

                    /*
                    * Deduct stock only after Paystack
                    * confirms successful payment.
                    */
                    foreach ($order->items as $item) {

                        $product = Product::where(
                            'id',
                            $item->product_id
                        )
                        ->lockForUpdate()
                        ->first();

                        if (!$product) {
                            DB::rollBack();

                            return response()->json([
                                'error' => 'Product not found'
                            ], 404);
                        }

                        $inventoryService->deductStock(
                            $product,
                            $item->quantity
                        );
                    }

                    // Update existing payment
                    $payment->update([
                        'amount' => $paidAmount,
                        'status' => 'paid',
                        'payment_method' => 'paystack',
                        'gateway_reference' => $reference,
                        'paid_at' => now(),
                    ]);

                    // Update order
                    $order->update([
                        'status' => 'paid',
                    ]);

                    // Clear database cart if one exists
                    if (
                        $order->user &&
                        $order->user->cart
                    ) {
                        $order->user
                            ->cart
                            ->items()
                            ->delete();
                    }

                    DB::commit();

                    // Send GreenHaven payment email
                    try {

                        Mail::to(
                            $order->user->email
                        )->send(
                            new PaymentSuccessMail(
                                $order->fresh([
                                    'items.product',
                                    'payment',
                                    'user'
                                ]),
                                $payment->fresh(),
                                $order->user
                            )
                        );

                    } catch (\Exception $mailException) {

                        Log::error(
                            'Payment success email failed',
                            [
                                'message' =>
                                    $mailException->getMessage(),

                                'order_id' =>
                                    $order->id,
                            ]
                        );
                    }

                    return response()->json([
                        'success' => true,
                        'message' =>
                            'Payment verified successfully.',

                        'paystack_status' =>
                            $data['status'],

                        'order_status' =>
                            'paid',

                        'payment_status' =>
                            'paid',

                        'reference' =>
                            $reference,

                        'order' =>
                            $order->fresh([
                                'items.product',
                                'payment'
                            ])
                    ], 200);

                } catch (\Exception $e) {

                    DB::rollBack();

                    throw $e;
                }

            } catch (\Exception $e) {

                Log::error(
                    'Paystack callback error',
                    [
                        'message' =>
                            $e->getMessage(),

                        'reference' =>
                            $reference,
                    ]
                );

                return response()->json([
                    'error' =>
                        'Payment verification failed',

                    'message' =>
                        $e->getMessage()
                ], 500);
            }
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

    public function webhook(Request $request, InventoryService $inventoryService)
    {
        $secret = env('PAYSTACK_SECRET_KEY');
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        if (!$signature || hash_hmac('sha512', $payload, $secret) !== $signature) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);

        if (($event['event'] ?? null) !== 'charge.success') {
            return response()->json(['message' => 'Event ignored'], 200);
        }

        $data = $event['data'] ?? null;

        if (!$data || ($data['status'] ?? null) !== 'success') {
            return response()->json(['message' => 'Invalid payment data'], 400);
        }

        $orderId = $data['metadata']['order_id'] ?? null;
        $userId = $data['metadata']['user_id'] ?? null;
        $paymentId = $data['metadata']['payment_id'] ?? null;
        $reference = $data['reference'] ?? null;
        $paidAmount = isset($data['amount']) ? $data['amount'] / 100 : null;

        if (!$orderId || !$userId || !$reference || is_null($paidAmount)) {
            return response()->json(['message' => 'Incomplete metadata'], 400);
        }

        DB::beginTransaction();

        try {
            $order = Order::with(['items.product', 'payment', 'user'])
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ((int) $order->user_id !== (int) $userId) {
                DB::rollBack();
                return response()->json(['message' => 'Order ownership mismatch'], 403);
            }

            $paymentQuery = Payment::where('order_id', $order->id)
                ->where('user_id', $userId)
                ->where('reference', $reference)
                ->lockForUpdate();

            if ($paymentId) {
                $paymentQuery->where('id', $paymentId);
            }

            $payment = $paymentQuery->first();

            if (!$payment) {
                DB::rollBack();
                return response()->json(['message' => 'Payment record not found'], 404);
            }

            if ($payment->status === 'paid') {
                DB::rollBack();
                return response()->json(['message' => 'Already processed'], 200);
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

                return response()->json(['message' => 'Payment expired'], 200);
            }

            if (round((float) $paidAmount, 2) !== round((float) $payment->amount, 2)) {
                DB::rollBack();
                return response()->json(['message' => 'Amount mismatch'], 400);
            }

            foreach ($order->items as $item) {
                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    DB::rollBack();
                    return response()->json(['message' => 'Product not found'], 404);
                }

                $inventoryService->deductStock($product, $item->quantity);
            }

            $payment->update([
                'amount' => $paidAmount,
                'status' => 'paid',
                'payment_method' => 'paystack',
                'gateway_reference' => $reference,
                'paid_at' => now(),
            ]);

            $order->update([
                'status' => 'paid',
            ]);

            if ($order->user && $order->user->cart) {
                $order->user->cart->items()->delete();
            }

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

            return response()->json(['message' => 'Webhook processed successfully'], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Paystack webhook error', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return response()->json([
                'message' => 'Webhook failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
