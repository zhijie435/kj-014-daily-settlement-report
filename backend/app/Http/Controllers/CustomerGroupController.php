<?php

namespace App\Http\Controllers;

use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CustomerGroup::query();

        if ($request->has('active')) {
            $query->active();
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $customerGroups = $query->ordered()->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $customerGroups->items(),
            'pagination' => [
                'total' => $customerGroups->total(),
                'per_page' => $customerGroups->perPage(),
                'current_page' => $customerGroups->currentPage(),
                'last_page' => $customerGroups->lastPage(),
            ],
        ]);
    }

    public function all(): JsonResponse
    {
        $customerGroups = CustomerGroup::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $customerGroups,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $customerGroup = CustomerGroup::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $customerGroup,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:customer_groups,code',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'settings' => 'nullable|array',
        ]);

        $customerGroup = CustomerGroup::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer group created successfully.',
            'data' => $customerGroup,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $customerGroup = CustomerGroup::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'code' => 'string|max:100|unique:customer_groups,code,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'settings' => 'nullable|array',
        ]);

        $customerGroup->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer group updated successfully.',
            'data' => $customerGroup,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $customerGroup = CustomerGroup::findOrFail($id);
        $customerGroup->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer group deleted successfully.',
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $customerGroup = CustomerGroup::withTrashed()->findOrFail($id);
        $customerGroup->restore();

        return response()->json([
            'success' => true,
            'message' => 'Customer group restored successfully.',
            'data' => $customerGroup,
        ]);
    }
}
