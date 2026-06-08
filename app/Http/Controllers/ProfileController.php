<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class ProfileController extends Controller
{
    public function index(): View
    {
        return view('users.profile.index');
    }

    public function tickets(): View
    {
        return view('users.tickets.index');
    }
}
