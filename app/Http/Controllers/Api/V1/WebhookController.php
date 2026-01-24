<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Publishing\PublishingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected PublishingService $publishingService
    ) {}

    /**
     * Handle publishing result callback from n8n.
     */
    public function publishResult(Request $request): JsonResponse
    {
        // Validate webhook secret if configured
        $secret = config('services.n8n.webhook_secret');
        if ($secret && $request->header('X-Webhook-Secret') !== $secret) {
            Log::warning('Invalid webhook secret', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid webhook secret'], 403);
        }

        $request->validate([
            'post_id' => ['required', 'string'],
            'platform' => ['required', 'string', 'in:facebook,instagram,youtube'],
            'success' => ['required', 'boolean'],
            'external_id' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
        ]);

        $result = $this->publishingService->handleCallback($request->all());

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * Health check endpoint for n8n.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'publishing-webhook',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
