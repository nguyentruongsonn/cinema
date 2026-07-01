<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Http\Requests\Admin\StoreBranchRequest;
use App\Http\Requests\Admin\UpdateBranchRequest;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $branches = Branch::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10);

        return response()->json($branches);
    }

    public function store(StoreBranchRequest $request)
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->has('is_active') ? 1 : 0;

        $branch = Branch::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tạo chi nhánh thành công.',
            'data' => $branch
        ], 201);
    }

    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->has('is_active') ? 1 : 0;

        $branch->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật chi nhánh thành công.',
            'data' => $branch
        ]);
    }

    public function toggleActive(Branch $branch)
    {
        $branch->update(['is_active' => !$branch->is_active]);
        return response()->json(['success' => true, 'is_active' => $branch->is_active]);
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();
        return response()->json(['success' => true, 'message' => 'Xóa chi nhánh thành công.']);
    }
}
