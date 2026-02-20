<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->settingsResponse();
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'registration_enabled' => ['sometimes', 'boolean'],
            'login_enabled' => ['sometimes', 'boolean'],
        ]);

        foreach ($validated as $key => $value) {
            AppSetting::setValue($key, $value ? 'true' : 'false');
        }

        return $this->settingsResponse();
    }

    private function settingsResponse(): JsonResponse
    {
        return response()->json([
            'data' => [
                'registration_enabled' => AppSetting::getBool('registration_enabled', true),
                'login_enabled' => AppSetting::getBool('login_enabled', true),
            ],
        ]);
    }
}
