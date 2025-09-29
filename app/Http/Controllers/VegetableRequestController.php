<?php

namespace App\Http\Controllers;

use App\Models\Vegetable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\VegetableResource;

class VegetableRequestController extends Controller
{
    public function __construct()
    {
        // Only authenticated users can fulfill requests
        $this->middleware('auth:sanctum')->only(['fulfill']);
    }

    /**
     * Customer makes a request for a vegetable.
     */
    public function store(Request $request, int $vegetableId): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_contact' => 'required|string|max:255',
        ]);

        $veg = Vegetable::findOrFail($vegetableId);

        if ($veg->request_status === 'pending' || $veg->request_status === 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'There is already an active request for this vegetable.',
            ], 409);
        }

        $veg->update([
            'customer_name' => $validated['customer_name'],
            'customer_contact' => $validated['customer_contact'],
            'request_status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Your request for {$veg->name} has been received.",
            'data' => new VegetableResource($veg),
        ]);
    }

    /**
     * Fulfill a customer's request.
     */
    public function fulfill(int $vegetableId): JsonResponse
    {
        $veg = Vegetable::findOrFail($vegetableId);

        if ($veg->request_status !== 'in_progress' && $veg->request_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'No active request to fulfill for this vegetable.',
            ], 400);
        }

        $veg->update(['request_status' => 'fulfilled']);

        return response()->json([
            'success' => true,
            'message' => "Request for {$veg->name} has been fulfilled.",
            'data' => new VegetableResource($veg),
        ]);
    }
}
