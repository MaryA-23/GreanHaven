<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List all orders (admins see all, users see only theirs).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Order::with('items.Product'); // eager load items & Products

        if ($user->role === 'admin') {
            // Admin sees all orders
        } elseif ($user->role === 'company') {
            $query->where('company_id', $user->company_id);
        } else { // normal user
            $query->where('user_id', $user->id);
        }

        // Optional: filter by date if needed
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Create a new order (user).
     */
     public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        DB::beginTransaction();

        try {
            $total = 0;

            // Create order FIRST but safely inside transaction
            $order = Order::create([
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'status' => 'pending',
                'total_price' => 0,
            ]);

            foreach ($validated['items'] as $item) {

                // Lock row to prevent race condition
                $product = Product::where('id', $item['product_id'])->lockForUpdate()->first();

                // Extra safety check
                if (!$product || !$product->is_available) {
                    throw new \Exception("Product not available");
                }

                if ($product->quantity < $item['quantity']) {
                    throw new \Exception("{$product->name} is out of stock");
                }

                $price = $product->price;
                $subtotal = $price * $item['quantity'];

                // Create item
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity'   => $item['quantity'],
                    'price'      => $price,
                    'subtotal'   => $subtotal,
                ]);

                $total += $subtotal;
            }

            // Update total AFTER loop
            $order->update(['total_price' => $total]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully.',
                'data' => $order->load('items.product'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Show a single order.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::with('items.Product')->findOrFail($id);

        if ($request->user()->role !== 'admin' && $order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Update order status (admin only).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Only admins can update orders.'], 403);
        }

        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:pending,completed,cancelled',
        ]);

        $order->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully.',
            'data' => $order->load('items.product'),
        ]);
    }

    /**
     * Delete an order (admin only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Only admins can delete orders.'], 403);
        }

        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully.',
        ]);
    }
}
