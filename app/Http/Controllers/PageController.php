<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function terms(): Response
    {
        return Inertia::render('legal/Terms');
    }

    public function privacy(): Response
    {
        return Inertia::render('legal/Privacy');
    }

    public function faq(): Response
    {
        return Inertia::render('legal/Faq');
    }
}
