<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Welcome', [
            'chatMessages' => $request->session()->get('consultant_chat', []),
            'consultantRequestReference' => $request->session()->get('consultant_request_reference'),
        ]);
    }
}
