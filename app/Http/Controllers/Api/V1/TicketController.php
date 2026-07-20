<?php

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
                    'showtime.movie:id,title,poster_url,duration,age_rating',
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
                    'showtime.movie:id,title,poster_url,duration,age_rating',
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

            $ticket = Ticket::query()
                ->where('ticket_code', $request->input('ticket_code'))
                ->with([
                    'order:id,code,total_amount,created_at',
                    'showtime:id,scheduled_at,screen_id,movie_id',
                    'showtime.movie:id,title,poster_url',
                    'showtime.screen:id,name,theater_id',
                    'showtime.screen.theater:id,name,branch_id',
                    'showtime.screen.theater.branch:id,name',
                    'seat:id,row,number,label',
                ])
                ->first();

            if (!$ticket) {
                return $this->error('Vé không tồn tại.', 404);
            }

            if ($ticket->status === 'used') {
                $checkedInAt = $ticket->checked_in_at?->format('d/m/Y H:i') ?? 'không xác định';

                return $this->error('Vé đã được sử dụng trước đó vào lúc ' . $checkedInAt, 409);
            }

            if ($ticket->status === 'cancelled') {
                return $this->error('Vé đã bị hủy.', 400);
            }

            if ($ticket->status === 'refunded') {
                return $this->error('Vé đã được hoàn tiền.', 400);
            }

            if ($ticket->showtime && $ticket->showtime->scheduled_at < now()) {
                return $this->error('Suất chiếu đã qua. Vé không còn hiệu lực.', 400);
            }

            if (! $ticket->markAsUsed()) {
                return $this->error('Vé đã được xác thực bởi yêu cầu khác.', 409);
            }

            $ticket->refresh();

            return $this->ok([
                'code' => $ticket->ticket_code,
                'movie' => $ticket->showtime->movie->title ?? 'N/A',
                'showtime' => $ticket->showtime ? $ticket->showtime->scheduled_at->format('d/m/Y H:i') : 'N/A',
                'seat' => $ticket->seat ? $ticket->seat->label : 'N/A',
                'screen' => $ticket->showtime->screen->name ?? 'N/A',
                'theater' => $ticket->showtime->screen->theater->name ?? 'N/A',
                'branch' => $ticket->showtime->screen->theater->branch->name ?? 'N/A',
                'status' => 'Đã xác thực',
                'verified_at' => $ticket->checked_in_at?->format('d/m/Y H:i:s'),
            ], 'Vé hợp lệ. Đã xác thực thành công.');
        } catch (ValidationException $e) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $e->errors());
        } catch (Throwable $e) {
            report($e);

            return $this->error('Đã xảy ra lỗi khi xác thực vé.', 500);
        }
    }
}
