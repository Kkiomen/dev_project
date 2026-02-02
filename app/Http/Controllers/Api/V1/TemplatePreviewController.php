<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Services\TemplateRenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TemplatePreviewController extends Controller
{
    public function __construct(
        protected TemplateRenderService $renderService
    ) {}

    /**
     * Generate preview images for templates with substituted content.
     *
     * POST /api/v1/library/templates/preview
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.header' => 'nullable|string|max:500',
            'data.subtitle' => 'nullable|string|max:500',
            'data.paragraph' => 'nullable|string|max:2000',
            'data.primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'data.secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'data.social_handle' => 'nullable|string|max:100', // e.g. @aisello
            'data.main_image' => 'nullable|string', // base64 data URL or image URL
            'template_ids' => 'nullable|array',
            'template_ids.*' => 'string',
        ]);

        // Get templates - either specific ones or all library templates with semantic tags
        $query = Template::library()->with('layers');

        if (!empty($validated['template_ids'])) {
            $query->whereIn('public_id', $validated['template_ids']);
        }

        // Filter to templates that have at least one layer with a semantic tag
        // Support both old format (semanticTag) and new format (semanticTags array)
        $templates = $query->get()->filter(function ($template) {
            return $template->layers->contains(function ($layer) {
                $hasNewFormat = !empty($layer->properties['semanticTags']);
                $hasOldFormat = !empty($layer->properties['semanticTag']);
                return $hasNewFormat || $hasOldFormat;
            });
        });

        if ($templates->isEmpty()) {
            return response()->json([
                'previews' => [],
                'message' => __('graphics.template_preview.no_tagged_templates'),
            ]);
        }

        // Render previews
        $previews = [];
        foreach ($templates as $template) {
            try {
                $path = $this->renderService->renderAndStore($template, $validated['data']);
                $previews[] = [
                    'id' => $template->public_id,
                    'name' => $template->name,
                    'preview_url' => asset('storage/' . $path),
                    'thumbnail_url' => $template->thumbnail_url,
                    'width' => $template->width,
                    'height' => $template->height,
                ];
            } catch (\Exception $e) {
                Log::error('Template preview generation failed', [
                    'template_id' => $template->public_id,
                    'error' => $e->getMessage(),
                ]);
                $previews[] = [
                    'id' => $template->public_id,
                    'name' => $template->name,
                    'error' => __('graphics.template_preview.render_failed'),
                    'thumbnail_url' => $template->thumbnail_url,
                ];
            }
        }

        return response()->json([
            'previews' => $previews,
        ]);
    }

    /**
     * Check if the template renderer service is healthy.
     *
     * GET /api/v1/library/templates/preview/health
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        $isHealthy = $this->renderService->isHealthy();

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'unavailable',
            'service' => 'template-renderer',
        ], $isHealthy ? 200 : 503);
    }

    /**
     * Get available semantic tags for tagging layers.
     *
     * GET /api/v1/library/templates/semantic-tags
     *
     * @return JsonResponse
     */
    public function semanticTags(): JsonResponse
    {
        return response()->json([
            'tags' => TemplateRenderService::availableSemanticTags(),
        ]);
    }
}
