<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Http\Resources\SmAccountResource;
use App\Models\Brand;
use App\Models\SmAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class SmAccountController extends Controller
{
    public function index(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $accounts = $brand->smAccounts()->get();

        return SmAccountResource::collection($accounts);
    }

    public function store(Request $request, Brand $brand): SmAccountResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'platform' => ['required', 'string', Rule::in(Platform::values())],
            'platform_user_id' => ['nullable', 'string', 'max:255'],
            'handle' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
            'access_token' => ['nullable', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'token_expires_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        $account = $brand->smAccounts()->updateOrCreate(
            ['platform' => $validated['platform']],
            array_merge($validated, ['status' => 'active'])
        );

        return new SmAccountResource($account);
    }

    public function show(Request $request, Brand $brand, SmAccount $smAccount): SmAccountResource
    {
        $this->authorize('view', $brand);

        return new SmAccountResource($smAccount);
    }

    public function disconnect(Request $request, Brand $brand, string $platform): JsonResponse
    {
        $this->authorize('update', $brand);

        $account = $brand->smAccounts()->where('platform', $platform)->first();

        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        $account->markAsRevoked();

        return response()->json(['message' => 'Account disconnected']);
    }

    public function authUrl(Request $request, Brand $brand, string $platform): JsonResponse
    {
        $this->authorize('update', $brand);

        if (!in_array($platform, Platform::values())) {
            return response()->json(['message' => 'Invalid platform'], 422);
        }

        // OAuth URL generation will be implemented per-platform in Phase 4
        // For now, return a placeholder
        return response()->json([
            'platform' => $platform,
            'auth_url' => null,
            'message' => 'OAuth integration pending',
        ]);
    }
}
