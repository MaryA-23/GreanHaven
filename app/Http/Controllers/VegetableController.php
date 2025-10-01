<?php

namespace App\Http\Controllers;

use App\Models\Vegetable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\VegetableResource;
use App\Events\VegetableReady;
use Illuminate\Support\Facades\Log;

class VegetableController extends Controller
{
    public function __construct()
    {
        // Public: index, show
        // Protected: store, update, destroy, restore
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    /**
     * List all vegetables with pagination and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vegetable::query();

        // Filter by name (partial match)
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Include soft deleted if requested
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $vegetables = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => VegetableResource::collection($vegetables),
            'meta' => [
                'current_page' => $vegetables->currentPage(),
                'last_page' => $vegetables->lastPage(),
                'per_page' => $vegetables->perPage(),
                'total' => $vegetables->total(),
            ],
        ]);
    }

    /**
     * Add a new vegetable (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized. Only admins can add vegetables.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:50',
            'is_available' => 'nullable|boolean',
            'supplier' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:ready,not_ready',
        ]);

        $veg = Vegetable::create($validated);

        return response()->json([
            'success' => true,
            'message' => "{$veg->name} has been added.",
            'data' => new VegetableResource($veg),
        ], 201);
    }

    /**
     * Show vegetable details.
     */
    public function show(int $id): JsonResponse
    {
        $veg = Vegetable::withTrashed()->findOrFail($id);

        $message = $veg->status === 'ready'
            ? "{$veg->name} is available now."
            : "{$veg->name} is not ready yet. You can request it.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new VegetableResource($veg),
        ]);
    }

    /**
     * Update vegetable details (Admin only).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized. Only admins can update vegetables.'], 403);
        }

        $veg = Vegetable::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:0',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'sometimes|string|max:50',
            'is_available' => 'nullable|boolean',
            'supplier' => 'nullable|string|max:255',
            'status' => 'sometimes|string|in:ready,not_ready',
        ]);

        $oldStatus = $veg->status;
        $veg->update($validated);

        Log::info("Vegetable updated", [
            'id' => $veg->id,
            'old_status' => $oldStatus,
            'new_status' => $veg->status,
            'updated_fields' => array_keys($validated),
        ]);

        if ($oldStatus !== 'ready' && ($validated['status'] ?? $oldStatus) === 'ready') {
            VegetableReady::dispatch($veg);
        }

        return response()->json([
            'success' => true,
            'message' => "{$veg->name} has been updated.",
            'data' => new VegetableResource($veg),
        ]);
    }

    /**
     * Soft delete a vegetable (Admin only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized. Only admins can delete vegetables.'], 403);
        }

        $veg = Vegetable::findOrFail($id);
        $veg->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vegetable deleted successfully (soft delete).',
        ]);
    }

    /**
     * Restore a soft deleted vegetable (Admin only).
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized. Only admins can restore vegetables.'], 403);
        }

        $veg = Vegetable::withTrashed()->findOrFail($id);

        if ($veg->trashed()) {
            $veg->restore();

            return response()->json([
                'success' => true,
                'message' => 'Vegetable restored successfully.',
                'data' => new VegetableResource($veg),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Vegetable is not deleted.',
        ], 400);
    }
}
