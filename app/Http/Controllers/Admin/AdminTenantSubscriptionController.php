<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $subscriptions->getCollection()->transform(function (TenantSubscription $subscription) {
            return [
                'id' => $subscription->id,
                'company' => $subscription->company ? [
                    'id' => $subscription->company->id,
                    'name' => $subscription->company->name,
                    'email' => $subscription->company->email,
                    'phone' => $subscription->company->phone,
                ] : null,
                'months' => $subscription->months,
                'amount' => (float) $subscription->amount,
                'currency' => $subscription->currency,
                'reference' => $subscription->reference,
                'status' => $subscription->status,
                'display_status' => $this->displayStatus($subscription),
                'starts_at' => $subscription->starts_at,
                'expires_at' => $subscription->expires_at,
                'paid_at' => $subscription->paid_at,
                'created_at' => $subscription->created_at,
            ];
        });

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
                ->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $subscriptions,
        ]);
    }

    private function displayStatus(TenantSubscription $subscription): string
    {
        if ($subscription->status === 'paid') {
            if (
                $subscription->expires_at &&
                $subscription->expires_at->isFuture()
            ) {
                return 'active';
            }

            return 'expired';
        }

        return $subscription->status;
    }
}
