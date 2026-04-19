<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\InventoryService;

class OrderController extends Controller
{

protected $inventoryService;
    public function __construct(InventoryService $inventoryService)
    {
        $this->middleware('auth:sanctum');
        $this->inventoryService = $inventoryService;
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

        // prevent multiple pending orders
        $existingOrder = Order::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingOrder) {
            return response()->json([
                'message' => 'You already have a pending order'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $total = 0;

            $order = Order::create([
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'status' => 'pending',
                'total_price' => 0,
            ]);

            foreach ($validated['items'] as $item) {

                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$product || $product->status === 'out_of_stock') {
                    throw new \Exception("Product not available");
                }

                // USE SERVICE HERE (IMPORTANT CHANGE)
                $this->inventoryService->deductStock($product, $item['quantity']);

                $subtotal = $product->price * $item['quantity'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity'   => $item['quantity'],
                    'price'      => $product->price,
                    'subtotal'   => $subtotal,
                ]);

                $total += $subtotal;
            }

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
