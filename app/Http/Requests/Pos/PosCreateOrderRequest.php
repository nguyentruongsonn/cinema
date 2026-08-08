<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class PosCreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasAnyPermission(['orders.create', 'booking.create_order']);
    }

    public function rules(): array
    {
        return [
            'showtime_id' => ['nullable', 'integer', 'exists:showtimes,id'],
            'theater_id' => ['nullable', 'integer', 'exists:theaters,id'],
            'seat_ids' => ['nullable', 'array'],
            'seat_ids.*' => ['integer', 'exists:seats,id'],
            'tickets' => ['nullable', 'array'],
            'tickets.*.seat_id' => ['required', 'integer', 'exists:seats,id'],
            'tickets.*.audience_type' => ['required', 'string', 'in:adult,student,child,senior'],
            'tickets.*.student_card_verified' => ['nullable', 'boolean'],
            'products' => ['nullable', 'array'],
            'products.*.id' => ['required_with:products', 'integer'],
            'products.*.type' => ['required_with:products', 'string', 'in:product,combo'],
            'products.*.quantity' => ['required_with:products', 'integer', 'min:1', 'max:20'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_mode' => ['nullable', 'string', 'in:guest,member'],
            'customer_type' => ['nullable', 'string', 'in:adult,student,child,senior'],
            'payment_method' => ['required', 'string', 'in:cash,payos_qr'],
            'loyalty_points_to_use' => ['nullable', 'integer', 'min:0'],
            'promotion_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'showtime_id.required' => 'Vui lòng chọn suất chiếu.',
            'seat_ids.required' => 'Vui lòng chọn ít nhất 1 ghế.',
            'customer_type.in' => 'Loại khách hàng không hợp lệ.',
            'payment_method.in' => 'Phương thức thanh toán không hợp lệ.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tickets = collect($this->input('tickets', []))
            ->map(fn ($ticket) => [
                'seat_id' => (int) ($ticket['seat_id'] ?? 0),
                'audience_type' => $ticket['audience_type'] ?? 'adult',
                'student_card_verified' => filter_var($ticket['student_card_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])->values()->all();

        if ($tickets === [] && $this->filled('seat_ids')) {
            $tickets = collect((array) $this->input('seat_ids'))
                ->map(fn ($seatId) => [
                    'seat_id' => (int) $seatId,
                    'audience_type' => 'adult',
                    'student_card_verified' => false,
                ])->values()->all();
        }

        $this->merge([
            'theater_id' => $this->input('theater_id') !== null
                ? (int) $this->input('theater_id')
                : null,
            'tickets' => $tickets,
            'customer_mode' => $this->input('customer_mode')
                ?: ($this->filled('customer_phone') ? 'member' : 'guest'),
            'seat_ids' => $this->filled('seat_ids')
                ? array_map('intval', (array) $this->input('seat_ids'))
                : array_column($tickets, 'seat_id'),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $seatIds = collect($this->input('seat_ids', []))->map(fn ($id) => (int) $id)->sort()->values()->all();
            $ticketIds = collect($this->input('tickets', []))->pluck('seat_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($seatIds !== $ticketIds && ! empty($seatIds)) {
                $validator->errors()->add('tickets', 'Danh sách vé phải khớp với danh sách ghế.');
            }

            foreach ((array) $this->input('tickets', []) as $index => $ticket) {
                if (($ticket['audience_type'] ?? null) === 'student' && empty($ticket['student_card_verified'])) {
                    $validator->errors()->add("tickets.{$index}.student_card_verified", 'Nhân viên phải xác nhận đã xem thẻ sinh viên.');
                }
            }

            if ($this->input('customer_mode') === 'guest' && (int) $this->input('loyalty_points_to_use', 0) > 0) {
                $validator->errors()->add('loyalty_points_to_use', 'Khách vãng lai không sử dụng được điểm tích lũy.');
            }

            if (! $this->filled('showtime_id') && ! $this->filled('theater_id')) {
                $validator->errors()->add('theater_id', 'Vui lòng chọn rạp cho đơn không có suất chiếu.');
            }

            if (count((array) $this->input('seat_ids', [])) > 0 && ! $this->filled('showtime_id')) {
                $validator->errors()->add('showtime_id', 'Vui lòng chọn suất chiếu khi bán vé có ghế.');
            }
        });
    }
}
