<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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

        // Get the logged-in user
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        

        // Create order with user_id (required) and optional company_id
        $order = Order::create([
            'user_id' => $user->id,
            'company_id' => $user->company_id, // can be null if user has no company
            'status' => 'pending',
            'total_price' => 0, // will calculate later
        ]);

        $total = 0;

        // Create order items with dynamic Product price
            foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);

            // ❌ If product doesn't exist
            if (!$product) {
                return response()->json([
                    'error' => 'Product not found'
                ], 404);
            }

            // ❌ Prevent ordering unavailable or low stock
            if (!$product->is_available || $product->quantity < $item['quantity']) {
                return response()->json([
                    'error' => "{$product->name} is not available in requested quantity"
                ], 400);
            }

            $price = $product->price;
            $subtotal = $price * $item['quantity'];

            // ✅ Create order item
            $order->items()->create([
                'product_id' => $product->id,
                'quantity'   => $item['quantity'],
                'price'      => $price,
                'subtotal'   => $subtotal,
            ]);

            $total += $subtotal;
        }

        // Update order total price
        $order->update(['total_price' => $total]);

        // Return clean JSON response with loaded items
        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully.',
            'data' => $order->load('items.product'),
        ], 201);
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
