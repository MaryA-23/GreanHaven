<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;    

class CategoryController extends Controller
{
    /*    
    * Display a listing of the category.
     */

    public function index(Request $request)
    {
        $categories = Category::query()
       ->when($request->filled('search'), function ($query) use ($request) {
        $query->where('name', 'like', '%' . $request->search . '%');
            })
         ->withCount('products') // If you have products relationship
            ->orderBy('name')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' => 'Categories retrieved successfully'
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:categories,name|max:255',
        ]);

        $category = Category::create([
            'name' => $request->name
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category
        ], 201);
    }

    public function show($id)
    {
        $category = Category::with('products')->find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $request->validate([
            'name' => 'required|string|unique:categories,name,' . $id . '|max:255',
        ]);

        $category->update([
            'name' => $request->name
        ]);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category
        ]);
    }

    public function destroy($id)
    {
        $category = Category::find($id);

       // Prevent deletion if category has products
       if (!$category) {
    return response()->json(['message' => 'Category not found'], 404);
    }

        if ($category->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with associated products'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]); 
    }
    
}
