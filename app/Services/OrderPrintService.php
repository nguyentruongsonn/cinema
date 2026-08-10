<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Combo;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPrintLog;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

class OrderPrintService
{
    private const RELATIONS = [
        'user:id,name,email,phone',
        'servedBy:id,name',
        'showtime:id,scheduled_at,screen_id,movie_id,format_id',
        'showtime.movie:id,title,duration,age_rating',
        'showtime.format:id,name',
        'showtime.screen:id,name,theater_id',
        'showtime.screen.theater:id,name,address,phone',
        'theater:id,name,address,phone',
        'tickets:id,order_id,ticket_code,seat_id,status,checked_in_at',
        'tickets.seat:id,row,number,label,seat_type_id',
        'tickets.seat.seatType:id,name',
        'orderItems:id,order_id,item_type,item_id,quantity,unit_price,total_price,metadata,fulfillment_status',
        'payment:id,order_id,method,status,amount,paid_at',
        'printLogs:id,order_id,print_type,copy_number,is_reprint,printed_at',
    ];

    public function findByIdentifier(string $rawIdentifier): Order
    {
        $identifier = $this->normalizeIdentifier($rawIdentifier);

        return Order::query()
            ->where('code', $identifier)
            ->orWhereHas('tickets', fn ($query) => $query->where('ticket_code', $identifier))
            ->firstOrFail();
    }

    public function prepare(Order $order, User $actor): Order
    {
        $order->loadMissing(self::RELATIONS);
        $this->authorize($actor, $order);

        abort_unless($order->isPaid(), 422, 'Đơn hàng chưa thanh toán nên chưa thể in.');

        return $order;
    }

    public function summary(Order $order): array
    {
        $tickets = $this->tickets($order);
        $concessions = $this->concessions($order);
        $showtime = $order->showtime_id !== null ? $order->showtime : null;
        $theater = $showtime !== null
            ? $showtime->screen->theater
            : ($order->theater_id !== null ? $order->theater : null);
        $customer = $order->user;

        return [
            'id' => $order->id,
            'code' => $order->code,
            'source' => $order->source,
            'source_label' => $order->isPos() ? 'Đặt tại quầy POS' : 'Đặt trực tuyến',
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method ?? data_get($order->payload, 'payment_method'),
            'payment_method_label' => $this->paymentMethodLabel(
                (string) ($order->payment_method ?? data_get($order->payload, 'payment_method'))
            ),
            'total_amount' => (float) $order->total_amount,
            'customer' => [
                'name' => $customer->name ?? 'Khách vãng lai',
                'phone' => $customer->phone,
                'email' => $customer->email,
            ],
            'movie' => $showtime !== null ? [
                'title' => $showtime->movie->title,
                'duration' => $showtime->movie->duration,
                'age_rating' => $showtime->movie->age_rating,
                'format' => data_get($showtime->format, 'name'),
            ] : null,
            'showtime' => $showtime?->scheduled_at->toISOString(),
            'screen' => $showtime?->screen->name,
            'theater' => $theater ? [
                'id' => $theater->id,
                'name' => $theater->name,
                'address' => $theater->address,
                'phone' => $theater->phone,
            ] : null,
            'tickets' => $tickets->values()->all(),
            'concessions' => $concessions->values()->all(),
            'available_sections' => array_values(array_filter([
                'invoice',
                $tickets->isNotEmpty() ? 'tickets' : null,
                $concessions->isNotEmpty() ? 'concessions' : null,
            ])),
            'print_count' => $order->printLogs->count(),
            'last_printed_at' => $order->printLogs->max('printed_at')?->toISOString(),
        ];
    }

    public function printData(Order $order): array
    {
        $order->loadMissing(self::RELATIONS);

        $summary = $this->summary($order);
        $payload = (array) $order->payload;
        $subtotal = (float) ($payload['subtotal'] ?? $order->total_amount);
        $discount = (float) ($payload['discount_amount'] ?? 0);
        $voucherDiscount = (float) ($payload['voucher_discount'] ?? 0);
        $pointDiscount = (float) ($payload['point_discount'] ?? 0);

        if ($discount <= 0) {
            $discount = $voucherDiscount + $pointDiscount;
        }

        return array_merge($summary, [
            'created_at' => $order->created_at,
            'paid_at' => $order->paid_at,
            'served_by' => $order->servedBy?->name,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'voucher_discount' => $voucherDiscount,
            'point_discount' => $pointDiscount,
            'other_discount' => max(0, $discount - $voucherDiscount - $pointDiscount),
            'points_used' => (int) ($payload['points_used'] ?? 0),
            'vat_amount' => (float) $order->total_amount > 0
                ? (float) $order->total_amount - ((float) $order->total_amount / 1.05)
                : 0.0,
        ]);
    }

    public function recordPrintRequest(Order $order, User $actor, array $sections, ?string $reason = null): OrderPrintLog
    {
        $previousCount = $order->printLogs()->count();

        return OrderPrintLog::create([
            'order_id' => $order->id,
            'printed_by_user_id' => $actor->id,
            'print_type' => implode(',', $sections),
            'status' => 'requested',
            'copy_number' => $previousCount + 1,
            'is_reprint' => $previousCount > 0,
            'reason' => $previousCount > 0 ? ($reason ?: 'In lại tại quầy') : null,
            'metadata' => [
                'source' => $order->source,
                'theater_id' => $order->posTheaterId(),
            ],
            'printed_at' => now(),
        ]);
    }

    private function authorize(User $actor, Order $order): void
    {
        abort_unless($actor->hasPermission('tickets.issue'), 403, 'Bạn không có quyền in vé.');

        if (! $actor->requiresTheaterScope()) {
            return;
        }

        $theaterId = $order->posTheaterId();
        abort_unless(
            $theaterId !== null && $actor->isAssignedToTheater($theaterId),
            403,
            'Đơn hàng không thuộc rạp bạn phụ trách.'
        );
    }

    private function tickets(Order $order): Collection
    {
        $ticketItems = $order->orderItems
            ->where('item_type', Ticket::class)
            ->keyBy('item_id');

        return $order->tickets
            ->unique('id')
            ->map(function (Ticket $ticket) use ($ticketItems): array {
                $item = $ticketItems->get($ticket->id);
                $metadata = $item instanceof OrderItem ? (array) ($item->metadata ?? []) : [];

                return [
                    'id' => $ticket->id,
                    'ticket_code' => $ticket->ticket_code,
                    'status' => $ticket->status,
                    'seat_label' => $ticket->seat->label ?? $metadata['seat_label'] ?? '—',
                    'seat_type' => $ticket->seat->seatType->name ?? $metadata['seat_type'] ?? 'Thường',
                    'audience_type' => $metadata['audience_type'] ?? 'adult',
                    'price' => $item instanceof OrderItem ? (float) $item->unit_price : 0.0,
                ];
            })
            ->sortBy('seat_label')
            ->values();
    }

    private function concessions(Order $order): Collection
    {
        return $order->orderItems
            ->filter(fn (OrderItem $item): bool => in_array($item->item_type, [Product::class, Combo::class], true))
            ->map(function (OrderItem $item): array {
                $metadata = (array) ($item->metadata ?? []);

                return [
                    'id' => $item->id,
                    'name' => $metadata['combo_name'] ?? $metadata['product_name'] ?? $metadata['name'] ?? 'Sản phẩm',
                    'type' => $item->item_type === Combo::class ? 'combo' : 'product',
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total_price' => (float) $item->total_price,
                    'items' => $metadata['items'] ?? [],
                    'fulfillment_status' => $item->fulfillment_status,
                ];
            })
            ->values();
    }

    private function normalizeIdentifier(string $rawIdentifier): string
    {
        $value = trim($rawIdentifier);
        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            $value = (string) ($decoded['booking_id'] ?? $decoded['ticket_code'] ?? $value);
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            $path = trim((string) parse_url($value, PHP_URL_PATH), '/');
            $value = urldecode((string) basename($path));
        }

        return strtoupper(trim($value));
    }

    private function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Tiền mặt',
            'payos', 'payos_qr', 'qr_online' => 'QR PayOS',
            'zero_amount' => 'Đơn hàng 0đ',
            default => 'Đã thanh toán',
        };
    }
}
