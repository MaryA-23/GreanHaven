<?php

namespace App\Http\Middleware;

use App\Models\TenantSubscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'company' || !$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant account required.',
            ], 403);
        }

        $active = TenantSubscription::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'paid')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->exists();

        if (!$active) {
            return response()->json([
                'success' => false,
                'subscription_required' => true,
                'message' => 'An active subscription is required.',
            ], 403);
        }

        return $next($request);
    }
}
