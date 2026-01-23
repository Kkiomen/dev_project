<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Base;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    public function show(Request $request, Base $base)
    {
        $this->authorize('view', $base);

        $base->load('tables');

        // Redirect to first table if exists
        if ($base->tables->isNotEmpty()) {
            return redirect()->route('web.tables.show', $base->tables->first());
        }

        return view('bases.show', compact('base'));
    }
}
