<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmBrandKitResource;
use App\Models\Brand;
use App\Models\SmBrandKit;
use App\Services\SmManager\SmBrandKitGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SmBrandKitController extends Controller
{
    public function show(Request $request, Brand $brand): SmBrandKitResource
    {
        $this->authorize('view', $brand);

        $kit = $brand->smBrandKit ?? $brand->smBrandKit()->create([]);

        return new SmBrandKitResource($kit);
    }

    public function update(Request $request, Brand $brand): SmBrandKitResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'colors' => ['sometimes', 'array'],
            'colors.primary' => ['nullable', 'string', 'max:7'],
            'colors.secondary' => ['nullable', 'string', 'max:7'],
            'colors.accent' => ['nullable', 'string', 'max:7'],
            'colors.background' => ['nullable', 'string', 'max:7'],
            'colors.text' => ['nullable', 'string', 'max:7'],

            'fonts' => ['sometimes', 'array'],
            'fonts.heading' => ['nullable', 'array'],
            'fonts.heading.family' => ['nullable', 'string', 'max:100'],
            'fonts.heading.weight' => ['nullable', 'string', 'max:10'],
            'fonts.body' => ['nullable', 'array'],
            'fonts.body.family' => ['nullable', 'string', 'max:100'],
            'fonts.body.weight' => ['nullable', 'string', 'max:10'],
            'fonts.accent' => ['nullable', 'array'],
            'fonts.accent.family' => ['nullable', 'string', 'max:100'],
            'fonts.accent.weight' => ['nullable', 'string', 'max:10'],

            'style_preset' => ['nullable', 'string', 'in:modern,classic,bold,minimal,playful'],
            'tone_of_voice' => ['nullable', 'string', 'in:professional,casual,friendly,authoritative,humorous,inspirational'],
            'voice_attributes' => ['sometimes', 'array'],
            'voice_attributes.*' => ['string', 'max:50'],

            'content_pillars' => ['sometimes', 'array'],
            'content_pillars.*.name' => ['required', 'string', 'max:100'],
            'content_pillars.*.percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'content_pillars.*.description' => ['nullable', 'string', 'max:500'],

            'hashtag_groups' => ['sometimes', 'array'],
            'hashtag_groups.branded' => ['sometimes', 'array'],
            'hashtag_groups.branded.*' => ['string', 'max:100'],
            'hashtag_groups.industry' => ['sometimes', 'array'],
            'hashtag_groups.industry.*' => ['string', 'max:100'],

            'brand_guidelines_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $kit = $brand->smBrandKit ?? $brand->smBrandKit()->create([]);
        $kit->update($validated);

        return new SmBrandKitResource($kit);
    }

    public function generate(Request $request, Brand $brand, SmBrandKitGeneratorService $generator): JsonResponse
    {
        $this->authorize('update', $brand);

        $result = $generator->generateBrandKit($brand);

        if (!$result['success']) {
            $status = ($result['error_code'] ?? '') === 'no_api_key' ? 422 : 500;
            return response()->json(['success' => false, 'error' => $result['error']], $status);
        }

        $kit = $brand->smBrandKit ?? $brand->smBrandKit()->create([]);
        $kit->update($result['brand_kit']);

        return response()->json([
            'success' => true,
            'data' => new SmBrandKitResource($kit->fresh()),
        ]);
    }

    public function uploadLogo(Request $request, Brand $brand): SmBrandKitResource
    {
        $this->authorize('update', $brand);

        $request->validate([
            'logo' => ['required', 'image', 'max:2048'],
            'variant' => ['sometimes', 'string', 'in:light,dark'],
        ]);

        $kit = $brand->smBrandKit ?? $brand->smBrandKit()->create([]);
        $variant = $request->input('variant', 'light');
        $field = $variant === 'dark' ? 'logo_dark_path' : 'logo_path';

        // Delete old logo if exists
        if ($kit->$field) {
            Storage::disk('public')->delete($kit->$field);
        }

        $path = $request->file('logo')->store(
            "brands/{$brand->public_id}/brand-kit",
            'public'
        );

        $kit->update([$field => $path]);

        return new SmBrandKitResource($kit);
    }

    public function deleteLogo(Request $request, Brand $brand): SmBrandKitResource
    {
        $this->authorize('update', $brand);

        $request->validate([
            'variant' => ['sometimes', 'string', 'in:light,dark'],
        ]);

        $kit = $brand->smBrandKit;

        if (!$kit) {
            return new SmBrandKitResource($brand->smBrandKit()->create([]));
        }

        $variant = $request->input('variant', 'light');
        $field = $variant === 'dark' ? 'logo_dark_path' : 'logo_path';

        if ($kit->$field) {
            Storage::disk('public')->delete($kit->$field);
            $kit->update([$field => null]);
        }

        return new SmBrandKitResource($kit);
    }
}
