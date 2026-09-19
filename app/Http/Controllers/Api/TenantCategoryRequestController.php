<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Category;
use App\Models\CategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantCategoryRequestController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        if (!$companyId) {
            return response()->json([
                'message' => 'No company is associated with this account.',
            ], 403);
        }

        $requests = CategoryRequest::where('company_id', $companyId)
            ->latest()
            ->get();

        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $companyId = $user->company_id;

        if (!$companyId) {
            return response()->json([
                'message' => 'No company is associated with this account.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_requests', 'name')
                    ->where(fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->where('status', 'pending')),
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $categoryName = trim($validated['name']);

        $categoryExists = Category::whereRaw(
            'LOWER(name) = ?',
            [strtolower($categoryName)]
        )->exists();

        if ($categoryExists) {
            return response()->json([
                'message' => 'This category already exists.',
            ], 422);
        }

        $categoryRequest = CategoryRequest::create([
            'company_id' => $companyId,
            'name' => $categoryName,
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Notify Super Admin
        |--------------------------------------------------------------------------
        */

        $companyName = $user->company?->name ?? 'A tenant';

        AdminNotification::create([
            'type' => 'category_request',
            'title' => 'New Category Request',
            'message' => $companyName
                . ' requested a new category: '
                . $categoryName . '.',
            'action_url' => '/super-admin/category-requests',
            'reference_id' => $categoryRequest->id,
            'is_read' => false,
            'read_at' => null,
        ]);

        return response()->json([
            'message' => 'Category request submitted successfully.',
            'request' => $categoryRequest,
        ], 201);
    }
}