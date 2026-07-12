<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Format;
use App\Models\SeatType;
use Illuminate\Http\Request;

class PricePageController extends Controller
{
    public function index()
    {
        return view('users.prices.index');
    }
}
