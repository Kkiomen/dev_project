<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AiChatRequest;
use App\Models\Template;
use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;

class AiChatController extends Controller
{
    public function __construct(
        protected AiChatService $chatService
    ) {}

    /**
     * Process a chat message for a template.
     */
    public function chat(AiChatRequest $request, Template $template): JsonResponse
    {
        // Ensure user owns the template
        if ($template->user_id !== $request->user()->id) {
            abort(403);
        }

        try {
            $result = $this->chatService->chat(
                $template,
                $request->input('history', []),
                $request->input('message')
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
