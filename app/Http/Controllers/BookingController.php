<?php

namespace App\Http\Controllers;

use App\Models\Showtime;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Display the booking page for a specific showtime.
     */
    public function show(Request $request, string $encryptedShowtimeId)
    {
        try {
            $showtimeId = (int) Crypt::decryptString($encryptedShowtimeId);
        } catch (DecryptException) {
            abort(404, 'Invalid showtime identifier.');
        }
        $showtime = Showtime::with([
            'movie',
            'screen.theater',
            'format',
            'versionType',
        ])->findOrFail($showtimeId);

        // Validate showtime is bookable
        if ($showtime->status != 1) {
            abort(403, 'Suất chiếu này không khả dụng.');
        }

        $now = Carbon::now();

        if ($showtime->scheduled_at <= $now) {
            abort(403, 'Suất chiếu này đã bắt đầu hoặc kết thúc. Không thể đặt vé.');
        }

        return view('users.booking.index', [
            'showtime' => $showtime,
        ]);
    }
}
