<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSeatLayoutTemplateRequest;
use App\Http\Requests\Admin\UpdateSeatLayoutTemplateRequest;
use App\Models\SeatLayoutTemplate;
use Illuminate\Http\Request;

class SeatLayoutTemplateController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $baseQuery = SeatLayoutTemplate::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('template_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('seat_matrix', 'like', "%{$search}%");
                });
            })
            ->latest();

        $allTemplates       = (clone $baseQuery)->paginate(10)->appends($request->only('search'));
        $publishedTemplates = (clone $baseQuery)->where('status', true)->paginate(10)->appends($request->only('search'));
        $draftTemplates     = (clone $baseQuery)->where('status', false)->paginate(10)->appends($request->only('search'));

        return view('admin.seat-layout-templates.index', compact('allTemplates', 'publishedTemplates', 'draftTemplates', 'search'));
    }

    public function store(StoreSeatLayoutTemplateRequest $request)
    {
        $validated = $request->validated();
        $validated['status'] = $request->has('status') ? 1 : 0;

        SeatLayoutTemplate::create($validated);

        return redirect()->route('admin.seat-layout-templates.index')->with('success', 'Tạo mẫu sơ đồ ghế thành công.');
    }

    public function update(UpdateSeatLayoutTemplateRequest $request, SeatLayoutTemplate $seatLayoutTemplate)
    {
        $validated = $request->validated();
        $validated['status'] = $request->has('status') ? 1 : 0;

        $seatLayoutTemplate->update($validated);

        return redirect()->route('admin.seat-layout-templates.index')->with('success', 'Cập nhật mẫu sơ đồ ghế thành công.');
    }

    public function toggleActive(SeatLayoutTemplate $seatLayoutTemplate)
    {
        $seatLayoutTemplate->update(['status' => !$seatLayoutTemplate->status]);

        return response()->json(['success' => true, 'status' => $seatLayoutTemplate->status]);
    }

    public function destroy(SeatLayoutTemplate $seatLayoutTemplate)
    {
        $seatLayoutTemplate->delete();

        return redirect()->route('admin.seat-layout-templates.index')->with('success', 'Xóa mẫu sơ đồ ghế thành công.');
    }
}
