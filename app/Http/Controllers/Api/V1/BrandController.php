<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBrandRequest;
use App\Http\Requests\Api\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Jobs\ProcessAutomationJob;
use App\Models\Brand;
use App\Models\BrandMember;
use App\Services\AI\BrandSuggestionService;
use App\Services\Automation\AutomationOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // Get all brands user has access to (owned + member of)
        $brands = $request->user()->allBrands()
            ->withCount(['posts', 'templates'])
            ->latest()
            ->get();

        return BrandResource::collection($brands);
    }

    public function store(StoreBrandRequest $request): BrandResource
    {
        $brand = $request->user()->brands()->create($request->validated());

        // Create BrandMember with owner role
        BrandMember::create([
            'brand_id' => $brand->id,
            'user_id' => $request->user()->id,
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        // Set as current brand if user doesn't have one selected
        if (!$request->user()->getCurrentBrand()) {
            $request->user()->setCurrentBrand($brand);
        }

        return new BrandResource($brand);
    }

    public function show(Request $request, Brand $brand): BrandResource
    {
        $this->authorize('view', $brand);

        return new BrandResource(
            $brand->loadCount(['posts', 'templates'])
        );
    }

    public function update(UpdateBrandRequest $request, Brand $brand): BrandResource
    {
        $this->authorize('update', $brand);

        $brand->update($request->validated());

        return new BrandResource(
            $brand->loadCount(['posts', 'templates'])
        );
    }

    public function destroy(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('delete', $brand);

        // If this is the current brand, clear it
        if ($request->user()->getCurrentBrandId() === $brand->id) {
            $request->user()->setSetting('current_brand_id', null);
            $request->user()->save();
        }

        $brand->delete();

        return response()->json(['message' => 'Brand deleted successfully']);
    }

    public function setCurrent(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        $request->user()->setCurrentBrand($brand);

        return response()->json([
            'message' => 'Current brand updated',
            'brand' => new BrandResource($brand),
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $brand = $request->user()->getCurrentBrand();

        if (!$brand) {
            return response()->json([
                'brand' => null,
                'message' => 'No brand selected',
            ]);
        }

        return response()->json([
            'brand' => new BrandResource($brand->loadCount(['posts', 'templates'])),
        ]);
    }

    public function completeOnboarding(Request $request, Brand $brand): BrandResource
    {
        $this->authorize('update', $brand);

        $brand->completeOnboarding();

        return new BrandResource($brand);
    }

    public function generateSuggestions(Request $request, BrandSuggestionService $suggestionService): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:interests,painPoints,contentPillars',
            'brandData' => 'required|array',
            'brandData.name' => 'nullable|string',
            'brandData.description' => 'required|string|min:10',
            'brandData.industry' => 'nullable|string',
            'brandData.ageRange' => 'nullable|string',
            'brandData.gender' => 'nullable|string',
        ]);

        try {
            $suggestions = $suggestionService->generateSuggestions(
                $request->input('type'),
                $request->input('brandData')
            );

            return response()->json([
                'success' => true,
                'data' => $suggestions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function automationStats(Request $request, Brand $brand, AutomationOrchestrator $orchestrator): JsonResponse
    {
        $this->authorize('view', $brand);

        $status = $orchestrator->getAutomationStatus($brand);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    public function enableAutomation(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        $request->validate([
            'content_queue_days' => 'sometimes|integer|min:1|max:30',
            'automation_settings' => 'sometimes|array',
        ]);

        $brand->automation_enabled = true;

        if ($request->has('content_queue_days')) {
            $brand->content_queue_days = $request->input('content_queue_days');
        }

        if ($request->has('automation_settings')) {
            $brand->automation_settings = $request->input('automation_settings');
        }

        $brand->save();

        return response()->json([
            'success' => true,
            'message' => 'Automation enabled',
            'brand' => new BrandResource($brand),
        ]);
    }

    public function disableAutomation(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        $brand->disableAutomation();

        return response()->json([
            'success' => true,
            'message' => 'Automation disabled',
            'brand' => new BrandResource($brand),
        ]);
    }

    public function triggerAutomation(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        if (!$brand->isAutomationEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Automation is not enabled for this brand',
            ], 400);
        }

        ProcessAutomationJob::dispatch($brand);

        return response()->json([
            'success' => true,
            'message' => 'Automation job dispatched',
        ]);
    }

    public function updateAutomationSettings(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        $request->validate([
            'content_queue_days' => 'sometimes|integer|min:1|max:30',
            'automation_settings' => 'sometimes|array',
        ]);

        \Log::info('updateAutomationSettings called', [
            'brand_id' => $brand->id,
            'request_all' => $request->all(),
            'has_automation_settings' => $request->has('automation_settings'),
            'existing_settings' => $brand->automation_settings,
        ]);

        if ($request->has('content_queue_days')) {
            $brand->content_queue_days = $request->input('content_queue_days');
        }

        if ($request->has('automation_settings')) {
            $brand->automation_settings = array_merge(
                $brand->automation_settings ?? [],
                $request->input('automation_settings')
            );
        }

        \Log::info('After merge', ['new_settings' => $brand->automation_settings]);

        $brand->save();

        return response()->json([
            'success' => true,
            'message' => 'Automation settings updated',
            'brand' => new BrandResource($brand),
        ]);
    }

    public function extendQueue(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        if (!$brand->isAutomationEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Automation is not enabled for this brand',
            ], 400);
        }

        $request->validate([
            'days' => 'sometimes|integer|min:1|max:14',
        ]);

        $extraDays = $request->input('days', 7);

        $orchestrator = app(\App\Services\Automation\AutomationOrchestrator::class);

        // Temporarily increase queue days to create new slots
        $originalQueueDays = $brand->getContentQueueDays();
        $brand->content_queue_days = $originalQueueDays + $extraDays;

        // Refill queue with extended days
        $slotsCreated = $orchestrator->refillQueue($brand);

        // Restore original queue days
        $brand->content_queue_days = $originalQueueDays;
        $brand->save();

        // If slots were created, generate content for them
        if ($slotsCreated > 0) {
            $orchestrator->generatePendingContent($brand, $slotsCreated);
        }

        return response()->json([
            'success' => true,
            'message' => "Extended queue with {$slotsCreated} new slots",
            'slots_created' => $slotsCreated,
        ]);
    }

    /**
     * Get system prompts for n8n integration
     */
    public function getSystemPrompts(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        $settings = $brand->automation_settings ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'text_system_prompt' => $settings['text_system_prompt'] ?? '',
                'image_system_prompt' => $settings['image_system_prompt'] ?? '',
                'variables' => $this->getAvailableVariables($brand),
            ],
        ]);
    }

    /**
     * Update system prompts
     */
    public function updateSystemPrompts(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        $request->validate([
            'text_system_prompt' => 'nullable|string|max:10000',
            'image_system_prompt' => 'nullable|string|max:10000',
        ]);

        $settings = $brand->automation_settings ?? [];

        if ($request->has('text_system_prompt')) {
            $settings['text_system_prompt'] = $request->input('text_system_prompt');
        }

        if ($request->has('image_system_prompt')) {
            $settings['image_system_prompt'] = $request->input('image_system_prompt');
        }

        $brand->automation_settings = $settings;
        $brand->save();

        return response()->json([
            'success' => true,
            'message' => 'System prompts updated',
            'data' => [
                'text_system_prompt' => $settings['text_system_prompt'] ?? '',
                'image_system_prompt' => $settings['image_system_prompt'] ?? '',
            ],
        ]);
    }

    /**
     * Get resolved system prompt for n8n (with variables replaced)
     */
    public function getResolvedPrompt(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        $request->validate([
            'type' => 'required|string|in:text,image',
        ]);

        $type = $request->input('type');
        $settings = $brand->automation_settings ?? [];
        $promptKey = $type . '_system_prompt';
        $prompt = $settings[$promptKey] ?? '';

        // Replace variables with actual brand data
        $resolvedPrompt = $this->replaceVariables($prompt, $brand);

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $type,
                'prompt' => $resolvedPrompt,
                'brand_context' => $brand->buildAiContext(),
            ],
        ]);
    }

    /**
     * Get available variables for prompts
     */
    protected function getAvailableVariables(Brand $brand): array
    {
        $targetAudience = $brand->target_audience ?? [];
        $voice = $brand->voice ?? [];

        return [
            'brand_name' => $brand->name,
            'brand_description' => $brand->description,
            'industry' => $brand->industry,
            'tone' => $voice['tone'] ?? '',
            'language' => $voice['language'] ?? 'pl',
            'emoji_usage' => $voice['emoji_usage'] ?? 'sometimes',
            'personality' => implode(', ', $voice['personality'] ?? []),
            'target_age_range' => $targetAudience['age_range'] ?? '',
            'target_gender' => $targetAudience['gender'] ?? 'all',
            'interests' => implode(', ', $targetAudience['interests'] ?? []),
            'pain_points' => implode(', ', $targetAudience['pain_points'] ?? []),
            'content_pillars' => implode(', ', array_column($brand->content_pillars ?? [], 'name')),
        ];
    }

    /**
     * Replace variables in prompt with brand data
     */
    protected function replaceVariables(string $prompt, Brand $brand): string
    {
        $variables = $this->getAvailableVariables($brand);

        foreach ($variables as $key => $value) {
            $prompt = str_replace('{{' . $key . '}}', $value, $prompt);
        }

        return $prompt;
    }
}
