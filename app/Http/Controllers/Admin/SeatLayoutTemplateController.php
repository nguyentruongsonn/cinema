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
        $status = $request->input('status'); // 'all', 'published', 'draft'

        $query = SeatLayoutTemplate::query()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('template_name', 'like', "%{$search}%")
                       ->orWhere('description', 'like', "%{$search}%")
                       ->orWhere('seat_matrix', 'like', "%{$search}%");
                });
            });

        if ($status === 'published') {
            $query->where('status', true);
        } elseif ($status === 'draft') {
            $query->where('status', false);
        }

        $templates = $query->latest()->paginate(10);

        return response()->json($templates);
    }

    public function store(StoreSeatLayoutTemplateRequest $request)
    {
        $validated = $request->validated();
        $validated['status'] = $request->has('status') ? 1 : 0;

        $template = SeatLayoutTemplate::create($validated);

        return response()->json(['success' => true, 'message' => 'Tạo mẫu sơ đồ ghế thành công.', 'data' => $template]);
    }

    public function update(UpdateSeatLayoutTemplateRequest $request, SeatLayoutTemplate $seatLayoutTemplate)
    {
        $validated = $request->validated();
        $validated['status'] = $request->has('status') ? 1 : 0;

        $seatLayoutTemplate->update($validated);

        return response()->json(['success' => true, 'message' => 'Cập nhật mẫu sơ đồ ghế thành công.']);
    }

    public function toggleActive(SeatLayoutTemplate $seatLayoutTemplate)
    {
        $seatLayoutTemplate->update(['status' => !$seatLayoutTemplate->status]);

        return response()->json(['success' => true, 'status' => $seatLayoutTemplate->status]);
    }

    public function destroy(SeatLayoutTemplate $seatLayoutTemplate)
    {
        $seatLayoutTemplate->delete();

        return response()->json(['success' => true, 'message' => 'Xóa mẫu sơ đồ ghế thành công.']);
    }
}
