<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Models\Theater;
use App\Models\Branch;
use App\Models\Format;
use App\Models\Sound;
use App\Models\VersionType;
use App\Models\SeatLayoutTemplate;
use App\Models\Seat;
use App\Models\SeatType;
use App\Http\Requests\Admin\StoreScreenRequest;
use App\Http\Requests\Admin\UpdateScreenRequest;
use App\Http\Requests\Admin\StoreFormatRequest;
use App\Http\Requests\Admin\UpdateFormatRequest;
use App\Http\Requests\Admin\StoreSoundRequest;
use App\Http\Requests\Admin\UpdateSoundRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScreenController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Screen::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            'include_references' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'theater_id' => ['nullable', 'integer', 'exists:theaters,id'],
            'status' => ['nullable', 'string', 'in:all,active,inactive'],
        ]);

        $search = $validated['search'] ?? null;
        $perPage = $validated['per_page'] ?? 10;

        $screens = Screen::query()
            ->with([
                'theater:id,branch_id,name',
                'format:id,name',
                'sound:id,name',
                'seatLayoutTemplate:id,template_name',
            ])
            ->when($validated['branch_id'] ?? null, fn ($query, $branchId) => $query->whereHas('theater', fn ($theaterQuery) => $theaterQuery->where('branch_id', $branchId)))
            ->when($validated['theater_id'] ?? null, fn ($query, $theaterId) => $query->where('theater_id', $theaterId))
            ->when(($validated['status'] ?? 'all') !== 'all', fn ($query) => $query->where('status', $validated['status'] === 'active'))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhereHas('theater', function ($subQ) use ($search) {
                          $subQ->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->latest()
            ->paginate($perPage);

        $response = [
            'screens' => $screens,
        ];

        if ($request->boolean('include_references')) {
            $response['formats'] = Format::query()
                ->select(['id', 'name', 'surcharge'])
                ->orderBy('name')
                ->get();
            $response['version_types'] = VersionType::query()
                ->select(['id', 'name', 'slug'])
                ->orderBy('name')
                ->get();
            $response['sounds'] = Sound::query()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get();
            $response['branches'] = Branch::query()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get();
            $response['theaters'] = Theater::active()
                ->select(['id', 'branch_id', 'name'])
                ->orderBy('name')
                ->get();
            $response['templates'] = SeatLayoutTemplate::active()
                ->select(['id', 'template_name'])
                ->orderBy('template_name')
                ->get();
        }

        return response()->json($response);
    }

    public function store(StoreScreenRequest $request)
    {
        $this->authorize('create', Screen::class);

        $validated = $request->validated();
        $validated['status'] = $request->has('status') ? 1 : 0;

        try {
            DB::beginTransaction();

            $screen = Screen::create($validated);
            $this->generateSeatsForScreen($screen);

            DB::commit();

            Log::info('Screen created', ['screen_id' => $screen->id, 'admin' => auth()->id()]);
            return response()->json(['success' => true, 'message' => 'Tạo phòng chiếu và sơ đồ ghế thành công.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating screen', ['error' => $e->getMessage(), 'admin' => auth()->id()]);
            return response()->json(['success' => false, 'message' => 'Không thể tạo phòng chiếu.'], 500);
        }
    }

    public function update(UpdateScreenRequest $request, Screen $screen)
    {
        $this->authorize('update', $screen);

        $validated = $request->validated();
        $validated['status'] = $request->has('status') ? 1 : 0;

        // Block layout template changes when screen has showtimes
        if (
            isset($validated['seat_layout_template_id']) &&
            $validated['seat_layout_template_id'] != $screen->seat_layout_template_id &&
            $screen->showtimes()->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể thay đổi sơ đồ ghế cho phòng chiếu đã có lịch chiếu.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $oldTemplateId = $screen->seat_layout_template_id;
            $screen->update($validated);

            // Re-generate seats only if template has changed
            if ($oldTemplateId != $screen->seat_layout_template_id) {
                $this->generateSeatsForScreen($screen);
            }

            DB::commit();

            Log::info('Screen updated', ['screen_id' => $screen->id, 'admin' => auth()->id()]);
            return response()->json(['success' => true, 'message' => 'Cập nhật phòng chiếu thành công.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating screen', ['screen_id' => $screen->id, 'error' => $e->getMessage(), 'admin' => auth()->id()]);
            return response()->json(['success' => false, 'message' => 'Không thể cập nhật phòng chiếu.'], 500);
        }
    }

    public function toggleActive(Request $request, Screen $screen)
    {
        $this->authorize('toggleStatus', $screen);

        $validated = $request->validate([
            'status' => ['required', 'boolean'],
        ]);

        // Block deactivation when screen has future showtimes
        if (!$validated['status'] && $screen->showtimes()->where('scheduled_at', '>=', now())->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tắt phòng chiếu đang có lịch chiếu trong tương lai.'
            ], 422);
        }

        $screen->update(['status' => $validated['status']]);

        Log::info('Screen status toggled', ['screen_id' => $screen->id, 'status' => $validated['status'], 'admin' => auth()->id()]);
        return response()->json(['success' => true, 'status' => $screen->status]);
    }

    public function destroy(Screen $screen)
    {
        $this->authorize('delete', $screen);

        // Block deletion when screen has showtimes
        if ($screen->showtimes()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa phòng chiếu đã có lịch chiếu.'
            ], 422);
        }

        try {
            DB::beginTransaction();
            // Delete associated seats
            Seat::where('screen_id', $screen->id)->delete();
            $screen->delete();
            DB::commit();

            Log::info('Screen deleted', ['screen_id' => $screen->id, 'admin' => auth()->id()]);
            return response()->json(['success' => true, 'message' => 'Xóa phòng chiếu thành công.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting screen', ['screen_id' => $screen->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Không thể xóa phòng chiếu.'], 500);
        }
    }

    public function showSeats(Screen $screen)
    {
        $this->authorize('view', $screen);

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
        $this->authorize('manageSeats', $screen);

        $validated = $request->validate([
            'seats' => ['required', 'array', 'max:500'],
            'seats.*.id' => ['required', 'integer', 'exists:seats,id'],
            'seats.*.status' => ['required', 'boolean'],
        ]);

        try {
            DB::transaction(function () use ($screen, $validated) {
                foreach ($validated['seats'] as $seat) {
                    Seat::where('id', $seat['id'])
                        ->where('screen_id', $screen->id)
                        ->lockForUpdate()
                        ->update(['status' => $seat['status']]);
                }
            });

            // Recalculate and update screen capacity based on active seats
            $activeCount = $screen->seats()->where('status', 1)->count();
            $screen->update(['capacity' => $activeCount]);

            Log::info('Screen seats updated', ['screen_id' => $screen->id, 'seat_count' => count($validated['seats']), 'admin' => auth()->id()]);
            return response()->json(['success' => true, 'message' => 'Cập nhật trạng thái ghế thành công.']);
        } catch (\Exception $e) {
            Log::error('Error updating screen seats', ['screen_id' => $screen->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Không thể cập nhật trạng thái ghế.'], 500);
        }
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
        // Explicit dependency check before deletion
        if ($format->screens()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa định dạng vì đang có phòng chiếu sử dụng.'
            ], 422);
        }

        try {
            $format->delete();
            Log::info('Format deleted', ['format_id' => $format->id, 'admin' => auth()->id()]);
            return response()->json(['success' => true, 'message' => 'Xóa định dạng chiếu thành công.']);
        } catch (\Exception $e) {
            Log::error('Error deleting format', ['format_id' => $format->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Không thể xóa định dạng.'], 500);
        }
    }

    public function storeSound(StoreSoundRequest $request)
    {
        $this->authorize('create', Sound::class);

        $sound = Sound::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tạo định dạng âm thanh thành công.',
            'data' => $sound,
        ], 201);
    }

    public function updateSound(UpdateSoundRequest $request, Sound $sound)
    {
        $this->authorize('update', $sound);

        $sound->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật định dạng âm thanh thành công.',
            'data' => $sound,
        ]);
    }

    public function destroySound(Sound $sound)
    {
        $this->authorize('delete', $sound);

        if ($sound->screens()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa định dạng âm thanh đang được phòng chiếu sử dụng.',
            ], 422);
        }

        $sound->delete();

        Log::info('Sound deleted', ['sound_id' => $sound->id, 'admin' => auth()->id()]);

        return response()->json([
            'success' => true,
            'message' => 'Xóa định dạng âm thanh thành công.',
        ]);
    }

    /* ── Helper: Seat Generation ─────────────────────────────────────── */
    private function generateSeatsForScreen(Screen $screen)
    {
        $template = $screen->seatLayoutTemplate;
        if (!$template) {
            throw new \RuntimeException('Seat layout template not found.');
        }

        // Block seat regeneration if screen has showtimes (double-check safety)
        if ($screen->showtimes()->exists()) {
            throw new \RuntimeException('Cannot regenerate seats for screen with showtimes.');
        }

        // Delete existing seats
        Seat::where('screen_id', $screen->id)->delete();

        $regularCount = $template->regular_seat_rows;
        $vipCount = $template->vip_seat_rows;
        $coupleCount = $template->couple_seat_rows;

        $seatTypes = SeatType::all()->keyBy('name');
        $standardType = $seatTypes->get('Standard') ?? $seatTypes->first();

        if (!$standardType) {
            throw new \RuntimeException('No seat types configured. Please seed SeatType data.');
        }

        $vipType = $seatTypes->get('VIP') ?? $standardType;
        $coupleType = $seatTypes->get('Couple') ?? $vipType;

        // Validate and parse seat matrix format
        $rows = 0;
        $cols = 0;

        if (preg_match('/^\d+x\d+$/', $template->seat_matrix)) {
            $parts = explode('x', $template->seat_matrix);
            $rows = (int) $parts[0];
            $cols = (int) $parts[1];
        } else {
            // Try to parse format: A=11111, B=11111, C=11111, D=11111
            $parts = array_filter(array_map('trim', explode(',', $template->seat_matrix)));
            if (!empty($parts)) {
                $rows = count($parts);
                $firstPart = explode('=', $parts[0]);
                if (count($firstPart) === 2) {
                    $cols = strlen(trim($firstPart[1]));
                }
            }
        }

        if ($rows < 1 || $rows > 30 || $cols < 1 || $cols > 30) {
            throw new \RuntimeException('Invalid seat matrix format or dimensions. Expected format: ROWSxCOLS (e.g., 10x12) or A=11111, B=11111. Dimensions must be between 1-30.');
        }

        $seatsToInsert = [];
        $totalCapacity = 0;

        for ($r = 0; $r < $rows; $r++) {
            // Excel-style row labels: A-Z, AA-AZ, BA-BZ, etc.
            $rowLabel = $this->generateRowLabel($r);

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

    /**
     * Generate Excel-style row label for seat generation.
     * 0 => A, 1 => B, ..., 25 => Z, 26 => AA, 27 => AB, etc.
     */
    private function generateRowLabel(int $index): string
    {
        $label = '';
        $index++; // Convert 0-based to 1-based for calculation
        
        while ($index > 0) {
            $index--; // Adjust for 0-based alphabet indexing
            $label = chr(65 + ($index % 26)) . $label;
            $index = (int) ($index / 26);
        }
        
        return $label;
    }
}
