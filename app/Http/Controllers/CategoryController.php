<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    /*
     * Display a listing of categories.
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
            'message' => 'Categories retrieved successfully'
        ]);
    }


    /*
     * Create category.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' =>
                'required|string|unique:categories,name|max:255',

            'image' =>
                'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);


        $imagePath = $request
            ->file('image')
            ->store('categories', 'public');


        $category = Category::create([
            'name' => $request->name,
            'image' => $imagePath
        ]);


        $category->image_url =
            asset('storage/' . $category->image);


        return response()->json([
            'message' =>
                'Category created successfully',

            'category' =>
                $category
        ], 201);
    }


    /*
     * Show category.
     */
    public function show($id)
    {
        $category = Category::with('products')
            ->find($id);


        if (!$category) {

            return response()->json([
                'message' =>
                    'Category not found'
            ], 404);

        }


        $category->image_url =
            $category->image
                ? asset(
                    'storage/' .
                    $category->image
                )
                : null;


        return response()->json(
            $category
        );
    }


    /*
     * Update category.
     */
    public function update(
        Request $request,
        $id
    ) {

        $category = Category::find($id);


        if (!$category) {

            return response()->json([
                'message' =>
                    'Category not found'
            ], 404);

        }


        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $id,
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);


        $category->name =
            $request->name;


        if ($request->hasFile('image')) {

            if (
                $category->image &&
                Storage::disk('public')
                    ->exists($category->image)
            ) {

                Storage::disk('public')
                    ->delete(
                        $category->image
                    );

            }


            $category->image =
                $request
                    ->file('image')
                    ->store(
                        'categories',
                        'public'
                    );

        }


        $category->save();


        $category->image_url =
            $category->image
                ? asset(
                    'storage/' .
                    $category->image
                )
                : null;


        return response()->json([
            'message' =>
                'Category updated successfully',

            'category' =>
                $category
        ]);
    }


    /*
     * Delete category.
     */
    public function destroy($id)
    {
        $category =
            Category::find($id);


        if (!$category) {

            return response()->json([
                'message' =>
                    'Category not found'
            ], 404);

        }


        /*
         * Prevent deletion if category
         * already has products.
         */
        if (
            $category
                ->products()
                ->count() > 0
        ) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Cannot delete category with associated products'
            ], 422);

        }


        /*
         * Delete category image.
         */
        if (
            $category->image &&
            Storage::disk('public')
                ->exists($category->image)
        ) {

            Storage::disk('public')
                ->delete(
                    $category->image
                );

        }


        $category->delete();


        return response()->json([
            'message' =>
                'Category deleted successfully'
        ]);
    }
}