<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Vegetable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // List all orders (admin can see all, users can see only their company)
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $orders = Order::with(['company', 'items.vegetable'])->paginate(20);
        } else {
            $orders = Order::with(['company', 'items.vegetable'])
                ->where('company_id', $user->company_id)
                ->paginate(20);
        }

        return OrderResource::collection($orders);
    }

    // Create a new order
     public function store(Request $request)
        {
            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.vegetable_id' => 'required|exists:vegetables,id',
                'items.*.quantity' => 'required|integer|min:1',
            ]);

            // Get authenticated user
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            $companyId = $user->company_id;
            if (!$companyId) {
                return response()->json(['error' => 'User does not belong to any company'], 400);
            }

            DB::beginTransaction();
            try {
                // Create order
                $order = Order::create([
                    'company_id' => $companyId,
                    'status' => 'pending',
                    'total_price' => 0,
                ]);

                $total = 0;

                foreach ($request->items as $item) {
                    $veg = Vegetable::findOrFail($item['vegetable_id']);
                    $subtotal = $veg->price * $item['quantity'];

                    $order->items()->create([
                        'vegetable_id' => $veg->id,
                        'quantity' => $item['quantity'],
                        'price' => $veg->price,
                        'subtotal' => $subtotal,
                    ]);

                    $total += $subtotal;
                }

                $order->update(['total_price' => $total]);
                DB::commit();

                return new OrderResource($order->load(['company', 'items.vegetable']));
            } catch (\Throwable $e) {
                DB::rollBack();
                return response()->json(['error' => 'Failed to create order', 'message' => $e->getMessage()], 500);
            }
        }


    // Show single order
    public function show(Request $request, $id)
    {
        $order = Order::with(['company', 'items.vegetable'])->findOrFail($id);
        $user = $request->user();

        if (!$user->isAdmin() && $order->company_id !== $user->company_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return new OrderResource($order);
    }

    // Update order status (admin only)
    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,delivered',
        ]);

        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);

        return new OrderResource($order->load(['company', 'items.vegetable']));
    }

    // Delete order (admin only)
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $order = Order::findOrFail($id);
        $order->delete();

        return response()->noContent();
    }
}
