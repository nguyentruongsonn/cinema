<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile page.
     *
     * Note: This endpoint requires authentication middleware at the route level.
     */
    public function index(): View
    {
        return view('users.profile.index', [
            'user' => Auth::user(),
        ]);
    }
}
