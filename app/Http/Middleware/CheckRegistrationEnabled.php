<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRegistrationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!AppSetting::getBool('registration_enabled', true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('app.registration_disabled'),
                ], 403);
            }

            return redirect()->route('login')
                ->with('status', __('app.registration_disabled'));
        }

        return $next($request);
    }
}
