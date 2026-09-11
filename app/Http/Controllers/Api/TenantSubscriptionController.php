<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenantSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantSubscriptionController extends Controller
{
    private const PLANS = [
        1 => 100,
        3 => 270,
        6 => 500,
        12 => 900,
    ];

    public function plans()
    {
        return response()->json([
            'success' => true,
            'currency' => 'GHS',
            'plans' => collect(self::PLANS)
                ->map(fn ($amount, $months) => [
                    'months' => (int) $months,
                    'amount' => $amount,
                ])
                ->values(),
        ]);
    }

    public function status(Request $request)
    {
        $companyId = $request->user()->company_id;

        $subscription = TenantSubscription::query()
            ->where('company_id', $companyId)
            ->where('status', 'paid')
            ->latest('expires_at')
            ->first();

        return response()->json([
            'success' => true,
            'active' => $subscription?->isActive() ?? false,
            'subscription' => $subscription,
        ]);
    }

    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'months' => ['required', 'integer', 'in:1,3,6,12'],
        ]);

        $user = $request->user();
        $companyId = $user->company_id;

        $months = (int) $validated['months'];
        $amount = self::PLANS[$months];

        $reference = 'SUB-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(8));

        $subscription = TenantSubscription::create([
            'company_id' => $companyId,
            'months' => $months,
            'amount' => $amount,
            'currency' => 'GHS',
            'reference' => $reference,
            'status' => 'pending',
        ]);

        $callbackUrl = url('/api/tenant/subscriptions/paystack/callback');

        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->post('https://api.paystack.co/transaction/initialize', [
                    'email' => $user->email,
                    'amount' => (int) round($amount * 100),
                    'currency' => 'GHS',
                    'reference' => $reference,
                    'callback_url' => $callbackUrl,
                    'metadata' => [
                        'subscription_id' => $subscription->id,
                        'company_id' => $companyId,
                        'months' => $months,
                    ],
                ]);

            if (!$response->successful() || !$response->json('status')) {
                $subscription->update(['status' => 'failed']);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to initialize subscription payment.',
                ], 502);
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription payment initialized.',
                'authorization_url' => $response->json('data.authorization_url'),
                'reference' => $reference,
                'subscription' => $subscription,
            ]);
        } catch (\Throwable $e) {
            Log::error('Tenant subscription initialization failed', [
                'message' => $e->getMessage(),
                'company_id' => $companyId,
                'subscription_id' => $subscription->id,
            ]);

            $subscription->update(['status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => 'Unable to initialize subscription payment.',
            ], 500);
        }
    }

    public function callback(Request $request)
    {
        $reference = (string) $request->query('reference');

        if ($reference === '') {
            return redirect(
                env('FRONTEND_URL', 'http://localhost:4200')
                . '/tenant/subscription?status=failed'
            );
        }

        $verified = $this->verifyAndActivate($reference);

        return redirect(
            env('FRONTEND_URL', 'http://localhost:4200')
            . '/tenant/subscription?status=' . ($verified ? 'success' : 'failed')
        );
    }

    public function webhook(Request $request)
    {
        $secret = config('services.paystack.secret_key');
        $signature = $request->header('x-paystack-signature');

        if (!$secret || !$signature) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $expected = hash_hmac('sha512', $request->getContent(), $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($request->input('event') !== 'charge.success') {
            return response()->json(['message' => 'Ignored'], 200);
        }

        $reference = (string) $request->input('data.reference');

        if ($reference !== '') {
            $this->verifyAndActivate($reference);
        }

        return response()->json(['message' => 'OK'], 200);
    }

    private function verifyAndActivate(string $reference): bool
    {
        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->get(
                    'https://api.paystack.co/transaction/verify/'
                    . urlencode($reference)
                );

            if (!$response->successful() || !$response->json('status')) {
                return false;
            }

            $data = $response->json('data');

            if (($data['status'] ?? null) !== 'success') {
                return false;
            }

            return DB::transaction(function () use ($reference, $data) {
                $subscription = TenantSubscription::query()
                    ->where('reference', $reference)
                    ->lockForUpdate()
                    ->first();

                if (!$subscription) {
                    return false;
                }

                if ($subscription->status === 'paid') {
                    return true;
                }

                $expectedAmount = (int) round(((float) $subscription->amount) * 100);
                $paidAmount = (int) ($data['amount'] ?? 0);

                if ($expectedAmount !== $paidAmount) {
                    $subscription->update(['status' => 'failed']);
                    return false;
                }

                $metadata = $data['metadata'] ?? [];

                if (
                    (int) ($metadata['subscription_id'] ?? 0) !== $subscription->id ||
                    (int) ($metadata['company_id'] ?? 0) !== $subscription->company_id
                ) {
                    $subscription->update(['status' => 'failed']);
                    return false;
                }

                $latestActive = TenantSubscription::query()
                    ->where('company_id', $subscription->company_id)
                    ->where('status', 'paid')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '>', now())
                    ->orderByDesc('expires_at')
                    ->lockForUpdate()
                    ->first();

                $startsAt = $latestActive?->expires_at ?? now();
                $expiresAt = $startsAt->copy()->addMonthsNoOverflow($subscription->months);

                $subscription->update([
                    'status' => 'paid',
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                    'paid_at' => now(),
                ]);

                return true;
            });
        } catch (\Throwable $e) {
            Log::error('Tenant subscription verification failed', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return false;
        }
    }
}
