<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theater;
use App\Models\Branch;
use App\Http\Requests\Admin\StoreTheaterRequest;
use App\Http\Requests\Admin\UpdateTheaterRequest;
use Illuminate\Http\Request;

class TheaterController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $theaters = Theater::query()
            ->with('branch')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $branches = Branch::where('is_active', true)->get();

        return view('admin.theaters.index', compact('theaters', 'branches', 'search'));
    }

    public function store(StoreTheaterRequest $request)
    {
        $validated = $request->validated();
        $validated['status'] = $request->has('status') ? 1 : 0;

        Theater::create($validated);

        return redirect()->route('admin.theaters.index')->with('success', 'Tạo rạp chiếu thành công.');
    }

    public function update(UpdateTheaterRequest $request, Theater $theater)
    {
        $validated = $request->validated();
        $validated['status'] = $request->has('status') ? 1 : 0;

        $theater->update($validated);

        return redirect()->route('admin.theaters.index')->with('success', 'Cập nhật rạp chiếu thành công.');
    }

    public function toggleActive(Theater $theater)
    {
        $theater->update(['status' => !$theater->status]);
        return response()->json(['success' => true, 'status' => $theater->status]);
    }

    public function destroy(Theater $theater)
    {
        $theater->delete();
        return redirect()->route('admin.theaters.index')->with('success', 'Xóa rạp chiếu thành công.');
    }
}
