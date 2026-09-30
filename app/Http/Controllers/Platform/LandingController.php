<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class LandingController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect('/');
    }
}
