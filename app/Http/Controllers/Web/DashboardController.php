<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $bases = $request->user()
            ->bases()
            ->withCount('tables')
            ->latest()
            ->get();

        return view('dashboard', compact('bases'));
    }
}
