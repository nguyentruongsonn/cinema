<?php

namespace App\Http\Controllers;

use App\Models\Showtime;

class BookingController extends Controller
{
    /**
     * Display the booking page for a specific showtime.
     */
    public function show($showtimeId)
    {
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
