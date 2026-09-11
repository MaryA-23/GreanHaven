<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCustomerController extends Controller
{
    /**
     * List customers who registered on the main website.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = User::query()
            ->where('role', 'user');

        $search = trim($validated['search'] ?? '');

        if ($search !== '') {

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query
            ->withCount('orders')
            ->latest()
            ->paginate(
                (int) ($validated['per_page'] ?? 10)
            );

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }


    /**
     * Show one customer.
     */
    public function show(int $id): JsonResponse
    {
        $customer = User::query()
            ->where('role', 'user')
            ->withCount('orders')
            ->with([
                'orders' => function ($query) {
                    $query
                        ->latest()
                        ->limit(10);
                }
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }


    /**
     * Activate or deactivate a customer.
     */
    public function updateStatus(
        Request $request,
        int $id
    ): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'in:active,inactive'
            ],
        ]);

        $customer = User::query()
            ->where('role', 'user')
            ->findOrFail($id);

        $customer->status =
            $validated['status'];

        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Customer status updated successfully.',
            'data' => $customer,
        ]);
    }
}
