<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('sort', 'asc')
            ->get();

        return response()->json($categories);
    }

    public function all(Request $request)
    {
        $query = Category::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $categories = $query->orderBy('sort', 'asc')->get();

        return response()->json($categories);
    }

    public function show(Category $category)
    {
        return response()->json($category->load(['parent', 'children', 'products']));
    }

    public function store(Request $request)
    {
        $this->authorize('category.manage');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:categories',
            'parent_id' => 'nullable|exists:categories,id',
            'sort' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category,
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        $this->authorize('category.manage');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:categories,code,' . $category->id,
            'parent_id' => 'nullable|exists:categories,id',
            'sort' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category,
        ]);
    }

    public function destroy(Category $category)
    {
        $this->authorize('category.manage');

        if ($category->products()->count() > 0 || $category->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with existing products or subcategories.'
            ], 400);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    public function tree()
    {
        $categories = Category::with('children.children')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort', 'asc')
            ->get();

        return response()->json($categories);
    }
}
