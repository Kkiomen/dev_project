<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandAiKeyController extends Controller
{
    public function index(Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        $keys = $brand->aiKeys()->get();

        $providers = collect(AiProvider::cases())->map(function (AiProvider $provider) use ($keys) {
            $key = $keys->firstWhere('provider', $provider);

            return [
                'provider' => $provider->value,
                'provider_label' => $provider->label(),
                'is_active' => $key?->is_active ?? false,
                'has_key' => $key !== null,
            ];
        });

        return response()->json($providers->values());
    }

    public function store(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        $request->validate([
            'provider' => ['required', 'string', 'in:' . implode(',', AiProvider::values())],
            'api_key' => ['required', 'string', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $brand->aiKeys()->updateOrCreate(
            ['provider' => $request->input('provider')],
            [
                'api_key' => $request->input('api_key'),
                'is_active' => $request->input('is_active', true),
            ]
        );

        return response()->json([
            'provider' => $request->input('provider'),
            'has_key' => true,
            'is_active' => $request->input('is_active', true),
        ]);
    }

    public function destroy(Brand $brand, string $provider): JsonResponse
    {
        $this->authorize('update', $brand);

        if (!in_array($provider, AiProvider::values())) {
            return response()->json(['message' => 'Invalid provider'], 422);
        }

        $brand->aiKeys()->where('provider', $provider)->delete();

        return response()->json([
            'provider' => $provider,
            'has_key' => false,
            'is_active' => false,
        ]);
    }
}
