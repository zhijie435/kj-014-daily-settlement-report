<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributorController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('distributor.view');

        $query = Distributor::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

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

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        $distributors = $query->withCount(['orders'])
            ->with('parent')
            ->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($distributors);
    }

    public function show(Distributor $distributor)
    {
        $this->authorize('distributor.view');

        return response()->json($distributor->load(['users', 'parent', 'children']));
    }

    public function store(Request $request)
    {
        $this->authorize('distributor.create');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'business_license' => 'nullable|string|max:255',
            'type' => 'required|in:regional_agent,wholesaler',
            'region' => 'nullable|string|max:255',
            'contact_person' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
            'discount_rate' => 'nullable|integer|min:1|max:100',
            'status' => 'in:pending,active,suspended,rejected',
            'parent_id' => 'nullable|exists:distributors,id',
            'remark' => 'nullable|string',
            'user_email' => 'nullable|email|unique:users,email',
            'user_password' => 'nullable|min:6',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $distributor = Distributor::create($validated);

            if ($request->user_email && $request->user_password) {
                $user = User::create([
                    'name' => $validated['contact_person'],
                    'email' => $request->user_email,
                    'phone' => $validated['phone'],
                    'password' => bcrypt($request->user_password),
                    'user_type' => 'distributor',
                    'distributor_id' => $distributor->id,
                    'is_active' => true,
                ]);
                $user->assignRole($distributor->type === 'regional_agent' ? 'regional_agent' : 'distributor');
            }

            return response()->json([
                'message' => 'Distributor created successfully',
                'distributor' => $distributor,
            ], 201);
        });
    }

    public function update(Request $request, Distributor $distributor)
    {
        $this->authorize('distributor.edit');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'business_license' => 'nullable|string|max:255',
            'type' => 'sometimes|in:regional_agent,wholesaler',
            'region' => 'nullable|string|max:255',
            'contact_person' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
            'discount_rate' => 'nullable|integer|min:1|max:100',
            'status' => 'in:pending,active,suspended,rejected',
            'parent_id' => 'nullable|exists:distributors,id',
            'remark' => 'nullable|string',
        ]);

        $distributor->update($validated);

        return response()->json([
            'message' => 'Distributor updated successfully',
            'distributor' => $distributor,
        ]);
    }

    public function destroy(Distributor $distributor)
    {
        $this->authorize('distributor.delete');

        if ($distributor->orders()->count() > 0 || $distributor->children()->count() > 0) {
            throw ValidationException::withMessages([
                'distributor' => ['Cannot delete distributor with existing orders or subordinate distributors.'],
            ]);
        }

        $distributor->delete();

        return response()->json(['message' => 'Distributor deleted successfully']);
    }

    public function approve(Distributor $distributor)
    {
        $this->authorize('distributor.edit');

        $distributor->update(['status' => 'active']);

        return response()->json([
            'message' => 'Distributor approved successfully',
            'distributor' => $distributor,
        ]);
    }

    public function reject(Request $request, Distributor $distributor)
    {
        $this->authorize('distributor.edit');

        $request->validate(['remark' => 'required|string']);

        $distributor->update([
            'status' => 'rejected',
            'remark' => $request->remark,
        ]);

        return response()->json([
            'message' => 'Distributor rejected successfully',
            'distributor' => $distributor,
        ]);
    }
}
