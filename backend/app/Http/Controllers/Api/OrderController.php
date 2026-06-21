<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Distributor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('order.view');

        $query = Order::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('distributor_id')) {
            $query->where('distributor_id', $request->distributor_id);
        }

        if ($request->has('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('order_no', 'like', "%{$keyword}%");
            });
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $user = $request->user();
        if ($user->isSupplier()) {
            $query->where('supplier_id', $user->supplier_id);
        } elseif ($user->isDistributor()) {
            $query->where('distributor_id', $user->distributor_id);
            if ($user->isRegionalAgent() && $request->has('include_subordinate')) {
                $subordinateIds = Distributor::where('parent_id', $user->distributor_id)->pluck('id');
                $query->orWhereIn('distributor_id', $subordinateIds);
            }
        }

        $orders = $query->with(['supplier', 'distributor', 'createdBy', 'items'])
            ->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($orders);
    }

    public function show(Order $order)
    {
        $this->authorize('order.view');

        $user = request()->user();
        if ($user->isSupplier() && $user->supplier_id != $order->supplier_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($user->isDistributor() && $user->distributor_id != $order->distributor_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($order->load(['supplier', 'distributor', 'createdBy', 'items', 'items.product', 'payments']));
    }

    public function store(Request $request)
    {
        $this->authorize('order.create');

        $validated = $request->validate([
            'type' => 'required|in:supplier_purchase,distributor_order,agent_order',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'distributor_id' => 'nullable|exists:distributors,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'remark' => 'nullable|string',
        ]);

        $user = $request->user();

        if ($user->isDistributor()) {
            $validated['distributor_id'] = $user->distributor_id;
        }

        if (in_array($validated['type'], ['distributor_order', 'agent_order']) && !$validated['distributor_id']) {
            return response()->json(['message' => 'Distributor ID is required.'], 400);
        }

        if ($validated['type'] === 'supplier_purchase' && !$validated['supplier_id']) {
            return response()->json(['message' => 'Supplier ID is required.'], 400);
        }

        return DB::transaction(function () use ($validated, $user, $request) {
            $subtotal = 0;
            $orderItems = [];
            $distributor = isset($validated['distributor_id']) ? Distributor::find($validated['distributor_id']) : null;

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                $unitPrice = $product->cost_price;
                if ($distributor) {
                    $unitPrice = $product->getPriceForDistributor($distributor);
                }

                $itemSubtotal = $unitPrice * $item['quantity'];
                $subtotal += $itemSubtotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'specification' => $product->specification,
                    'unit' => $product->unit,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $itemSubtotal,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => $itemSubtotal,
                ];

                if ($product->stock_quantity < $item['quantity']) {
                    return response()->json([
                        'message' => "Insufficient stock for product: {$product->name}. Available: {$product->stock_quantity}, Requested: {$item['quantity']}"
                    ], 400);
                }
            }

            $total = $subtotal;

            $order = Order::create([
                'type' => $validated['type'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'distributor_id' => $validated['distributor_id'] ?? null,
                'created_by' => $user->id,
                'subtotal' => $subtotal,
                'tax' => 0,
                'discount' => 0,
                'shipping' => 0,
                'total' => $total,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
                'status' => 'pending',
                'shipping_address' => $validated['shipping_address'] ?? null,
                'billing_address' => $validated['billing_address'] ?? null,
                'remark' => $validated['remark'] ?? null,
            ]);

            foreach ($orderItems as $item) {
                $order->items()->create($item);

                $product = Product::find($item['product_id']);
                $product->decrement('stock_quantity', $item['quantity']);
            }

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order->load('items'),
            ], 201);
        });
    }

    public function update(Request $request, Order $order)
    {
        $this->authorize('order.edit');

        if (!$order->canBeCancelled()) {
            return response()->json(['message' => 'Order cannot be modified.'], 400);
        }

        $validated = $request->validate([
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'remark' => 'nullable|string',
        ]);

        $order->update($validated);

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $order,
        ]);
    }

    public function confirm(Order $order)
    {
        $this->authorize('order.approve');

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Only pending orders can be confirmed.'], 400);
        }

        $order->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Order confirmed successfully',
            'order' => $order,
        ]);
    }

    public function process(Order $order)
    {
        $this->authorize('order.edit');

        if ($order->status !== 'confirmed') {
            return response()->json(['message' => 'Only confirmed orders can be processed.'], 400);
        }

        $order->update(['status' => 'processing']);

        return response()->json([
            'message' => 'Order is now processing',
            'order' => $order,
        ]);
    }

    public function ship(Request $request, Order $order)
    {
        $user = request()->user();
        if ($user->isSupplier()) {
            $this->authorize('order.ship');
        } else {
            $this->authorize('order.edit');
        }

        if (!in_array($order->status, ['confirmed', 'processing'])) {
            return response()->json(['message' => 'Only confirmed or processing orders can be shipped.'], 400);
        }

        $request->validate(['tracking_no' => 'nullable|string']);

        $order->update([
            'status' => 'shipped',
            'tracking_no' => $request->tracking_no,
            'shipped_at' => now(),
        ]);

        return response()->json([
            'message' => 'Order shipped successfully',
            'order' => $order,
        ]);
    }

    public function deliver(Order $order)
    {
        $this->authorize('order.edit');

        if ($order->status !== 'shipped') {
            return response()->json(['message' => 'Only shipped orders can be delivered.'], 400);
        }

        $order->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        return response()->json([
            'message' => 'Order delivered successfully',
            'order' => $order,
        ]);
    }

    public function complete(Order $order)
    {
        $this->authorize('order.approve');

        if (!in_array($order->status, ['delivered', 'shipped'])) {
            return response()->json(['message' => 'Only delivered or shipped orders can be completed.'], 400);
        }

        $order->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Order completed successfully',
            'order' => $order,
        ]);
    }

    public function cancel(Order $order)
    {
        $this->authorize('order.edit');

        if (!$order->canBeCancelled()) {
            return response()->json(['message' => 'Order cannot be cancelled.'], 400);
        }

        return DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock_quantity', $item->quantity);
                }
            }

            $order->update(['status' => 'cancelled']);

            return response()->json([
                'message' => 'Order cancelled successfully',
                'order' => $order,
            ]);
        });
    }

    public function destroy(Order $order)
    {
        $this->authorize('order.delete');

        if (!$order->canBeCancelled()) {
            return response()->json(['message' => 'Order cannot be deleted.'], 400);
        }

        return DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock_quantity', $item['quantity']);
                }
            }

            $order->delete();

            return response()->json(['message' => 'Order deleted successfully']);
        });
    }

    public function statistics(Request $request)
    {
        $user = $request->user();
        $query = Order::query();

        if ($user->isSupplier()) {
            $query->where('supplier_id', $user->supplier_id);
        } elseif ($user->isDistributor()) {
            $query->where('distributor_id', $user->distributor_id);
        }

        $stats = [
            'total_orders' => $query->count(),
            'pending_orders' => (clone $query)->where('status', 'pending')->count(),
            'processing_orders' => (clone $query)->where('status', 'processing')->count(),
            'shipped_orders' => (clone $query)->where('status', 'shipped')->count(),
            'completed_orders' => (clone $query)->where('status', 'completed')->count(),
            'total_amount' => (clone $query)->sum('total'),
            'unpaid_amount' => (clone $query)->where('payment_status', '!=', 'paid')->sum(DB::raw('total - paid_amount')),
        ];

        return response()->json($stats);
    }
}
