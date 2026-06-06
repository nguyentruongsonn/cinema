<?php

namespace App\Http\Controllers;

use App\Models\Showtime;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class BookingController extends Controller
{
    /**
     * Display the booking page for a specific showtime.
     */
    public function show($encryptedId)
    {
        try {
            $showtimeId = Crypt::decryptString($encryptedId);
        } catch (DecryptException $e) {
            abort(404, 'Suất chiếu không hợp lệ');
        }

        $showtime = Showtime::with([
            'movie',
            'screen.theater',
            'screen.format',
            'screen.sound',
        ])->findOrFail($showtimeId);

        return view('users.booking.index', [
            'showtime' => $showtime,
        ]);
    }
}
