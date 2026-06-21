<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('inventory.view');

        $query = Inventory::query();

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('keyword')) {
            $keyword = $request->keyword;
            $query->whereHas('product', function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%");
            })->orWhere('batch_no', 'like', "%{$keyword}%");
        }

        if ($request->has('low_stock')) {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('stock_quantity', '<=', 'safety_stock');
            });
        }

        $user = $request->user();
        if ($user->isSupplier()) {
            $query->where('supplier_id', $user->supplier_id);
        }

        $inventory = $query->with(['product', 'supplier'])
            ->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($inventory);
    }

    public function show(Inventory $inventory)
    {
        $this->authorize('inventory.view');

        return response()->json($inventory->load(['product', 'supplier']));
    }

    public function store(Request $request)
    {
        $this->authorize('inventory.edit');

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'quantity' => 'required|integer|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'batch_no' => 'nullable|string',
            'expiry_date' => 'nullable|date',
            'location' => 'nullable|string',
            'remark' => 'nullable|string',
        ]);

        $user = $request->user();
        if ($user->isSupplier() && $user->supplier_id != $validated['supplier_id']) {
            return response()->json(['message' => 'You can only manage inventory for your own supplier.'], 403);
        }

        return DB::transaction(function () use ($validated) {
            $inventory = Inventory::create(array_merge($validated, [
                'available_quantity' => $validated['quantity'],
                'reserved_quantity' => 0,
            ]));

            $product = Product::find($validated['product_id']);
            $product->increment('stock_quantity', $validated['quantity']);

            return response()->json([
                'message' => 'Inventory created successfully',
                'inventory' => $inventory,
            ], 201);
        });
    }

    public function update(Request $request, Inventory $inventory)
    {
        $this->authorize('inventory.edit');

        $user = $request->user();
        if ($user->isSupplier() && $user->supplier_id != $inventory->supplier_id) {
            return response()->json(['message' => 'You can only manage inventory for your own supplier.'], 403);
        }

        $validated = $request->validate([
            'quantity' => 'sometimes|integer|min:0',
            'unit_cost' => 'sometimes|numeric|min:0',
            'batch_no' => 'nullable|string',
            'expiry_date' => 'nullable|date',
            'location' => 'nullable|string',
            'remark' => 'nullable|string',
        ]);

        if (isset($validated['quantity'])) {
            $difference = $validated['quantity'] - $inventory->quantity;
            $availableDiff = $validated['quantity'] - $inventory->quantity;

            if ($inventory->available_quantity + $availableDiff < 0) {
                return response()->json(['message' => 'Available quantity cannot be negative.'], 400);
            }

            $inventory->available_quantity += $availableDiff;

            $product = $inventory->product;
            $product->increment('stock_quantity', $difference);
        }

        $inventory->update($validated);

        return response()->json([
            'message' => 'Inventory updated successfully',
            'inventory' => $inventory,
        ]);
    }

    public function destroy(Inventory $inventory)
    {
        $this->authorize('inventory.edit');

        $user = request()->user();
        if ($user->isSupplier() && $user->supplier_id != $inventory->supplier_id) {
            return response()->json(['message' => 'You can only manage inventory for your own supplier.'], 403);
        }

        return DB::transaction(function () use ($inventory) {
            $product = $inventory->product;
            $product->decrement('stock_quantity', $inventory->quantity);

            $inventory->delete();

            return response()->json(['message' => 'Inventory deleted successfully']);
        });
    }

    public function adjust(Request $request, Inventory $inventory)
    {
        $this->authorize('inventory.edit');

        $validated = $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:in,out,adjust',
            'remark' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($inventory, $validated) {
            $quantity = $validated['quantity'];
            if ($validated['type'] === 'out') {
                $quantity = -$quantity;
            } elseif ($validated['type'] === 'adjust') {
                $quantity = $validated['quantity'] - $inventory->quantity;
            }

            $newQuantity = $inventory->quantity + $quantity;
            if ($newQuantity < 0) {
                return response()->json(['message' => 'Quantity cannot be negative.'], 400);
            }

            $newAvailable = $inventory->available_quantity + $quantity;
            if ($newAvailable < 0) {
                return response()->json(['message' => 'Available quantity cannot be negative.'], 400);
            }

            $inventory->quantity = $newQuantity;
            $inventory->available_quantity = $newAvailable;
            $inventory->save();

            $product = $inventory->product;
            $product->increment('stock_quantity', $quantity);

            return response()->json([
                'message' => 'Inventory adjusted successfully',
                'inventory' => $inventory,
            ]);
        });
    }

    public function lowStock(Request $request)
    {
        $this->authorize('inventory.view');

        $query = Product::whereColumn('stock_quantity', '<=', 'safety_stock')
            ->where('stock_quantity', '>=', 0);

        $user = $request->user();
        if ($user->isSupplier()) {
            $query->where('supplier_id', $user->supplier_id);
        }

        $products = $query->with(['category', 'supplier'])
            ->orderBy('stock_quantity', 'asc')
            ->paginate($request->per_page ?? 20);

        return response()->json($products);
    }
}
