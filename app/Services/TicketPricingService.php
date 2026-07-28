<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/**
 * TicketPricingService
 *
 * Tính giá vé theo bảng giá chuẩn CGV-style.
 * Thứ tự ưu tiên rule:
 *   beta_ten > holiday > happy_day > weekday/weekend > standard
 */
class TicketPricingService
{
    // ─── Constants ──────────────────────────────────────────────────────

    // Ngày lễ Việt Nam (MM-DD)
    private const HOLIDAYS = [
        '01-01', // Tết Dương lịch
        '04-30', // Giải phóng miền Nam
        '05-01', // Quốc tế Lao động
        '09-02', // Quốc khánh
        // Tết Nguyên Đán phải tính riêng (âm lịch) — thêm theo năm
    ];

    // ─── Công khai ──────────────────────────────────────────────────────

    /**
     * Tính giá vé.
     *
     * @param  string  $format          '2D' | '3D' | '4DX' | 'IMAX' …
     * @param  Carbon  $scheduledAt     Thời điểm bắt đầu suất chiếu
     * @param  string  $customerType    'adult' | 'student' | 'child' | 'senior'
     * @param  bool    $isDoubleSeat    Ghế đôi (couple seat)?
     * @param  int     $movieSurcharge  Phụ thu phim (VNĐ, từ bảng movies.surcharge)
     * @param  array   $extraHolidays   Danh sách ngày lễ bổ sung dạng ['MM-DD', ...]
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

        $defaultPricing = [
            'base_price' => 70000,
            'weekend_surcharge' => 10000,
            'holiday_surcharge' => 20000,
            'happy_day_price' => 50000,
            'student_discount' => 10000,
            'beta_ten_discount' => -10000,
        ];
        $tp = $theaterPricing ?? $defaultPricing;

        $hour     = (int) $scheduledAt->format('H');
        $minute   = (int) $scheduledAt->format('i');
        $dayOfWeek = $scheduledAt->dayOfWeek; // 0=Sun … 6=Sat
        $mmdd      = $scheduledAt->format('m-d');

        // Chuẩn hóa customer type
        $isReduced = in_array($customerType, ['student', 'child', 'senior'], true);
        $priceGroup = $isReduced ? 'student_child_senior' : 'adult';

        // ── Bước 2: Xác định time slot bình thường ──────────────────────
        $timeSlot = $this->resolveTimeSlot($hour);

        // ── Bước 3: Xác định ngày đặc biệt ──────────────────────────────
        $dayType  = $this->resolveDayType($scheduledAt, $dayOfWeek, $mmdd, $extraHolidays);

        // ── Bước 4: Tính giá gốc và Phụ thu ─────────────────────────────
        $surcharges = [];

        if ($dayType === 'happy_day') {
            $basePrice = (int) ($tp['happy_day_price'] ?? 50000);
        } else {
            $basePrice = (int) ($tp['base_price'] ?? 70000);

            if ($dayType === 'holiday') {
                $surcharges['holiday'] = [
                    'label'  => 'Phụ thu ngày lễ',
                    'amount' => (int) ($tp['holiday_surcharge'] ?? 20000),
                ];
            } elseif ($dayType === 'weekend') {
                $surcharges['weekend'] = [
                    'label'  => 'Phụ thu cuối tuần',
                    'amount' => (int) ($tp['weekend_surcharge'] ?? 10000),
                ];
            }
        }

        // Ưu đãi đối tượng khách hàng
        if ($isReduced && $dayType !== 'happy_day') {
            $studentDiscount = (int) ($tp['student_discount'] ?? 10000);
            if ($studentDiscount > 0) {
                $surcharges['student_discount'] = [
                    'label'  => 'Ưu đãi đối tượng',
                    'amount' => -$studentDiscount,
                ];
            }
        }

        // Phụ thu ghế đôi
        if ($isDoubleSeat) {
            $surcharges['double_seat'] = [
                'label'  => 'Phụ thu ghế đôi',
                'amount' => 5000,
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
     * Xác định time slot: '10:00-18:00' hoặc '18:00-22:00'
     */
    private function resolveTimeSlot(int $hour): string
    {
        return ($hour >= 10 && $hour < 18) ? '10:00-18:00' : '18:00-22:00';
    }

    /**
     * Xác định loại ngày (theo thứ tự ưu tiên).
     * holiday > happy_day > weekday/weekend > standard
     * (beta_ten được xử lý riêng, mad_sale_day chỉ thêm surcharge)
     */
    private function resolveDayType(Carbon $dt, int $dow, string $mmdd, array $extraHolidays): string
    {
        // Holiday check (MM-DD)
        $holidays = array_merge(self::HOLIDAYS, $extraHolidays);
        if (in_array($mmdd, $holidays, true)) {
            return 'holiday';
        }

        // Happy Day: Thứ 3 (Carbon: 2 = Tuesday)
        if ($dow === Carbon::TUESDAY) {
            return 'happy_day';
        }

        // Weekend: T6, T7, CN
        if (in_array($dow, [Carbon::FRIDAY, Carbon::SATURDAY, Carbon::SUNDAY], true)) {
            return 'weekend';
        }

        // Mad Sale Day: Thứ 2 đầu tiên của tháng
        if ($dow === Carbon::MONDAY && $dt->day <= 7) {
            return 'mad_sale_day';
        }

        // Weekday: T2, T4, T5
        if (in_array($dow, [Carbon::MONDAY, Carbon::WEDNESDAY, Carbon::THURSDAY], true)) {
            return 'weekday';
        }

        return 'standard';
    }
}
