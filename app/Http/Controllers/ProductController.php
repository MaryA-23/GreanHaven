<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\ProductResource;
use App\Events\ProductReady;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function __construct()
    {
        // Public: index, show
        // Protected: store, update, destroy, restore
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    /**
     * List all Products with pagination and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        // $query = Product::query();
        $query = Product::with('category');

        // Filter by name (partial match)
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by price range
        if ($request->filled('min_price')) {
          $query->where('price', '>=', $request->min_price);  
}
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);  
        }

        // Filter by stock availability
        if ($request->filled('in_stock')) {
         $query->where('status', 'active')  
          ->where('quantity', '>', 0);    
}
        // Include soft deleted if requested
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $Products = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($Products),
            'meta' => [
                'current_page' => $Products->currentPage(),
                'last_page' => $Products->lastPage(),
                'per_page' => $Products->perPage(),
                'total' => $Products->total(),
            ],
        ]);
    }

    /**
     * Add a new Product (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized. Only admins can add Products.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:50',
            'is_available' => 'nullable|boolean',
            'status' => 'nullable|string|in:ready,not_ready',
        ]);

        //  CHECK IF PRODUCT ALREADY EXISTS
        $existingProduct = Product::where('name', $validated['name'])
            ->where('category_id', $validated['category_id'])
            ->first();

        if ($existingProduct) {

            // update stock instead of duplicate
            $existingProduct->quantity += $validated['quantity'];
            $existingProduct->price = $validated['price']; // optional update
            $existingProduct->is_available = true;
            $existingProduct->save();

            return response()->json([
                'success' => true,
                'message' => "{$existingProduct->name} stock updated successfully.",
                'data' => new ProductResource($existingProduct),
            ], 200);
        }

        // create new product
        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => "{$product->name} has been added.",
            'data' => new ProductResource($product),
        ], 201);
    }

    /**
     * Show Product details.
     */
    public function show(int $id): JsonResponse
    {
        $veg = Product::withTrashed()->findOrFail($id);

        $message = $veg->status === 'ready'
            ? "{$veg->name} is available now."
            : "{$veg->name} is not ready yet. You can request it.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new ProductResource($veg),
        ]);
    }

    /**
     * Update Product details (Admin only).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized. Only admins can update Products.'], 403);
        }

        $veg = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'unit' => 'sometimes|string|max:50',
            'is_available' => 'nullable|boolean',
            'supplier' => 'nullable|string|max:255',
            'status' => 'sometimes|string|in:ready,not_ready',
        ]);

        $oldStatus = $veg->status;
        $veg->update($validated);

        Log::info("Product updated", [
            'id' => $veg->id,
            'old_status' => $oldStatus,
            'new_status' => $veg->status,
            'updated_fields' => array_keys($validated),
        ]);

        if ($oldStatus !== 'ready' && ($validated['status'] ?? $oldStatus) === 'ready') {
            ProductReady::dispatch($veg);
        }

        return response()->json([
            'success' => true,
            'message' => "{$veg->name} has been updated.",
            'data' => new ProductResource($veg),
        ]);
    }

    /**
     * Soft delete a Product (Admin only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized. Only admins can delete Products.'], 403);
        }

        $veg = Product::findOrFail($id);
        $veg->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully (soft delete).',
        ]);
    }

    /**
     * Restore a soft deleted Product (Admin only).
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized. Only admins can restore Products.'], 403);
        }

        $veg = Product::withTrashed()->findOrFail($id);

        if ($veg->trashed()) {
            $veg->restore();

            return response()->json([
                'success' => true,
                'message' => 'Product restored successfully.',
                'data' => new ProductResource($veg),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Product is not deleted.',
        ], 400);
    }
}
