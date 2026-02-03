<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    private const SUPPORTED_LOCALES = ['en', 'pl'];

    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, self::SUPPORTED_LOCALES)) {
            $locale = config('app.locale', 'en');
        }

        App::setLocale($locale);
        $request->session()->put('locale', $locale);

        return redirect()
            ->back()
            ->withCookie(cookie('locale', $locale, 525600)); // 1 year
    }
}
