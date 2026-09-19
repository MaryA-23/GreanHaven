<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryRequest;
use App\Models\TenantNotification;
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

        /*
        |--------------------------------------------------------------------------
        | Notify Tenant
        |--------------------------------------------------------------------------
        */

        TenantNotification::create([
            'company_id' => $categoryRequest->company_id,
            'type' => 'category_request_approved',
            'title' => 'Category Request Approved',
            'message' => 'Your request for the category "'
                . $categoryRequest->name
                . '" has been approved.',
            'action_url' => '/tenant/category-requests',
            'reference_id' => $categoryRequest->id,
            'is_read' => false,
            'read_at' => null,
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

        /*
        |--------------------------------------------------------------------------
        | Notify Tenant
        |--------------------------------------------------------------------------
        */

        TenantNotification::create([
            'company_id' => $categoryRequest->company_id,
            'type' => 'category_request_rejected',
            'title' => 'Category Request Rejected',
            'message' => 'Your request for the category "'
                . $categoryRequest->name
                . '" has been rejected.',
            'action_url' => '/tenant/category-requests',
            'reference_id' => $categoryRequest->id,
            'is_read' => false,
            'read_at' => null,
        ]);

        return response()->json([
            'message' => 'Category request rejected successfully.',
            'request' => $categoryRequest->load('company:id,name,email'),
        ]);
    }
}