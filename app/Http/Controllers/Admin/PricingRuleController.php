<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\DayRule;
use App\Models\TimeSlot;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PricingRuleController extends Controller
{
    use ApiResponse;

    // ─── Holidays CRUD ───────────────────────────────────────────────────

    public function getHolidays(): JsonResponse
    {
        try {
            $holidays = Holiday::orderBy('date')->get();
            return $this->successResponse($holidays);
        } catch (\Exception $e) {
            Log::error('Get holidays error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Không thể lấy danh sách ngày lễ', 500);
        }
    }

    public function storeHoliday(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'date' => ['required', 'string', 'regex:/^[0-3][0-9]-[0-1][0-9]$/'],
                'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
                'surcharge' => ['required', 'integer', 'min:0'],
                'status' => ['nullable', 'integer', 'in:0,1'],
            ]);

            if (!isset($validated['status'])) {
                $validated['status'] = 1;
            }

            $holiday = Holiday::create($validated);
            return $this->successResponse($holiday, 'Tạo ngày lễ thành công.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Dữ liệu không hợp lệ', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Store holiday error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Không thể lưu ngày lễ', 500);
        }
    }

    public function updateHoliday(Request $request, Holiday $holiday): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'date' => ['required', 'string', 'regex:/^[0-3][0-9]-[0-1][0-9]$/'],
                'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
                'surcharge' => ['required', 'integer', 'min:0'],
                'status' => ['nullable', 'integer', 'in:0,1'],
            ]);

            $holiday->update($validated);
            return $this->successResponse($holiday, 'Cập nhật ngày lễ thành công.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Dữ liệu không hợp lệ', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Update holiday error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Không thể cập nhật ngày lễ', 500);
        }
    }

    public function destroyHoliday(Holiday $holiday): JsonResponse
    {
        try {
            $holiday->delete();
            return $this->successResponse(null, 'Xóa ngày lễ thành công.');
        } catch (\Exception $e) {
            Log::error('Delete holiday error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Không thể xóa ngày lễ', 500);
        }
    }

    // ─── Day Rules CRUD ──────────────────────────────────────────────────

    public function getDayRules(): JsonResponse
    {
        try {
            $dayRules = DayRule::orderBy('day_of_week')->get();
            return $this->successResponse($dayRules);
        } catch (\Exception $e) {
            Log::error('Get day rules error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Không thể lấy quy tắc ngày', 500);
        }
    }

    public function updateDayRules(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'rules' => ['required', 'array'],
                'rules.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
                'rules.*.day_type' => ['required', 'string', 'in:weekday,weekend,happy_day'],
                'rules.*.surcharge' => ['required', 'integer', 'min:0'],
            ]);

            DB::transaction(function () use ($validated) {
                foreach ($validated['rules'] as $ruleData) {
                    DayRule::where('day_of_week', $ruleData['day_of_week'])->update([
                        'day_type' => $ruleData['day_type'],
                        'surcharge' => $ruleData['surcharge'],
                    ]);
                }
            });

            $updatedRules = DayRule::orderBy('day_of_week')->get();
            return $this->successResponse($updatedRules, 'Cập nhật quy tắc ngày thành công.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Dữ liệu quy tắc ngày không hợp lệ', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Update day rules error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Không thể cập nhật quy tắc ngày', 500);
        }
    }

    // ─── Time Slots CRUD ─────────────────────────────────────────────────

    public function getTimeSlots(): JsonResponse
    {
        try {
            $timeSlots = TimeSlot::orderBy('start_time')->get();
            return $this->successResponse($timeSlots);
        } catch (\Exception $e) {
            Log::error('Get time slots error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Không thể lấy danh sách khung giờ', 500);
        }
    }

    public function storeTimeSlot(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'start_time' => ['required', 'date_format:H:i:s'],
                'end_time' => ['required', 'date_format:H:i:s'],
                'surcharge' => ['required', 'integer', 'min:0'],
                'status' => ['nullable', 'integer', 'in:0,1'],
            ]);

            if (!isset($validated['status'])) {
                $validated['status'] = 1;
            }

            $timeSlot = TimeSlot::create($validated);
            return $this->successResponse($timeSlot, 'Tạo khung giờ thành công.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Dữ liệu không hợp lệ', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Store time slot error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Không thể lưu khung giờ', 500);
        }
    }

    public function updateTimeSlot(Request $request, TimeSlot $timeSlot): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'start_time' => ['required', 'date_format:H:i:s'],
                'end_time' => ['required', 'date_format:H:i:s'],
                'surcharge' => ['required', 'integer', 'min:0'],
                'status' => ['nullable', 'integer', 'in:0,1'],
            ]);

            $timeSlot->update($validated);
            return $this->successResponse($timeSlot, 'Cập nhật khung giờ thành công.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Dữ liệu không hợp lệ', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Update time slot error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Không thể cập nhật khung giờ', 500);
        }
    }

    public function destroyTimeSlot(TimeSlot $timeSlot): JsonResponse
    {
        try {
            $timeSlot->delete();
            return $this->successResponse(null, 'Xóa khung giờ thành công.');
        } catch (\Exception $e) {
            Log::error('Delete time slot error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Không thể xóa khung giờ', 500);
        }
    }

    public function toggleHolidayActive(Holiday $holiday): JsonResponse
    {
        try {
            DB::transaction(function () use ($holiday) {
                $locked = Holiday::where('id', $holiday->id)->lockForUpdate()->firstOrFail();
                $locked->update([
                    'status' => $locked->status === 1 ? 0 : 1
                ]);
            });
            $holiday->refresh();
            return $this->successResponse($holiday, 'Cập nhật trạng thái ngày lễ thành công.');
        } catch (\Exception $e) {
            Log::error('Toggle holiday active error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Không thể cập nhật trạng thái ngày lễ', 500);
        }
    }

    public function toggleTimeSlotActive(TimeSlot $timeSlot): JsonResponse
    {
        try {
            DB::transaction(function () use ($timeSlot) {
                $locked = TimeSlot::where('id', $timeSlot->id)->lockForUpdate()->firstOrFail();
                $locked->update([
                    'status' => $locked->status === 1 ? 0 : 1
                ]);
            });
            $timeSlot->refresh();
            return $this->successResponse($timeSlot, 'Cập nhật trạng thái khung giờ thành công.');
        } catch (\Exception $e) {
            Log::error('Toggle time slot active error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Không thể cập nhật trạng thái khung giờ', 500);
        }
    }
}
