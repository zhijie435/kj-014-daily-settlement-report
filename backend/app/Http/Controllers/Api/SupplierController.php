<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('supplier.view');

        $query = Supplier::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('company_name', 'like', "%{$keyword}%")
                    ->orWhere('contact_person', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        $suppliers = $query->withCount(['products', 'orders'])
            ->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($suppliers);
    }

    public function show(Supplier $supplier)
    {
        $this->authorize('supplier.view');

        return response()->json($supplier->load(['users', 'products' => function ($q) {
            $q->take(10);
        }]));
    }

    public function store(Request $request)
    {
        $this->authorize('supplier.create');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'business_license' => 'nullable|string|max:255',
            'contact_person' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
            'status' => 'in:pending,active,suspended,rejected',
            'remark' => 'nullable|string',
            'user_email' => 'nullable|email|unique:users,email',
            'user_password' => 'nullable|min:6',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $supplier = Supplier::create($validated);

            if ($request->user_email && $request->user_password) {
                $user = User::create([
                    'name' => $validated['contact_person'],
                    'email' => $request->user_email,
                    'phone' => $validated['phone'],
                    'password' => bcrypt($request->user_password),
                    'user_type' => 'supplier',
                    'supplier_id' => $supplier->id,
                    'is_active' => true,
                ]);
                $user->assignRole('supplier');
            }

            return response()->json([
                'message' => 'Supplier created successfully',
                'supplier' => $supplier,
            ], 201);
        });
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorize('supplier.edit');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'business_license' => 'nullable|string|max:255',
            'contact_person' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
            'status' => 'in:pending,active,suspended,rejected',
            'remark' => 'nullable|string',
        ]);

        $supplier->update($validated);

        return response()->json([
            'message' => 'Supplier updated successfully',
            'supplier' => $supplier,
        ]);
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorize('supplier.delete');

        if ($supplier->products()->count() > 0 || $supplier->orders()->count() > 0) {
            throw ValidationException::withMessages([
                'supplier' => ['Cannot delete supplier with existing products or orders.'],
            ]);
        }

        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted successfully']);
    }

    public function approve(Supplier $supplier)
    {
        $this->authorize('supplier.edit');

        $supplier->update(['status' => 'active']);

        return response()->json([
            'message' => 'Supplier approved successfully',
            'supplier' => $supplier,
        ]);
    }

    public function reject(Request $request, Supplier $supplier)
    {
        $this->authorize('supplier.edit');

        $request->validate(['remark' => 'required|string']);

        $supplier->update([
            'status' => 'rejected',
            'remark' => $request->remark,
        ]);

        return response()->json([
            'message' => 'Supplier rejected successfully',
            'supplier' => $supplier,
        ]);
    }
}
