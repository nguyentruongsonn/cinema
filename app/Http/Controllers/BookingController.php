<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ViewBookingRequest;
use App\Services\ShowtimeService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class BookingController extends Controller
{
    public function __construct(
        private readonly ShowtimeService $showtimeService
    ) {
    }

    /**
     * Display the booking page for a specific showtime.
     */
    public function show(ViewBookingRequest $request, string $encryptedShowtimeId): View
    {
        try {
            $showtimeId = (int) Crypt::decryptString($encryptedShowtimeId);
        } catch (DecryptException $e) {
            Log::warning('Invalid encrypted showtime identifier used for booking page', [
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
                'identifier_length' => strlen($encryptedShowtimeId),
                'error' => $e->getMessage(),
            ]);

            abort(404, 'Invalid booking link.');
        }

        try {
            $showtime = $this->showtimeService->getBookableShowtimeForBookingPage($showtimeId);

            Log::info('Booking page accessed', [
                'showtime_id' => $showtime->id,
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
            ]);

            return view('users.booking.index', [
                'showtime' => $showtime,
            ]);
        } catch (ModelNotFoundException) {
            Log::notice('Booking page requested for missing showtime', [
                'showtime_id' => $showtimeId,
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
            ]);

            abort(404, 'Showtime not found.');
        } catch (HttpException $e) {
            Log::notice('Booking page requested for unavailable showtime', [
                'showtime_id' => $showtimeId,
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
                'status_code' => $e->getStatusCode(),
            ]);

            abort($e->getStatusCode(), 'This showtime is not available for booking.');
        } catch (Throwable $e) {
            Log::error('Booking page failed unexpectedly', [
                'showtime_id' => $showtimeId,
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            abort(500, 'Unable to load booking page.');
        }
    }
}
