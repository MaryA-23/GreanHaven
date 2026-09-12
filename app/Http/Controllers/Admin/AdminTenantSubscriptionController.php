<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\TenantSubscription;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AdminTenantSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,active,expired,pending,failed,paid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? 'all';
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = TenantSubscription::query()
            ->with([
                'company:id,name,email,phone',
            ])
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhere('admin_note', 'like', "%{$search}%")
                    ->orWhereHas('company', function ($companyQuery) use ($search) {
                        $companyQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        switch ($status) {
            case 'active':
                $query
                    ->where('status', 'paid')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '>', now());
                break;

            case 'expired':
                $query
                    ->where('status', 'paid')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now());
                break;

            case 'paid':
                $query->where('status', 'paid');
                break;

            case 'pending':
                $query->where('status', 'pending');
                break;

            case 'failed':
                $query->where('status', 'failed');
                break;
        }

        $subscriptions = $query->paginate($perPage);

        $subscriptions->getCollection()->transform(
            fn (TenantSubscription $subscription) =>
                $this->subscriptionPayload($subscription)
        );

        $summary = [
            'total' => TenantSubscription::count(),

            'active' => TenantSubscription::query()
                ->where('status', 'paid')
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', now())
                ->count(),

            'expired' => TenantSubscription::query()
                ->where('status', 'paid')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->count(),

            'pending' => TenantSubscription::query()
                ->where('status', 'pending')
                ->count(),

            'failed' => TenantSubscription::query()
                ->where('status', 'failed')
                ->count(),

            'revenue' => (float) TenantSubscription::query()
                ->where('status', 'paid')
                ->where('source', '!=', 'admin_override')
                ->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $subscriptions,
        ]);
    }

    public function verifyPayment(
        Request $request,
        int $id,
        AdminNotificationService $adminNotificationService
    ): JsonResponse {
        $this->requireSuperAdmin($request);

        $subscription = TenantSubscription::query()
            ->with('company')
            ->findOrFail($id);

        if ($subscription->status === 'paid') {
            return response()->json([
                'success' => true,
                'message' => 'Subscription is already active.',
                'data' => $this->subscriptionPayload($subscription),
            ]);
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
            $adminNotificationService->subscriptionProblem(
                $subscription->id,
                $subscription->company_id,
                $subscription->company?->name ?? 'Tenant',
                'Paystack configuration is missing while trying to verify a subscription.'
            );

            return response()->json([
                'success' => false,
                'message' => 'Paystack configuration is missing.',
            ], 500);
        }

        try {
            $response = Http::withToken($secret)
                ->get(
                    $baseUrl
                    . '/transaction/verify/'
                    . urlencode($subscription->reference)
                );

            if (
                !$response->successful()
                || !$response->json('status')
            ) {
                $adminNotificationService->subscriptionProblem(
                    $subscription->id,
                    $subscription->company_id,
                    $subscription->company?->name ?? 'Tenant',
                    'Paystack could not verify this subscription payment.'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Paystack could not verify this payment.',
                ], 422);
            }

            $data = $response->json('data');

            if (($data['status'] ?? null) !== 'success') {
                return response()->json([
                    'success' => false,
                    'message' => 'Paystack reports that this payment is not successful.',
                ], 422);
            }

            $expectedAmount = (int) round(
                ((float) $subscription->amount) * 100
            );

            $paidAmount = (int) ($data['amount'] ?? 0);

            if ($expectedAmount !== $paidAmount) {
                $adminNotificationService->subscriptionProblem(
                    $subscription->id,
                    $subscription->company_id,
                    $subscription->company?->name ?? 'Tenant',
                    'Verified payment amount does not match the expected subscription amount.'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount does not match this subscription.',
                ], 422);
            }

            $metadata = $data['metadata'] ?? [];

            if (
                (int) ($metadata['subscription_id'] ?? 0)
                    !== (int) $subscription->id
                ||
                (int) ($metadata['company_id'] ?? 0)
                    !== (int) $subscription->company_id
            ) {
                $adminNotificationService->subscriptionProblem(
                    $subscription->id,
                    $subscription->company_id,
                    $subscription->company?->name ?? 'Tenant',
                    'Paystack payment metadata does not match this subscription.'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Payment metadata does not match this subscription.',
                ], 422);
            }

            $subscription = DB::transaction(
                function () use ($subscription) {
                    $locked = TenantSubscription::query()
                        ->whereKey($subscription->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($locked->status === 'paid') {
                        return $locked;
                    }

                    $latestActive = TenantSubscription::query()
                        ->where('company_id', $locked->company_id)
                        ->where('status', 'paid')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '>', now())
                        ->orderByDesc('expires_at')
                        ->lockForUpdate()
                        ->first();

                    $startsAt =
                        $latestActive?->expires_at
                        ?? now();

                    $expiresAt =
                        $startsAt
                            ->copy()
                            ->addMonthsNoOverflow(
                                $locked->months
                            );

                    $locked->update([
                        'status' => 'paid',
                        'source' => 'paystack',
                        'starts_at' => $startsAt,
                        'expires_at' => $expiresAt,
                        'paid_at' => now(),
                    ]);

                    return $locked->fresh('company');
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment verified and tenant access activated.',
                'data' => $this->subscriptionPayload($subscription),
            ]);

        } catch (\Throwable $e) {
            Log::error(
                'Super Admin subscription verification failed',
                [
                    'message' => $e->getMessage(),
                    'subscription_id' => $subscription->id,
                    'reference' => $subscription->reference,
                ]
            );

            try {
                $adminNotificationService->subscriptionProblem(
                    $subscription->id,
                    $subscription->company_id,
                    $subscription->company?->name ?? 'Tenant',
                    'An error occurred while verifying the subscription payment.'
                );
            } catch (\Throwable $notificationException) {
                Log::error(
                    'Unable to create subscription problem notification',
                    [
                        'message' => $notificationException->getMessage(),
                    ]
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to verify the subscription payment.',
            ], 500);
        }
    }

    public function grantAccess(
        Request $request,
        int $id
    ): JsonResponse {
        $admin = $this->requireSuperAdmin($request);

        $validated = $request->validate([
            'months' => [
                'required',
                'integer',
                Rule::in([1, 3, 6, 12]),
            ],
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:500',
            ],
        ]);

        $source = TenantSubscription::query()
            ->with('company')
            ->findOrFail($id);

        $override = DB::transaction(
            function () use (
                $source,
                $validated,
                $admin
            ) {
                $latestActive =
                    TenantSubscription::query()
                        ->where(
                            'company_id',
                            $source->company_id
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
                            (int) $validated['months']
                        );

                return TenantSubscription::create([
                    'company_id' =>
                        $source->company_id,

                    'months' =>
                        (int) $validated['months'],

                    'amount' => 0,

                    'currency' =>
                        $source->currency ?: 'GHS',

                    'reference' =>
                        'ADMIN-'
                        . now()->format('YmdHis')
                        . '-'
                        . strtoupper(
                            substr(
                                sha1(
                                    $admin->uuid
                                    . microtime(true)
                                ),
                                0,
                                8
                            )
                        ),

                    'status' => 'paid',

                    'source' =>
                        'admin_override',

                    'admin_note' =>
                        $validated['reason'],

                    'granted_by_uuid' =>
                        $admin->uuid,

                    'source_subscription_id' =>
                        $source->id,

                    'starts_at' =>
                        $startsAt,

                    'expires_at' =>
                        $expiresAt,

                    'paid_at' =>
                        now(),
                ])->fresh('company');
            }
        );

        Log::warning(
            'Super Admin granted tenant subscription access manually',
            [
                'admin_uuid' => $admin->uuid,
                'company_id' => $source->company_id,
                'source_subscription_id' => $source->id,
                'override_subscription_id' => $override->id,
                'months' => $validated['months'],
                'reason' => $validated['reason'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Tenant access granted successfully.',
            'reason' => $validated['reason'],
            'data' => $this->subscriptionPayload($override),
        ]);
    }

    private function requireSuperAdmin(Request $request): Admin
    {
        $admin = $request->user();

        abort_unless(
            $admin instanceof Admin
            && $admin->role === 'super_admin'
            && $admin->status === 'active',
            403,
            'An active Super Admin account is required.'
        );

        return $admin;
    }

    private function subscriptionPayload(
        TenantSubscription $subscription
    ): array {
        $subscription->loadMissing('company');

        return [
            'id' => $subscription->id,

            'company' =>
                $subscription->company
                    ? [
                        'id' =>
                            $subscription->company->id,

                        'name' =>
                            $subscription->company->name,

                        'email' =>
                            $subscription->company->email,

                        'phone' =>
                            $subscription->company->phone,
                    ]
                    : null,

            'months' =>
                $subscription->months,

            'amount' =>
                (float) $subscription->amount,

            'currency' =>
                $subscription->currency,

            'reference' =>
                $subscription->reference,

            'status' =>
                $subscription->status,

            'display_status' =>
                $this->displayStatus(
                    $subscription
                ),

            'source' =>
                $subscription->source,

            'admin_note' =>
                $subscription->admin_note,

            'granted_by_uuid' =>
                $subscription->granted_by_uuid,

            'source_subscription_id' =>
                $subscription->source_subscription_id,

            'starts_at' =>
                $subscription->starts_at,

            'expires_at' =>
                $subscription->expires_at,

            'paid_at' =>
                $subscription->paid_at,

            'created_at' =>
                $subscription->created_at,
        ];
    }

    private function displayStatus(
        TenantSubscription $subscription
    ): string {
        if ($subscription->status === 'paid') {
            if (
                $subscription->expires_at
                &&
                $subscription->expires_at->isFuture()
            ) {
                return 'active';
            }

            return 'expired';
        }

        return $subscription->status;
    }
}
