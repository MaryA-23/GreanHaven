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
        // Protect routes except index and show
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
     * Add a new vegetable.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'nullable|string|in:ready,not_ready',
        ]);

        $veg = Vegetable::create([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'not_ready',
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$veg->name} has been added.",
            'data' => new VegetableResource($veg),
        ], 201);
    }

    /**
     * Show vegetable status.
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
     * Update vegetable details or status.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $veg = Vegetable::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|in:ready,not_ready',
            'customer_name' => 'sometimes|nullable|string|max:255',
            'customer_contact' => 'sometimes|nullable|string|max:255',
            'request_status' => 'sometimes|string|in:pending,in_progress,fulfilled',
        ]);

        $oldStatus = $veg->status;

        $veg->update($validated);

        // Log the update
        Log::info("Vegetable updated", [
            'id' => $veg->id,
            'old_status' => $oldStatus,
            'new_status' => $veg->status,
            'updated_fields' => array_keys($validated),
        ]);

        // Dispatch event if status changed to ready
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
     * Soft delete a vegetable.
     */
    public function destroy(int $id): JsonResponse
    {
        $veg = Vegetable::findOrFail($id);
        $veg->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vegetable deleted successfully (soft delete).',
        ]);
    }

    /**
     * Restore a soft deleted vegetable.
     */
    public function restore(int $id): JsonResponse
    {
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