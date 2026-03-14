<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->when($request->active, fn($q) => $q->where('is_active', true))
            ->withCount('activeProducts')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:categories',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;

        $category = Category::create($data);

        return response()->json($category, 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($category->load('products'));
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:100|unique:categories,name,' . $category->id,
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return response()->json($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with existing products.'
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
