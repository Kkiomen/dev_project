<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreApprovalTokenRequest;
use App\Http\Resources\ApprovalTokenResource;
use App\Models\ApprovalToken;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ApprovalTokenController extends Controller
{
    public function __construct(
        protected ApprovalService $approvalService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = ApprovalToken::forUser($request->user())
            ->withCount(['approvals as pending_count' => function ($query) {
                $query->whereNull('is_approved');
            }])
            ->latest()
            ->paginate($request->get('per_page', 20));

        return ApprovalTokenResource::collection($tokens);
    }

    public function store(StoreApprovalTokenRequest $request): ApprovalTokenResource
    {
        $token = $this->approvalService->createToken($request->user(), $request->validated());

        // Force show token on creation
        $token->wasRecentlyCreated = true;

        return new ApprovalTokenResource($token);
    }

    public function show(Request $request, ApprovalToken $approvalToken): ApprovalTokenResource
    {
        $this->authorize('view', $approvalToken);

        $approvalToken->loadCount(['approvals as pending_count' => function ($query) {
            $query->whereNull('is_approved');
        }]);

        return new ApprovalTokenResource($approvalToken);
    }

    public function destroy(Request $request, ApprovalToken $approvalToken): JsonResponse
    {
        $this->authorize('delete', $approvalToken);

        $this->approvalService->revokeToken($approvalToken);

        return response()->json(['message' => 'Token revoked successfully']);
    }

    public function regenerate(Request $request, ApprovalToken $approvalToken): ApprovalTokenResource
    {
        $this->authorize('regenerate', $approvalToken);

        $this->approvalService->regenerateToken($approvalToken);

        // Force show token after regeneration
        $approvalToken->wasRecentlyCreated = true;

        return new ApprovalTokenResource($approvalToken->fresh());
    }

    public function stats(Request $request, ApprovalToken $approvalToken): JsonResponse
    {
        $this->authorize('view', $approvalToken);

        $stats = $this->approvalService->getTokenStats($approvalToken);

        return response()->json($stats);
    }
}
