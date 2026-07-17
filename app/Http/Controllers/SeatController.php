<?php

namespace App\Http\Controllers;

use App\Exceptions\SeatConflictException;
use App\Http\Requests\GetSeatsRequest;
use App\Http\Requests\LockSeatRequest;
use App\Http\Requests\UnlockSeatsRequest;
use App\Models\SeatHold;
use App\Services\SeatService;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SeatController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SeatService $seatService
    ) {
    }

    /**
     * Get seats by showtime with availability status.
     */
    public function getByShowtime(GetSeatsRequest $request, string $encryptedShowtimeId)
    {
        try {
            $showtimeId = (int) Crypt::decryptString($encryptedShowtimeId);
            $user = Auth::user();

            Log::info('Seat availability retrieval requested', [
                'showtime_id' => $showtimeId,
                'user_id' => $user?->id,
                'ip' => $request->ip(),
            ]);

            $data = $this->seatService->getByShowtime($showtimeId, $user);

            return $this->successResponse($data, __('seats.retrieved'), 200);
        } catch (DecryptException $e) {
            Log::warning('Invalid encrypted showtime id for seat retrieval', [
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse(__('showtimes.not_found'), 404);
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve seats', [
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(__('seats.retrieve_failed'), 500);
        }
    }

    /**
     * Lock seats temporarily for booking.
     */
    public function lock(LockSeatRequest $request)
    {
        $user = Auth::user();

        if ($user === null) {
            return $this->errorResponse(__('auth.unauthenticated'), 401);
        }

        Log::info('Seat lock attempt', [
            'user_id' => $user->id,
            'showtime_id' => $request->validated('showtime_id'),
            'seat_ids' => $request->validated('seat_ids'),
            'ip' => $request->ip(),
        ]);

        try {
            $data = $this->seatService->lock($request->validated(), $user);

            Log::info('Seats locked successfully', [
                'user_id' => $user->id,
                'hold_id' => $data['hold_id'] ?? null,
                'seat_count' => count($data['seat_ids'] ?? []),
            ]);

            return $this->successResponse($data, __('seats.locked'), 201);
        } catch (SeatConflictException $e) {
            Log::warning('Seat lock conflict', [
                'user_id' => $user->id,
                'showtime_id' => $request->validated('showtime_id'),
                'conflicted_seats' => $e->conflictedSeats(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('seats.unavailable'),
                'data' => [
                    'conflicted_seats' => $e->conflictedSeats(),
                ],
            ], 409);
        } catch (\RuntimeException $e) {
            $statusCode = in_array($e->getCode(), [403, 404, 409, 422], true) ? $e->getCode() : 422;

            Log::warning('Seat lock rejected', [
                'user_id' => $user->id,
                'showtime_id' => $request->validated('showtime_id'),
                'status_code' => $statusCode,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(__('seats.lock_failed'), $statusCode);
        } catch (\Throwable $e) {
            Log::error('Seat lock failed', [
                'user_id' => $user->id,
                'showtime_id' => $request->validated('showtime_id'),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(__('seats.lock_failed'), 500);
        }
    }

    /**
     * Unlock held seats.
     */
    public function unlock(UnlockSeatsRequest $request, $holdId)
    {
        $user = Auth::user();

        if ($user === null) {
            return $this->errorResponse(__('auth.unauthenticated'), 401);
        }

        Log::info('Seat unlock attempt', [
            'user_id' => $user->id,
            'hold_id' => (int) $holdId,
            'ip' => $request->ip(),
        ]);

        try {
            $hold = SeatHold::query()
                ->whereKey((int) $holdId)
                ->where('user_id', $user->id)
                ->first();

            if ($hold === null) {
                return $this->successResponse(['unlocked_count' => 0], __('seats.unlocked'), 200);
            }

            if (! $hold->isValid()) {
                return $this->errorResponse(__('seats.hold_expired'), 422);
            }

            $data = $this->seatService->unlock((int) $holdId, $user);

            Log::info('Seats unlocked successfully', [
                'user_id' => $user->id,
                'hold_id' => (int) $holdId,
                'unlocked_count' => $data['unlocked_count'] ?? null,
            ]);

            return $this->successResponse($data, __('seats.unlocked'), 200);
        } catch (\RuntimeException $e) {
            $statusCode = in_array($e->getCode(), [403, 404, 422], true) ? $e->getCode() : 500;

            Log::warning('Seat unlock rejected', [
                'user_id' => $user->id,
                'hold_id' => (int) $holdId,
                'status_code' => $statusCode,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(__('seats.unlock_failed'), $statusCode);
        } catch (\Throwable $e) {
            Log::error('Seat unlock failed', [
                'user_id' => $user->id,
                'hold_id' => (int) $holdId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(__('seats.unlock_failed'), 500);
        }
    }
}
