<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use App\Models\Holiday;
use App\Models\DayRule;
use App\Models\TimeSlot;

class TicketPricingService
{
    public function calculate(
        string $format,
        Carbon $scheduledAt,
        string $customerType   = 'adult',
        bool   $isDoubleSeat   = false,
        int    $movieSurcharge = 0,
        array  $extraHolidays  = [],
        int    $formatSurcharge = 0,
        int    $seatSurcharge  = 0,
        ?array $theaterPricing = null
    ): array {
        $formatKey = in_array($format, ['2D', '3D'], true) ? $format : '2D';

        $tp = $theaterPricing ?? [];

        $dayOfWeek = $scheduledAt->dayOfWeek;
        $ddmm      = $scheduledAt->format('d-m');

        // Chuẩn hóa customer type
        $isReduced = in_array($customerType, ['student', 'child', 'senior'], true);
        $priceGroup = $isReduced ? 'student_child_senior' : 'adult';

        // ── Bước 1: Xác định khung giờ chiếu từ Database ────────────────
        $timeSlotObj = $this->resolveTimeSlotFromDb($scheduledAt);
        $timeSlot = $timeSlotObj ? $timeSlotObj->name : 'Thường';

        // ── Bước 2: Xác định ngày đặc biệt từ Database ──────────────────
        $dayType  = $this->resolveDayType($scheduledAt, $dayOfWeek, $ddmm, $extraHolidays);

        // ── Bước 3: Tính giá gốc và Phụ thu ─────────────────────────────
        $surcharges = [];

        if ($dayType === 'happy_day') {
            $basePrice = (int) ($tp['happy_day_price'] ?? 0);
        } else {
            $basePrice = (int) ($tp['base_price'] ?? 0);

            if ($dayType === 'holiday') {
                // Lấy phụ thu từ cấu hình ngày lễ cụ thể trong database
                $holidayObj = Holiday::where('date', $ddmm)
                    ->where(fn($q) => $q->whereNull('year')->orWhere('year', $scheduledAt->year))
                    ->first();
                $holidaySurcharge = $holidayObj ? $holidayObj->surcharge : ($tp['holiday_surcharge'] ?? 0);

                $surcharges['holiday'] = [
                    'label'  => 'Phụ thu ngày lễ',
                    'amount' => (int) $holidaySurcharge,
                ];
            } elseif ($dayType === 'weekend') {
                // Lấy phụ thu cuối tuần ưu tiên bảng giá rạp, sau đó đến cấu hình mặc định của ngày trong DB
                $dayRuleObj = DayRule::where('day_of_week', $dayOfWeek)->first();
                $weekendSurcharge = ($tp['weekend_surcharge'] ?? ($dayRuleObj ? $dayRuleObj->surcharge : 0));

                $surcharges['weekend'] = [
                    'label'  => 'Phụ thu cuối tuần',
                    'amount' => (int) $weekendSurcharge,
                ];
            }
        }

        // Ưu đãi đối tượng khách hàng (HSSV...)
        if ($isReduced && $dayType !== 'happy_day') {
            $studentDiscount = (int) ($tp['student_discount'] ?? 0);
            if ($studentDiscount > 0) {
                $surcharges['student_discount'] = [
                    'label'  => 'Ưu đãi đối tượng',
                    'amount' => -$studentDiscount,
                ];
            }
        }

        // Phụ thu khung giờ từ Database
        if ($timeSlotObj && $timeSlotObj->surcharge > 0) {
            $surcharges['time_slot'] = [
                'label'  => "Phụ thu khung giờ ({$timeSlotObj->name})",
                'amount' => (int) $timeSlotObj->surcharge,
            ];
        }

        // Phụ thu phim
        if ($movieSurcharge > 0) {
            $surcharges['movie'] = [
                'label'  => 'Phụ thu phim',
                'amount' => $movieSurcharge,
            ];
        }

        // Phụ thu định dạng (từ DB)
        if ($formatSurcharge > 0) {
            $surcharges['format'] = [
                'label'  => 'Phụ thu định dạng chiếu',
                'amount' => $formatSurcharge,
            ];
        }

        // Phụ thu loại ghế (từ DB)
        if ($seatSurcharge > 0) {
            $surcharges['seat_type'] = [
                'label'  => 'Phụ thu loại ghế',
                'amount' => $seatSurcharge,
            ];
        }

        $totalSurcharge = array_sum(array_column($surcharges, 'amount'));
        $totalPrice     = $basePrice + $totalSurcharge;

        return [
            'base_price'    => $basePrice,
            'surcharges'    => $surcharges,
            'total_price'   => $totalPrice,
            'day_type'      => $dayType,
            'time_slot'     => $timeSlot,
            'customer_type' => $customerType,
            'price_group'   => $priceGroup,
            'format'        => $formatKey,
        ];
    }

    public function calculateAll(
        string $format,
        Carbon $scheduledAt,
        bool   $isDoubleSeat   = false,
        int    $movieSurcharge = 0,
        array  $extraHolidays  = [],
        int    $formatSurcharge = 0,
        int    $seatSurcharge  = 0,
        ?array $theaterPricing = null
    ): array {
        $types = ['adult', 'student', 'child', 'senior'];
        $result = [];
        foreach ($types as $type) {
            $result[$type] = $this->calculate(
                $format, $scheduledAt, $type, $isDoubleSeat, $movieSurcharge, $extraHolidays, $formatSurcharge, $seatSurcharge, $theaterPricing
            );
        }
        return $result;
    }


    private array $resolvedTimeSlots = [];
    private array $resolvedDayTypes = [];

    private function resolveTimeSlotFromDb(Carbon $scheduledAt): ?TimeSlot
    {
        $timeStr = $scheduledAt->format('H:i:s');
        if (array_key_exists($timeStr, $this->resolvedTimeSlots)) {
            return $this->resolvedTimeSlots[$timeStr];
        }
        
        $slot = TimeSlot::all()->first(function ($slot) use ($timeStr) {
            $start = $slot->start_time;
            $end = $slot->end_time;
            
            if ($start <= $end) {
                return $timeStr >= $start && $timeStr <= $end;
            } else {
                // Khung giờ cắt qua nửa đêm (ví dụ: 22h đêm đến 8h sáng hôm sau)
                return $timeStr >= $start || $timeStr <= $end;
            }
        });

        $this->resolvedTimeSlots[$timeStr] = $slot;
        return $slot;
    }

    private function resolveDayType(Carbon $dt, int $dow, string $ddmm, array $extraHolidays): string
    {
        $cacheKey = $ddmm . '_' . $dow . '_' . serialize($extraHolidays);
        if (array_key_exists($cacheKey, $this->resolvedDayTypes)) {
            return $this->resolvedDayTypes[$cacheKey];
        }

        // 1. Kiểm tra ngày lễ động từ database hoặc danh sách ngoại lệ
        $isHoliday = in_array($ddmm, $extraHolidays, true) || 
            Holiday::where('date', $ddmm)
                ->where(fn($q) => $q->whereNull('year')->orWhere('year', $dt->year))
                ->exists();

        if ($isHoliday) {
            $this->resolvedDayTypes[$cacheKey] = 'holiday';
            return 'holiday';
        }

        // 2. Tra cứu quy tắc ngày theo Thứ trong tuần từ database
        $dayRule = DayRule::where('day_of_week', $dow)->first();
        if ($dayRule) {
            $this->resolvedDayTypes[$cacheKey] = $dayRule->day_type;
            return $dayRule->day_type;
        }

        $this->resolvedDayTypes[$cacheKey] = 'standard';
        return 'standard';
    }
}
