<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('product.view');

        $query = Product::query();

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%")
                    ->orWhere('barcode', 'like', "%{$keyword}%");
            });
        }

        if ($request->has('on_sale')) {
            $query->where('status', 'on_sale');
        }

        $user = $request->user();
        if ($user->isSupplier()) {
            $query->where('supplier_id', $user->supplier_id);
        }

        $products = $query->with(['category', 'supplier'])
            ->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($products);
    }

    public function show(Product $product)
    {
        $this->authorize('product.view');

        return response()->json($product->load(['category', 'supplier', 'inventory']));
    }

    public function store(Request $request)
    {
        $this->authorize('product.create');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products',
            'barcode' => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'specification' => 'nullable|string|max:255',
            'unit' => 'required|string|max:20',
            'cost_price' => 'required|numeric|min:0',
            'wholesale_price' => 'required|numeric|min:0',
            'retail_price' => 'required|numeric|min:0',
            'agent_price' => 'required|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'safety_stock' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'status' => 'in:draft,on_sale,off_sale,discontinued',
        ]);

        $user = $request->user();
        if ($user->isSupplier() && $user->supplier_id != $validated['supplier_id']) {
            return response()->json(['message' => 'You can only create products for your own supplier.'], 403);
        }

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product,
        ], 201);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize('product.edit');

        $user = $request->user();
        if ($user->isSupplier() && $user->supplier_id != $product->supplier_id) {
            return response()->json(['message' => 'You can only edit products for your own supplier.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => 'sometimes|string|max:100|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'specification' => 'nullable|string|max:255',
            'unit' => 'sometimes|string|max:20',
            'cost_price' => 'sometimes|numeric|min:0',
            'wholesale_price' => 'sometimes|numeric|min:0',
            'retail_price' => 'sometimes|numeric|min:0',
            'agent_price' => 'sometimes|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'safety_stock' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'status' => 'in:draft,on_sale,off_sale,discontinued',
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product,
        ]);
    }

    public function destroy(Product $product)
    {
        $this->authorize('product.delete');

        $user = request()->user();
        if ($user->isSupplier() && $user->supplier_id != $product->supplier_id) {
            return response()->json(['message' => 'You can only delete products for your own supplier.'], 403);
        }

        if ($product->orderItems()->count() > 0) {
            return response()->json(['message' => 'Cannot delete product with existing orders.'], 400);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function toggleOnSale(Product $product)
    {
        $this->authorize('product.edit');

        $newStatus = $product->status === 'on_sale' ? 'off_sale' : 'on_sale';
        $product->update(['status' => $newStatus]);

        return response()->json([
            'message' => "Product {$newStatus} successfully",
            'product' => $product,
        ]);
    }

    public function updateStock(Request $request, Product $product)
    {
        $this->authorize('inventory.edit');

        $validated = $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:in,out',
            'remark' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($product, $validated) {
            $quantityChange = $validated['type'] === 'in' ? $validated['quantity'] : -$validated['quantity'];
            $newStock = $product->stock_quantity + $quantityChange;

            if ($newStock < 0) {
                return response()->json(['message' => 'Insufficient stock.'], 400);
            }

            $product->update(['stock_quantity' => $newStock]);

            return response()->json([
                'message' => 'Stock updated successfully',
                'product' => $product,
            ]);
        });
    }
}
