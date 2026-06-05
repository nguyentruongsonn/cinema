<?php

namespace App\Http\Controllers;

use App\Http\Requests\LockSeatRequest;
use App\Services\SeatService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

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
    public function getByShowtime($showtimeId)
    {
        try {
            $user = Auth::user();
            $data = $this->seatService->getByShowtime((int) $showtimeId, $user);

            return $this->successResponse($data, 'Seats retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve seats: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lock seats temporarily for booking.
     */
    public function lock(LockSeatRequest $request)
    {
        try {
            $user = Auth::user();
            $data = $this->seatService->lock($request->validated(), $user);

            return $this->successResponse($data, 'Seats locked successfully', 200);
        } catch (\RuntimeException $e) {
            $statusCode = in_array($e->getCode(), [403, 404, 422], true) ? $e->getCode() : 422;
            return $this->errorResponse($e->getMessage(), $statusCode);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to lock seats: ' . $e->getMessage(), 422);
        }
    }

    /**
     * Unlock held seats.
     */
    public function unlock($holdId)
    {
        try {
            $user = Auth::user();
            $data = $this->seatService->unlock((int) $holdId, $user);

            return $this->successResponse($data, 'Seats unlocked successfully');
        } catch (\RuntimeException $e) {
            $statusCode = in_array($e->getCode(), [403, 404], true) ? $e->getCode() : 500;
            return $this->errorResponse($e->getMessage(), $statusCode);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to unlock seats: ' . $e->getMessage(), 500);
        }
    }
}
