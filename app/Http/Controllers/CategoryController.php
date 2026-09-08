<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | PUBLIC CATEGORIES
    |--------------------------------------------------------------------------
    |
    | Public customers can see all GreenHaven master categories.
    |
    */

    public function index(Request $request)
    {
        $categories = Category::query()
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $query->where(
                        'name',
                        'like',
                        '%' . $request->search . '%'
                    );
                }
            )
            ->withCount('products')
            ->orderBy('name')
            ->paginate(15);

        $categories->getCollection()->transform(
            function ($category) {
                $category->image_url = $category->image
                    ? asset('storage/' . $category->image)
                    : null;

                return $category;
            }
        );

        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' => 'Categories retrieved successfully',
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW SINGLE CATEGORY
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $category = Category::with('products')
            ->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $category->image_url = $category->image
            ? asset('storage/' . $category->image)
            : null;

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | SUPER ADMIN - CREATE CATEGORY
    |--------------------------------------------------------------------------
    |
    | This route will only be available to role:admin.
    |
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:categories,name',
            ],

            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        $imagePath = $request
            ->file('image')
            ->store('categories', 'public');

        $category = Category::create([
            'name' => trim($validated['name']),
            'image' => $imagePath,
        ]);

        $category->image_url = $category->image
            ? asset('storage/' . $category->image)
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | SUPER ADMIN - UPDATE CATEGORY
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->ignore($category->id),
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update name
        |--------------------------------------------------------------------------
        */

        if ($request->filled('name')) {
            $category->name = trim($validated['name']);
        }


        /*
        |--------------------------------------------------------------------------
        | Update image
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('image')) {

            if (
                $category->image &&
                Storage::disk('public')->exists($category->image)
            ) {
                Storage::disk('public')->delete($category->image);
            }

            $category->image = $request
                ->file('image')
                ->store('categories', 'public');
        }

        $category->save();

        $category->image_url = $category->image
            ? asset('storage/' . $category->image)
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | SUPER ADMIN - DELETE CATEGORY
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Do not delete category if products are using it
        |--------------------------------------------------------------------------
        */

        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Cannot delete this category because products are using it.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | Delete category image
        |--------------------------------------------------------------------------
        */

        if (
            $category->image &&
            Storage::disk('public')->exists($category->image)
        ) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | TENANT / COMPANY - VIEW MASTER CATEGORIES
    |--------------------------------------------------------------------------
    |
    | Companies can SEE all GreenHaven categories.
    |
    | Companies CANNOT:
    |
    | - create categories
    | - edit categories
    | - delete categories
    |
    */

    public function tenantIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        if (
            !$user ||
            $user->role !== 'company' ||
            !$user->company_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Company account required.',
            ], 403);
        }

        $categories = Category::query()
            ->withCount('products')
            ->orderBy('name')
            ->get();

        $categories->transform(
            function ($category) {
                $category->image_url = $category->image
                    ? asset('storage/' . $category->image)
                    : null;

                return $category;
            }
        );

        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' =>
                'GreenHaven master categories retrieved successfully.',
        ]);
    }
}