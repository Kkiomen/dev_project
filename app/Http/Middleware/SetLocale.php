<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for X-Locale header first (explicit frontend setting)
        $locale = $request->header('X-Locale');

        // Fallback to Accept-Language header
        if (!$locale) {
            $acceptLanguage = $request->header('Accept-Language', '');
            if (str_contains($acceptLanguage, 'pl')) {
                $locale = 'pl';
            } elseif (str_contains($acceptLanguage, 'en')) {
                $locale = 'en';
            }
        }

        // Set locale if valid
        if ($locale && in_array($locale, ['en', 'pl'])) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
