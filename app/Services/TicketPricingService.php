<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use App\Models\Holiday;
use App\Models\DayRule;
use App\Models\TimeSlot;

/**
 * TicketPricingService
 *
 * Tính giá vé động dựa vào cấu hình trong Database.
 * Các bảng cấu hình:
 *   - holidays: Quản lý ngày lễ và phụ thu ngày lễ đặc biệt.
 *   - day_rules: Quản lý tính chất ngày (weekday, weekend, happy_day) cho các Thứ trong tuần.
 *   - time_slots: Quản lý khung giờ chiếu (sáng, tối, khuya) và phụ thu theo khung giờ.
 */
class TicketPricingService
{
    /**
     * Tính giá vé.
     *
     * @param  string  $format          '2D' | '3D' | '4DX' | 'IMAX' …
     * @param  Carbon  $scheduledAt     Thời điểm bắt đầu suất chiếu
     * @param  string  $customerType    'adult' | 'student' | 'child' | 'senior'
     * @param  bool    $isDoubleSeat    Ghế đôi (couple seat)?
     * @param  int     $movieSurcharge  Phụ thu phim (VNĐ, từ bảng movies.surcharge)
     * @param  array   $extraHolidays   Danh sách ngày lễ bổ sung dạng ['DD-MM', ...]
     *
     * @return array {
     *   base_price:      int,    -- Giá gốc trước phụ thu
     *   surcharges:      array,  -- Chi tiết từng khoản phụ thu
     *   total_price:     int,    -- Tổng giá cuối
     *   day_type:        string, -- Rule áp dụng
     *   time_slot:       string, -- Khung giờ
     *   customer_type:   string,
     * }
     */
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
        // Normalise format key (2D, 3D; others fall back to 2D)
        $formatKey = in_array($format, ['2D', '3D'], true) ? $format : '2D';

        $tp = $theaterPricing ?? [];

        $dayOfWeek = $scheduledAt->dayOfWeek; // 0=Sun … 6=Sat
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

    /**
     * Tính giá cho nhiều loại khách cùng lúc (trả bảng giá đầy đủ).
     */
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

    // ─── Private helpers ────────────────────────────────────────────────

    /**
     * Tra cứu khung giờ chiếu từ Database dựa trên giờ bắt đầu suất chiếu.
     */
    private function resolveTimeSlotFromDb(Carbon $scheduledAt): ?TimeSlot
    {
        $timeStr = $scheduledAt->format('H:i:s');
        
        return TimeSlot::all()->first(function ($slot) use ($timeStr) {
            $start = $slot->start_time;
            $end = $slot->end_time;
            
            if ($start <= $end) {
                return $timeStr >= $start && $timeStr <= $end;
            } else {
                // Khung giờ cắt qua nửa đêm (ví dụ: 22h đêm đến 8h sáng hôm sau)
                return $timeStr >= $start || $timeStr <= $end;
            }
        });
    }

    /**
     * Xác định loại ngày từ Database.
     * Thứ tự kiểm tra: holiday > day_rules (happy_day/weekend/weekday) > standard
     */
    private function resolveDayType(Carbon $dt, int $dow, string $ddmm, array $extraHolidays): string
    {
        // 1. Kiểm tra ngày lễ động từ database hoặc danh sách ngoại lệ
        $isHoliday = in_array($ddmm, $extraHolidays, true) || 
            Holiday::where('date', $ddmm)
                ->where(fn($q) => $q->whereNull('year')->orWhere('year', $dt->year))
                ->exists();

        if ($isHoliday) {
            return 'holiday';
        }

        // 2. Tra cứu quy tắc ngày theo Thứ trong tuần từ database
        $dayRule = DayRule::where('day_of_week', $dow)->first();
        if ($dayRule) {
            return $dayRule->day_type;
        }

        return 'standard';
    }
}
