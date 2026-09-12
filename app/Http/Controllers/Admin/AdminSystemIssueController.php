<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSystemIssueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:all,system_problem,subscription_problem'],
            'status' => ['nullable', 'in:all,unread,read'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $type = $validated['type'] ?? 'all';
        $status = $validated['status'] ?? 'all';
        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = AdminNotification::query()
            ->whereIn('type', [
                'system_problem',
                'subscription_problem',
            ])
            ->latest('id');

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        if ($status === 'unread') {
            $query->where('is_read', false);
        } elseif ($status === 'read') {
            $query->where('is_read', true);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('action_url', 'like', "%{$search}%");
            });
        }

        $issues = $query->paginate($perPage);

        $summaryQuery = AdminNotification::query()
            ->whereIn('type', [
                'system_problem',
                'subscription_problem',
            ]);

        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'unread' => (clone $summaryQuery)
                ->where('is_read', false)
                ->count(),
            'system' => (clone $summaryQuery)
                ->where('type', 'system_problem')
                ->count(),
            'subscriptions' => (clone $summaryQuery)
                ->where('type', 'subscription_problem')
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $issues,
        ]);
    }
}
