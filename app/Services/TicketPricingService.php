<?php

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
    // ─── Bảng giá gốc ───────────────────────────────────────────────────

    private const PRICING = [
        '2D' => [
            'standard' => [
                'adult'                    => ['10:00-18:00' => 50000, '18:00-22:00' => 50000],
                'student_child_senior'     => ['10:00-18:00' => 45000, '18:00-22:00' => 45000],
            ],
            'special_days' => [
                'mad_sale_day' => ['seat_surcharge' => 5000],          // Thứ 2 đầu tháng, tầng 01
                'beta_ten'     => [],                                   // Trước 10h & Sau 22h — giá đặc biệt
                'happy_day'    => [                                     // Thứ 3
                    'adult' => ['10:00-18:00' => 60000, '18:00-22:00' => 70000],
                ],
                'weekday'      => [                                     // T2, T4, T5
                    'adult' => ['10:00-18:00' => 60000, '18:00-22:00' => 70000],
                ],
                'weekend'      => [                                     // T6, T7, CN
                    'adult'                => ['all_day' => 80000],
                    'student_child_senior' => ['all_day' => 55000],
                ],
                'holiday'      => [
                    'adult'                => ['all_day' => 95000],
                ],
            ],
            'policy' => [
                'double_seat_surcharge' => 5000,
            ],
        ],
        '3D' => [
            'standard' => [
                'adult'                    => ['10:00-18:00' => 70000, '18:00-22:00' => 70000],
                'student_child_senior'     => ['10:00-18:00' => 65000, '18:00-22:00' => 65000],
            ],
            'special_days' => [
                'mad_sale_day' => ['seat_surcharge' => 5000],
                'beta_ten'     => [],
                'happy_day'    => [
                    'adult' => ['10:00-18:00' => 80000, '18:00-22:00' => 90000],
                ],
                'weekday'      => [
                    'adult' => ['10:00-18:00' => 80000, '18:00-22:00' => 90000],
                ],
                'weekend'      => [
                    'adult'                => ['all_day' => 100000],
                    'student_child_senior' => ['all_day' => 75000],
                ],
                'holiday'      => [
                    'adult'                => ['all_day' => 115000],
                ],
            ],
            'policy' => [
                'double_seat_surcharge' => 5000,
            ],
        ],
    ];

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
     *   is_beta_ten:     bool,
     * }
     */
    public function calculate(
        string $format,
        Carbon $scheduledAt,
        string $customerType   = 'adult',
        bool   $isDoubleSeat   = false,
        int    $movieSurcharge = 0,
        array  $extraHolidays  = []
    ): array {
        // Normalise format key (2D, 3D; others fall back to 2D)
        $formatKey = in_array($format, ['2D', '3D'], true) ? $format : '2D';

        $pricing  = self::PRICING[$formatKey];
        $hour     = (int) $scheduledAt->format('H');
        $minute   = (int) $scheduledAt->format('i');
        $dayOfWeek = $scheduledAt->dayOfWeek; // 0=Sun … 6=Sat
        $mmdd      = $scheduledAt->format('m-d');

        // Chuẩn hóa customer type
        $isReduced = in_array($customerType, ['student', 'child', 'senior'], true);
        $priceGroup = $isReduced ? 'student_child_senior' : 'adult';

        // ── Bước 1: Xác định beta_ten ───────────────────────────────────
        // Trước 10:00 hoặc sau 22:00
        $timeInMinutes = $hour * 60 + $minute;
        $isBetaTen = ($timeInMinutes < 10 * 60) || ($timeInMinutes >= 22 * 60);

        // ── Bước 2: Xác định time slot bình thường ──────────────────────
        $timeSlot = $this->resolveTimeSlot($hour);

        // ── Bước 3: Xác định ngày đặc biệt (theo thứ tự ưu tiên) ───────
        $dayType  = $this->resolveDayType($scheduledAt, $dayOfWeek, $mmdd, $extraHolidays);

        // ── Bước 4: Tính giá gốc ────────────────────────────────────────
        $basePrice = $this->resolveBasePrice(
            $pricing,
            $dayType,
            $priceGroup,
            $timeSlot,
            $isBetaTen
        );

        // ── Bước 5: Phụ thu ─────────────────────────────────────────────
        $surcharges = [];

        // 5a. Phụ thu ghế đôi
        if ($isDoubleSeat) {
            $doubleSurcharge = $pricing['policy']['double_seat_surcharge'] ?? 0;
            if ($doubleSurcharge > 0) {
                $surcharges['double_seat'] = [
                    'label'  => 'Phụ thu ghế đôi',
                    'amount' => $doubleSurcharge,
                ];
            }
        }

        // 5b. Phụ thu mad_sale_day (chỉ ảnh hưởng ghế — thêm vào surcharge)
        if ($dayType === 'mad_sale_day') {
            $madSurcharge = $pricing['special_days']['mad_sale_day']['seat_surcharge'] ?? 0;
            if ($madSurcharge > 0) {
                $surcharges['mad_sale_day'] = [
                    'label'  => 'Phụ thu Ngày Mad Sale',
                    'amount' => $madSurcharge,
                ];
            }
        }

        // 5c. Phụ thu phim (từ movie.surcharge)
        if ($movieSurcharge > 0) {
            $surcharges['movie'] = [
                'label'  => 'Phụ thu phim',
                'amount' => $movieSurcharge,
            ];
        }

        $totalSurcharge = array_sum(array_column($surcharges, 'amount'));
        $totalPrice     = $basePrice + $totalSurcharge;

        return [
            'base_price'    => $basePrice,
            'surcharges'    => $surcharges,
            'total_price'   => $totalPrice,
            'day_type'      => $dayType,
            'time_slot'     => $isBetaTen ? 'beta_ten' : $timeSlot,
            'customer_type' => $customerType,
            'price_group'   => $priceGroup,
            'format'        => $formatKey,
            'is_beta_ten'   => $isBetaTen,
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
        array  $extraHolidays  = []
    ): array {
        $types = ['adult', 'student', 'child', 'senior'];
        $result = [];
        foreach ($types as $type) {
            $result[$type] = $this->calculate(
                $format, $scheduledAt, $type, $isDoubleSeat, $movieSurcharge, $extraHolidays
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

    /**
     * Lấy giá gốc từ bảng theo dayType + priceGroup + timeSlot.
     */
    private function resolveBasePrice(
        array  $pricing,
        string $dayType,
        string $priceGroup,
        string $timeSlot,
        bool   $isBetaTen
    ): int {
        $special = $pricing['special_days'];
        $standard = $pricing['standard'];

        // beta_ten: dùng giá standard (slot 10:00-18:00 làm giá thấp nhất)
        if ($isBetaTen) {
            return $standard[$priceGroup]['10:00-18:00']
                ?? $standard['adult']['10:00-18:00'];
        }

        switch ($dayType) {
            case 'holiday':
                // Holiday chỉ có adult all_day; reduced không có giá riêng → dùng adult
                $holidayPrices = $special['holiday'] ?? [];
                if (isset($holidayPrices[$priceGroup]['all_day'])) {
                    return $holidayPrices[$priceGroup]['all_day'];
                }
                // Fallback: reduced dùng adult price
                return $holidayPrices['adult']['all_day']
                    ?? $standard[$priceGroup][$timeSlot]
                    ?? $standard['adult'][$timeSlot];

            case 'happy_day':
                $hd = $special['happy_day'] ?? [];
                if (isset($hd[$priceGroup][$timeSlot])) {
                    return $hd[$priceGroup][$timeSlot];
                }
                // Reduced không có giá riêng happy_day → dùng standard reduced
                return $standard[$priceGroup][$timeSlot]
                    ?? $standard['adult'][$timeSlot];

            case 'weekday':
                $wd = $special['weekday'] ?? [];
                if (isset($wd[$priceGroup][$timeSlot])) {
                    return $wd[$priceGroup][$timeSlot];
                }
                return $standard[$priceGroup][$timeSlot]
                    ?? $standard['adult'][$timeSlot];

            case 'weekend':
                $we = $special['weekend'] ?? [];
                if (isset($we[$priceGroup]['all_day'])) {
                    return $we[$priceGroup]['all_day'];
                }
                // Reduced fallback to adult
                return $we['adult']['all_day']
                    ?? $standard[$priceGroup][$timeSlot];

            case 'mad_sale_day':
                // Giá cơ sở vẫn theo weekday (Thứ 2 đầu tháng)
                $wd = $special['weekday'] ?? [];
                if (isset($wd[$priceGroup][$timeSlot])) {
                    return $wd[$priceGroup][$timeSlot];
                }
                return $standard[$priceGroup][$timeSlot]
                    ?? $standard['adult'][$timeSlot];

            default: // standard
                return $standard[$priceGroup][$timeSlot]
                    ?? $standard['adult'][$timeSlot];
        }
    }
}
