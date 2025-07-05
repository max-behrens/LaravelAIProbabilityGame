<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ErrorController extends Controller
{
    public function unauthorized()
    {
        return Inertia::render('Auth/Unauthorized');
    }
}