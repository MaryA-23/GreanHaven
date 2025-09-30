<?php

namespace App\Http\Controllers;

use App\Models\Vegetable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\VegetableRequest;
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

    // Check if there is already a pending request for this vegetable by the same customer
    $existingRequest = $veg->requests()
        ->where('status', 'pending')
        ->where('customer_contact', $validated['customer_contact'])
        ->first();

    if ($existingRequest) {
        return response()->json([
            'success' => false,
            'message' => 'You already have a pending request for this vegetable.',
        ], 409);
    }

    // Create a new vegetable request
    $vegRequest = $veg->requests()->create([
        'customer_name' => $validated['customer_name'],
        'customer_contact' => $validated['customer_contact'],
        'status' => 'pending',
    ]);

    return response()->json([
        'success' => true,
        'message' => "Your request for {$veg->name} has been received.",
        'data' => $vegRequest, // you can wrap it in a resource if you like
    ]);
    }


    /**
     * Fulfill a customer's request.
     */
    public function fulfill(int $requestId): JsonResponse
    {
        // Find the specific vegetable request
        $vegRequest = VegetableRequest::findOrFail($requestId);

        if ($vegRequest->status === 'fulfilled') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been fulfilled.',
            ], 400);
        }

        // Update the status to fulfilled
        $vegRequest->update([
            'status' => 'fulfilled',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Request for {$vegRequest->vegetable->name} has been fulfilled.",
            'data' => $vegRequest,
        ]);
    }

}
