<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryRequest;
use Illuminate\Http\Request;

class AdminCategoryRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = CategoryRequest::with('company:id,name,email');

        if ($request->filled('status')) {
            $request->validate([
                'status' => 'in:pending,approved,rejected',
            ]);

            $query->where('status', $request->status);
        }

        return response()->json(
            $query->latest()->get()
        );
    }

    public function show($id)
    {
        $categoryRequest = CategoryRequest::with('company:id,name,email')
            ->findOrFail($id);

        return response()->json($categoryRequest);
    }

    public function approve($id)
    {
        $categoryRequest = CategoryRequest::findOrFail($id);

        if ($categoryRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending requests can be approved.',
            ], 422);
        }

        $categoryRequest->update([
            'status' => 'approved',
        ]);

        return response()->json([
            'message' => 'Category request approved successfully.',
            'request' => $categoryRequest->load('company:id,name,email'),
        ]);
    }

    public function reject($id)
    {
        $categoryRequest = CategoryRequest::findOrFail($id);

        if ($categoryRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending requests can be rejected.',
            ], 422);
        }

        $categoryRequest->update([
            'status' => 'rejected',
        ]);

        return response()->json([
            'message' => 'Category request rejected successfully.',
            'request' => $categoryRequest->load('company:id,name,email'),
        ]);
    }
}