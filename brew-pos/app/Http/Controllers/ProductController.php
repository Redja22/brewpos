<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['category', 'inventory'])
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->active, fn($q) => $q->where('is_active', true))
            ->when($request->available, fn($q) => $q->where('is_available', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'sku' => 'nullable|string|unique:products',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'track_inventory' => 'boolean',
            'initial_stock' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'image' => 'nullable|file|image|max:2048', // ✅ added file|
        ]);

        $data['slug'] = Str::slug($data['name']) . '-' . uniqid();

        // ✅ Only store if actual file uploaded
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            unset($data['image']); // ✅ Remove from data if not a real file
        }

        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_available'] = $data['is_available'] ?? true;
        $data['track_inventory'] = $data['track_inventory'] ?? false;

        $product = Product::create($data);

        Inventory::create([
            'product_id' => $product->id,
            'quantity' => $data['initial_stock'] ?? 0,
            'low_stock_threshold' => $data['low_stock_threshold'] ?? 10,
            'unit' => 'pcs',
        ]);

        return response()->json($product->load(['category', 'inventory']), 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load(['category', 'inventory']));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'sku' => 'nullable|string|unique:products,sku,' . $product->id,
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'track_inventory' => 'boolean',
            'image' => 'nullable|file|image|max:2048', // ✅ added file|
        ]);

        // ✅ Only process image if actual file uploaded
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            unset($data['image']); // ✅ Don't overwrite existing image
        }

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']) . '-' . $product->id;
        }

        $product->update($data);

        return response()->json($product->load(['category', 'inventory']));
    }
    public function destroy(Product $product): JsonResponse
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }
}
