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
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.branches.index', compact('branches', 'search'));
    }

    public function store(StoreBranchRequest $request)
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->has('is_active');

        Branch::create($validated);

        return redirect()->route('admin.branches.index')->with('success', 'Tạo chi nhánh thành công.');
    }

    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->has('is_active');

        $branch->update($validated);

        return redirect()->route('admin.branches.index')->with('success', 'Cập nhật chi nhánh thành công.');
    }

    public function toggleActive(Branch $branch)
    {
        $branch->update(['is_active' => !$branch->is_active]);
        return response()->json(['success' => true, 'is_active' => $branch->is_active]);
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();
        return redirect()->route('admin.branches.index')->with('success', 'Xóa chi nhánh thành công.');
    }
}
