<?php

namespace App\Http\Controllers;

use App\Models\Showtime;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

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
            'subtitle',
        ])->findOrFail($showtimeId);

        return view('users.booking.index', [
            'showtime' => $showtime,
        ]);
    }
}
