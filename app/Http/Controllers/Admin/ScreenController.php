<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Models\Theater;
use App\Models\Format;
use App\Models\VersionType;
use App\Models\SeatLayoutTemplate;
use App\Models\Seat;
use App\Models\SeatType;
use App\Http\Requests\Admin\StoreScreenRequest;
use App\Http\Requests\Admin\UpdateScreenRequest;
use App\Http\Requests\Admin\StoreFormatRequest;
use App\Http\Requests\Admin\UpdateFormatRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScreenController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $screens = Screen::query()
            ->with(['theater', 'format', 'seatLayoutTemplate'])
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhereHas('theater', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
            })
            ->latest()
            ->paginate(10);

        $formats = Format::latest()->get();
        $versionTypes = VersionType::latest()->get();
        $theaters = Theater::active()->get();
        $templates = SeatLayoutTemplate::active()->get();

        return response()->json([
            'screens' => $screens,
            'formats' => $formats,
            'version_types' => $versionTypes,
            'theaters' => $theaters,
            'templates' => $templates
        ]);
    }

    public function store(StoreScreenRequest $request)
    {
        $validated = $request->validated();
        $validated['status'] = $request->has('status') ? 1 : 0;

        try {
            DB::beginTransaction();

            $screen = Screen::create($validated);
            $this->generateSeatsForScreen($screen);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Tạo phòng chiếu và sơ đồ ghế thành công.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating screen: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }

    public function update(UpdateScreenRequest $request, Screen $screen)
    {
        $validated = $request->validated();
        $validated['status'] = $request->has('status') ? 1 : 0;

        try {
            DB::beginTransaction();

            $oldTemplateId = $screen->seat_layout_template_id;
            $screen->update($validated);

            // Re-generate seats only if template has changed
            if ($oldTemplateId != $screen->seat_layout_template_id) {
                $this->generateSeatsForScreen($screen);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Cập nhật phòng chiếu thành công.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating screen: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }

    public function toggleActive(Screen $screen)
    {
        $screen->update(['status' => !$screen->status]);
        return response()->json(['success' => true, 'status' => $screen->status]);
    }

    public function destroy(Screen $screen)
    {
        try {
            DB::beginTransaction();
            // Delete associated seats
            Seat::where('screen_id', $screen->id)->delete();
            $screen->delete();
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Xóa phòng chiếu thành công.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting screen: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Không thể xóa phòng chiếu: ' . $e->getMessage()], 500);
        }
    }

    public function showSeats(Screen $screen)
    {
        $screen->load(['theater', 'format', 'seatLayoutTemplate']);

        $seats = $screen->seats()
            ->with('seatType')
            ->orderBy('row_index')
            ->orderBy('column_index')
            ->get();

        return response()->json([
            'screen' => $screen,
            'seats' => $seats
        ]);
    }

    public function updateSeats(Request $request, Screen $screen)
    {
        $seats = $request->input('seats', []);

        // Optimizing with a loop since we just update status
        foreach ($seats as $id => $status) {
            Seat::where('id', $id)->where('screen_id', $screen->id)->update(['status' => (bool)$status]);
        }

        return response()->json(['success' => true]);
    }

    /* ── Format CRUD Actions ────────────────────────────────────────── */
    public function storeFormat(StoreFormatRequest $request)
    {
        $format = Format::create($request->validated());
        return response()->json(['success' => true, 'message' => 'Tạo định dạng chiếu thành công.', 'data' => $format]);
    }

    public function updateFormat(UpdateFormatRequest $request, Format $format)
    {
        $format->update($request->validated());
        return response()->json(['success' => true, 'message' => 'Cập nhật định dạng chiếu thành công.', 'data' => $format]);
    }

    public function destroyFormat(Format $format)
    {
        try {
            $format->delete();
            return response()->json(['success' => true, 'message' => 'Xóa định dạng chiếu thành công.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Không thể xóa định dạng vì đang có phòng chiếu sử dụng.'], 400);
        }
    }

    /* ── Helper: Seat Generation ─────────────────────────────────────── */
    private function generateSeatsForScreen(Screen $screen)
    {
        $template = $screen->seatLayoutTemplate;
        if (!$template) {
            return;
        }

        // Delete existing seats
        Seat::where('screen_id', $screen->id)->delete();

        $regularCount = $template->regular_seat_rows;
        $vipCount = $template->vip_seat_rows;
        $coupleCount = $template->couple_seat_rows;

        $seatTypes = SeatType::all()->keyBy('name');
        $standardType = $seatTypes->get('Standard') ?? $seatTypes->first();
        $vipType = $seatTypes->get('VIP') ?? $standardType;
        $coupleType = $seatTypes->get('Couple') ?? $vipType;

        $parts = explode('x', $template->seat_matrix);
        $rows = (int) ($parts[0] ?? 12);
        $cols = (int) ($parts[1] ?? 12);

        $seatsToInsert = [];
        $totalCapacity = 0;

        for ($r = 0; $r < $rows; $r++) {
            $rowLabel = chr(65 + $r); // A, B, C, ...

            if ($r < $regularCount) {
                $type = $standardType;
            } elseif ($r < ($regularCount + $vipCount)) {
                $type = $vipType;
            } else {
                $type = $coupleType;
            }

            for ($c = 0; $c < $cols; $c++) {
                $seatNumber = $c + 1;
                $seatsToInsert[] = [
                    'screen_id' => $screen->id,
                    'seat_type_id' => $type->id,
                    'row' => $rowLabel,
                    'number' => (string) $seatNumber,
                    'row_index' => $r,
                    'column_index' => $c,
                    'label' => $rowLabel . $seatNumber,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $totalCapacity++;
            }
        }

        if (!empty($seatsToInsert)) {
            Seat::insert($seatsToInsert);
        }

        $screen->update(['capacity' => $totalCapacity]);
    }
}
