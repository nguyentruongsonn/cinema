<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class TicketController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user instanceof User) {
                return $this->unauthorized('Người dùng không được xác thực.');
            }

            $perPage = min((int) $request->input('per_page', 15), 50);
            $status = $request->input('status', 'all');

            $query = Ticket::query()
                ->where('user_id', $user->id)
                ->with([
                    'order:id,code,total_amount,created_at',
                    'showtime:id,scheduled_at,screen_id,movie_id',
                    'showtime.movie:id,title,poster_url,poster_path,duration,age_rating',
                    'showtime.screen:id,name,theater_id',
                    'showtime.screen.theater:id,name,address,branch_id',
                    'showtime.screen.theater.branch:id,name',
                    'seat:id,row,number,label,seat_type_id',
                    'seat.seatType:id,name,surcharge',
                ]);

            if ($status !== 'all') {
                $validStatuses = ['valid', 'used', 'cancelled', 'refunded'];

                if (!in_array($status, $validStatuses, true)) {
                    return $this->error(
                        'Trạng thái không hợp lệ. Giá trị: ' . implode(', ', $validStatuses),
                        422
                    );
                }

                $query->where('status', $status);
            }

            $tickets = $query->latest('created_at')->paginate($perPage);

            return $this->ok([
                'data' => $tickets->items(),
                'meta' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                    'from' => $tickets->firstItem(),
                    'to' => $tickets->lastItem(),
                ],
            ], 'Tải danh sách vé thành công.');
        } catch (Throwable $e) {
            report($e);

            return $this->error('Đã xảy ra lỗi khi tải danh sách vé.', 500);
        }
    }

    public function show(Request $request, string $ticketCode): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user instanceof User) {
                return $this->unauthorized('Người dùng không được xác thực.');
            }

            $ticket = Ticket::query()
                ->where('ticket_code', $ticketCode)
                ->where('user_id', $user->id)
                ->with([
                    'order:id,code,total_amount,created_at',
                    'showtime:id,scheduled_at,screen_id,movie_id',
                    'showtime.movie:id,title,poster_url,poster_path,duration,age_rating',
                    'showtime.screen:id,name,theater_id',
                    'showtime.screen.theater:id,name,address,branch_id,phone',
                    'showtime.screen.theater.branch:id,name',
                    'seat:id,row,number,label,seat_type_id',
                    'seat.seatType:id,name,surcharge',
                ])
                ->first();

            if (!$ticket) {
                return $this->error('Vé không tìm thấy hoặc bạn không có quyền xem vé này.', 404);
            }

            return $this->ok($ticket, 'Tải thông tin vé thành công.');
        } catch (Throwable $e) {
            report($e);

            return $this->error('Đã xảy ra lỗi khi tải thông tin vé.', 500);
        }
    }

    public function verify(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ticket_code' => 'required|string',
            ]);

            $ticketCode = $this->extractTicketCode($request->input('ticket_code'));

            if ($ticketCode === '') {
                return $this->error('Vui l?ng nh?p m? v?.', 422);
            }

            $ticket = Ticket::query()
                ->where('ticket_code', $ticketCode)
                ->with([
                    'order:id,code,total_amount,created_at',
                    'showtime:id,scheduled_at,screen_id,movie_id',
                    'showtime.movie:id,title,poster_url,poster_path',
                    'showtime.screen:id,name,theater_id',
                    'showtime.screen.theater:id,name,branch_id',
                    'showtime.screen.theater.branch:id,name',
                    'seat:id,row,number,label',
                ])
                ->first();

            if (! $ticket) {
                return $this->error('M? v? kh?ng t?n t?i.', 404);
            }

            if ($ticket->status === 'used') {
                $checkedInAt = $ticket->checked_in_at?->format('d/m/Y H:i') ?? 'kh?ng x?c ??nh';

                return $this->error('V? ?? ???c s? d?ng tr??c ?? v?o l?c ' . $checkedInAt, 409);
            }

            if ($ticket->status === 'cancelled') {
                return $this->error('V? ?? b? h?y.', 400);
            }

            if ($ticket->status === 'refunded') {
                return $this->error('V? ?? ???c ho?n ti?n.', 400);
            }

            if ($ticket->showtime && $ticket->showtime->scheduled_at < now()) {
                return $this->error('Su?t chi?u ?? qua. V? kh?ng c?n hi?u l?c.', 400);
            }

            if (! $ticket->markAsUsed()) {
                return $this->error('V? ?? ???c x?c th?c b?i y?u c?u kh?c.', 409);
            }

            $ticket->refresh();

            return $this->ok([
                'type' => 'ticket',
                'code' => $ticket->ticket_code,
                'movie' => $ticket->showtime->movie->title ?? 'N/A',
                'showtime' => $ticket->showtime ? $ticket->showtime->scheduled_at->format('d/m/Y H:i') : 'N/A',
                'seat' => $ticket->seat ? $ticket->seat->label : 'N/A',
                'screen' => $ticket->showtime->screen->name ?? 'N/A',
                'theater' => $ticket->showtime->screen->theater->name ?? 'N/A',
                'branch' => $ticket->showtime->screen->theater->branch->name ?? 'N/A',
                'status' => '?? x?c th?c',
                'verified_at' => $ticket->checked_in_at?->format('d/m/Y H:i:s'),
            ], 'V? h?p l?. ?? x?c th?c th?nh c?ng.');
        } catch (ValidationException $e) {
            return $this->error('D? li?u kh?ng h?p l?.', 422, $e->errors());
        } catch (Throwable $e) {
            report($e);

            return $this->error('?? x?y ra l?i khi x?c th?c v?.', 500);
        }
    }

    private function extractTicketCode(?string $raw): string
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return '';
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded) && isset($decoded['ticket_code']) && is_scalar($decoded['ticket_code'])) {
            return trim((string) $decoded['ticket_code']);
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $path = trim((string) parse_url($value, PHP_URL_PATH), '/');
            $segments = array_values(array_filter(explode('/', $path)));
            $lastSegment = end($segments);
            if (is_string($lastSegment) && str_starts_with(strtoupper($lastSegment), 'TKT-')) {
                return trim(urldecode($lastSegment));
            }
        }

        if (preg_match('/\b(TKT-[A-Z0-9_-]+)\b/i', $value, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return $value;
    }

}
