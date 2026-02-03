<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED_LOCALES = ['en', 'pl'];

    /**
     * Handle an incoming request.
     *
     * Priority: X-Locale header > session > cookie > Accept-Language > default
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('X-Locale')
            ?? ($request->hasSession() ? $request->session()->get('locale') : null)
            ?? $request->cookie('locale')
            ?? $this->parseAcceptLanguage($request);

        if ($locale && in_array($locale, self::SUPPORTED_LOCALES)) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    private function parseAcceptLanguage(Request $request): ?string
    {
        $acceptLanguage = $request->header('Accept-Language', '');

        if (str_contains($acceptLanguage, 'pl')) {
            return 'pl';
        }

        if (str_contains($acceptLanguage, 'en')) {
            return 'en';
        }

        return null;
    }
}
