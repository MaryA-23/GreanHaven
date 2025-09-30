<?php


namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Vegetable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * List all orders with related company and items.
     */
    public function index()
    {
        $orders = Order::with(['company', 'items.vegetable'])->get();   
        
        return OrderResource::collection($orders);
    }
        /**
     * Create a new order with multiple vegetables.
     */
    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'items' => 'required|array|min:1',
            'items.*.vegetable_id' => 'required|exists:vegetables,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // Create order with status pending
            $order = Order::create([
                'company_id' => $request->company_id,
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

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create order',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a single order.
     */
    public function show(string $id)
    {
        $order = Order::with(['company', 'items.vegetable'])->findOrFail($id);
        return new OrderResource($order);
    }

    /**
     * Update only the status of an order.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,delivered',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);

        return new OrderResource($order->load(['company', 'items.vegetable']));
    }

    /**
     * Delete an order and its items.
     */
    public function destroy(string $id)
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json(['message' => 'Order deleted successfully']);
    }
}
