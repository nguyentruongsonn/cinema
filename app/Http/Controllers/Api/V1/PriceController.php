<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Format;
use App\Models\SeatType;
use App\Models\Theater;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class PriceController extends Controller
{
    use ApiResponse;

    public function index()
    {
        try {
            // 1. Fetch active theaters, formats, and seat types
            $theaters = Theater::active()->get();
            $formats = Format::all();
            $seatTypes = SeatType::all();

            // 2. Generate pricing matrix for each theater
            $theatersData = [];
            
            foreach ($theaters as $theater) {
                $tp = $theater->pricing_profile ?? [
                    'base_price' => 70000,
                    'weekend_surcharge' => 10000,
                    'holiday_surcharge' => 20000,
                    'happy_day_price' => 50000,
                    'student_discount' => 10000,
                    'beta_ten_discount' => -10000,
                ];

                $base = intval($tp['base_price'] ?? 70000);
                $weekend = $base + intval($tp['weekend_surcharge'] ?? 10000);
                $holiday = $base + intval($tp['holiday_surcharge'] ?? 20000);
                
                $u22Discount = intval($tp['student_discount'] ?? 10000);
                $childDiscount = intval($tp['child_discount'] ?? 15000);

                $formatTables = [];
                foreach ($formats as $format) {
                    $fSur = floatval($format->surcharge ?? 0);
                    
                    $standardSurcharge = 0;
                    foreach ($seatTypes as $st) {
                        if (strtolower($st->name) === 'standard' || strtolower($st->name) === 'thường') {
                            $standardSurcharge = floatval($st->surcharge ?? 0);
                            break;
                        }
                    }
                    
                    $fBase = $base + $fSur + $standardSurcharge;
                    $fWeekend = $weekend + $fSur + $standardSurcharge;
                    $fHoliday = $holiday + $fSur + $standardSurcharge;

                    $weekdayRow = [
                        'title' => 'Thứ 2, 4, 5',
                        'adult' => $fBase,
                        'u22' => max(0, $fBase - $u22Discount),
                        'child' => max(0, $fBase - $childDiscount),
                    ];
                    $weekendRow = [
                        'title' => 'Thứ 6, 7, CN',
                        'adult' => $fWeekend,
                        'u22' => max(0, $fWeekend - $u22Discount),
                        'child' => max(0, $fWeekend - $childDiscount),
                    ];
                    $holidayRow = [
                        'title' => 'Ngày Lễ',
                        'adult' => $fHoliday,
                        'u22' => max(0, $fHoliday - $u22Discount),
                        'child' => max(0, $fHoliday - $childDiscount),
                    ];
                    
                    $seatNotes = [];
                    foreach ($seatTypes as $seatType) {
                        $sSur = floatval($seatType->surcharge ?? 0);
                        if (strtolower($seatType->name) === 'standard' || strtolower($seatType->name) === 'thường') {
                            continue;
                        }
                        if ($sSur > 0) {
                            $seatNotes[] = "Phụ thu ghế {$seatType->name}: +" . number_format($sSur, 0, ',', '.') . "đ";
                        }
                    }

                    $formatTables[] = [
                        'format' => [
                            'id' => $format->id,
                            'name' => $format->name,
                        ],
                        'rows' => [$weekdayRow, $weekendRow, $holidayRow],
                        'seat_notes' => implode(' | ', $seatNotes),
                    ];
                }

                $theatersData[] = [
                    'theater' => [
                        'id' => $theater->id,
                        'name' => $theater->name,
                    ],
                    'format_tables' => $formatTables,
                ];
            }

            return $this->successResponse([
                'theaters' => $theatersData
            ], 'Pricing data retrieved successfully');
        } catch (\Exception $e) {
            report($e);

            return $this->errorResponse('Failed to retrieve pricing data', 500);
        }
    }
}
