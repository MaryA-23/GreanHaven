<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenantSubscription;
use App\Services\AdminNotificationService;
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
        $user = $request->user();

        if (!$user || $user->role !== 'company' || !$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant account required.',
            ], 403);
        }

        $subscription = TenantSubscription::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'paid')
            ->orderByDesc('expires_at')
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
            'months' => [
                'required',
                'integer',
                'in:1,3,6,12',
            ],
        ]);

        $user = $request->user();

        if (!$user || $user->role !== 'company' || !$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant account required.',
            ], 403);
        }

        $secret = config('services.paystack.secret');

        $baseUrl = rtrim(
            config(
                'services.paystack.payment_url',
                'https://api.paystack.co'
            ),
            '/'
        );

        if (!$secret) {
            $this->notifySystemProblem(
                'Subscriptions',
                'Paystack configuration is missing while a tenant is trying to subscribe.',
                '/super-admin/subscriptions'
            );

            return response()->json([
                'success' => false,
                'message' => 'Paystack configuration is missing.',
            ], 500);
        }

        $companyId = (int) $user->company_id;
        $months = (int) $validated['months'];
        $amount = self::PLANS[$months];

        $reference =
            'SUB-'
            . now()->format('YmdHis')
            . '-'
            . Str::upper(Str::random(8));

        $subscription = TenantSubscription::create([
            'company_id' => $companyId,
            'months' => $months,
            'amount' => $amount,
            'currency' => 'GHS',
            'reference' => $reference,
            'status' => 'pending',
        ]);

        $callbackUrl = route(
            'tenant.subscriptions.paystack.callback'
        );

        try {
            $response = Http::withToken($secret)
                ->post(
                    $baseUrl . '/transaction/initialize',
                    [
                        'email' => $user->email,
                        'amount' => (int) round(
                            $amount * 100
                        ),
                        'currency' => 'GHS',
                        'reference' => $reference,
                        'callback_url' => $callbackUrl,
                        'metadata' => [
                            'subscription_id' =>
                                $subscription->id,
                            'company_id' =>
                                $companyId,
                            'months' =>
                                $months,
                        ],
                    ]
                );

            if (
                !$response->successful()
                || !$response->json('status')
            ) {
                $subscription->update([
                    'status' => 'failed',
                ]);

                $this->notifySubscriptionProblem(
                    $subscription,
                    'Paystack rejected the tenant subscription initialization request.'
                );

                Log::warning(
                    'Paystack subscription initialization rejected',
                    [
                        'company_id' => $companyId,
                        'subscription_id' =>
                            $subscription->id,
                        'reference' => $reference,
                        'http_status' =>
                            $response->status(),
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Unable to initialize subscription payment.',
                ], 502);
            }

            return response()->json([
                'success' => true,
                'message' =>
                    'Subscription payment initialized.',
                'authorization_url' =>
                    $response->json(
                        'data.authorization_url'
                    ),
                'reference' => $reference,
                'subscription' => $subscription,
            ]);
        } catch (\Throwable $e) {
            Log::error(
                'Tenant subscription initialization failed',
                [
                    'message' => $e->getMessage(),
                    'company_id' => $companyId,
                    'subscription_id' =>
                        $subscription->id,
                    'reference' => $reference,
                ]
            );

            $subscription->update([
                'status' => 'failed',
            ]);

            $this->notifySubscriptionProblem(
                $subscription,
                'An unexpected error occurred while initializing the tenant subscription payment.'
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to initialize subscription payment.',
            ], 500);
        }
    }

    public function callback(Request $request)
    {
        $reference = trim(
            (string) $request->query(
                'reference',
                ''
            )
        );

        $frontendUrl = rtrim(
            env(
                'FRONTEND_URL',
                'http://localhost:4200'
            ),
            '/'
        );

        if ($reference === '') {
            return redirect(
                $frontendUrl
                . '/tenant/subscription?status=failed'
            );
        }

        $verified =
            $this->verifyAndActivate(
                $reference
            );

        return redirect(
            $frontendUrl
            . '/tenant/subscription?status='
            . (
                $verified
                    ? 'success'
                    : 'failed'
            )
        );
    }

    public function webhook(Request $request)
    {
        $secret =
            config(
                'services.paystack.secret'
            );

        $signature =
            $request->header(
                'x-paystack-signature'
            );

        if (!$secret || !$signature) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $expected =
            hash_hmac(
                'sha512',
                $request->getContent(),
                $secret
            );

        if (
            !hash_equals(
                $expected,
                $signature
            )
        ) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        if (
            $request->input('event')
            !== 'charge.success'
        ) {
            return response()->json([
                'message' => 'Ignored',
            ], 200);
        }

        $reference = trim(
            (string) $request->input(
                'data.reference',
                ''
            )
        );

        if ($reference !== '') {
            $this->verifyAndActivate(
                $reference
            );
        }

        return response()->json([
            'message' => 'OK',
        ], 200);
    }

    private function verifyAndActivate(
        string $reference
    ): bool {
        $secret =
            config(
                'services.paystack.secret'
            );

        $baseUrl = rtrim(
            config(
                'services.paystack.payment_url',
                'https://api.paystack.co'
            ),
            '/'
        );

        if (!$secret) {
            Log::error(
                'Paystack secret is missing during subscription verification',
                [
                    'reference' => $reference,
                ]
            );

            $this->notifySystemProblem(
                'Subscriptions',
                'Paystack configuration is missing during subscription verification.',
                '/super-admin/subscriptions'
            );

            return false;
        }

        try {
            $response =
                Http::withToken($secret)
                    ->get(
                        $baseUrl
                        . '/transaction/verify/'
                        . urlencode(
                            $reference
                        )
                    );

            if (
                !$response->successful()
                || !$response->json('status')
            ) {
                $this->notifySubscriptionProblemByReference(
                    $reference,
                    'Paystack could not verify this tenant subscription payment.'
                );

                return false;
            }

            $data =
                $response->json(
                    'data'
                );

            if (
                !is_array($data)
                || ($data['status'] ?? null)
                    !== 'success'
            ) {
                $this->notifySubscriptionProblemByReference(
                    $reference,
                    'Paystack reports that this tenant subscription payment was not successful.'
                );

                return false;
            }

            return DB::transaction(
                function () use (
                    $reference,
                    $data
                ) {
                    $subscription =
                        TenantSubscription::query()
                            ->where(
                                'reference',
                                $reference
                            )
                            ->lockForUpdate()
                            ->first();

                    if (!$subscription) {
                        return false;
                    }

                    if (
                        $subscription->status
                        === 'paid'
                    ) {
                        return true;
                    }

                    $expectedAmount =
                        (int) round(
                            (
                                (float)
                                $subscription->amount
                            ) * 100
                        );

                    $paidAmount =
                        (int) (
                            $data['amount']
                            ?? 0
                        );

                    if (
                        $expectedAmount
                        !== $paidAmount
                    ) {
                        $subscription->update([
                            'status' => 'failed',
                        ]);

                        $this->notifySubscriptionProblem(
                            $subscription,
                            'The verified subscription payment amount does not match the expected amount.'
                        );

                        return false;
                    }

                    $metadata =
                        $data['metadata']
                        ?? [];

                    if (
                        !is_array($metadata)
                        ||
                        (
                            (int) (
                                $metadata[
                                    'subscription_id'
                                ]
                                ?? 0
                            )
                            !==
                            (int)
                            $subscription->id
                        )
                        ||
                        (
                            (int) (
                                $metadata[
                                    'company_id'
                                ]
                                ?? 0
                            )
                            !==
                            (int)
                            $subscription->company_id
                        )
                    ) {
                        $subscription->update([
                            'status' => 'failed',
                        ]);

                        $this->notifySubscriptionProblem(
                            $subscription,
                            'The verified subscription payment metadata does not match this tenant.'
                        );

                        return false;
                    }

                    $latestActive =
                        TenantSubscription::query()
                            ->where(
                                'company_id',
                                $subscription->company_id
                            )
                            ->where(
                                'status',
                                'paid'
                            )
                            ->whereNotNull(
                                'expires_at'
                            )
                            ->where(
                                'expires_at',
                                '>',
                                now()
                            )
                            ->orderByDesc(
                                'expires_at'
                            )
                            ->lockForUpdate()
                            ->first();

                    $startsAt =
                        $latestActive?->expires_at
                        ?? now();

                    $expiresAt =
                        $startsAt
                            ->copy()
                            ->addMonthsNoOverflow(
                                $subscription->months
                            );

                    $subscription->update([
                        'status' => 'paid',
                        'starts_at' =>
                            $startsAt,
                        'expires_at' =>
                            $expiresAt,
                        'paid_at' => now(),
                    ]);

                    return true;
                }
            );
        } catch (\Throwable $e) {
            Log::error(
                'Tenant subscription verification failed',
                [
                    'message' =>
                        $e->getMessage(),
                    'reference' =>
                        $reference,
                ]
            );

            $this->notifySubscriptionProblemByReference(
                $reference,
                'An unexpected error occurred while verifying the tenant subscription payment.'
            );

            return false;
        }
    }

    private function notifySubscriptionProblem(
        TenantSubscription $subscription,
        string $message
    ): void {
        try {
            $subscription->loadMissing('company');

            app(AdminNotificationService::class)
                ->subscriptionProblem(
                    $subscription->id,
                    $subscription->company_id,
                    $subscription->company?->name ?? 'Tenant',
                    $message
                );
        } catch (\Throwable $e) {
            Log::error(
                'Subscription problem notification failed',
                [
                    'message' => $e->getMessage(),
                    'subscription_id' => $subscription->id,
                ]
            );
        }
    }

    private function notifySubscriptionProblemByReference(
        string $reference,
        string $message
    ): void {
        $subscription = TenantSubscription::query()
            ->where('reference', $reference)
            ->first();

        if ($subscription) {
            $this->notifySubscriptionProblem(
                $subscription,
                $message
            );
            return;
        }

        $this->notifySystemProblem(
            'Subscriptions',
            $message,
            '/super-admin/subscriptions'
        );
    }

    private function notifySystemProblem(
        string $area,
        string $message,
        string $actionUrl
    ): void {
        try {
            app(AdminNotificationService::class)
                ->systemProblem(
                    'Tenant Portal',
                    $area,
                    $message,
                    $actionUrl
                );
        } catch (\Throwable $e) {
            Log::error(
                'System problem notification failed',
                [
                    'message' => $e->getMessage(),
                    'area' => $area,
                ]
            );
        }
    }

}
