<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePostProposalRequest;
use App\Http\Requests\Api\UpdatePostProposalRequest;
use App\Http\Resources\PostProposalResource;
use App\Http\Resources\SocialPostResource;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\PostProposal;
use App\Models\SocialPost;
use App\Enums\ProposalStatus;
use App\Services\ProposalBatchGeneratorService;
use App\Services\ProposalToPostService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostProposalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PostProposal::forUser($request->user());

        if ($request->has('brand_id')) {
            $brand = Brand::findByPublicIdOrFail($request->get('brand_id'));
            $query->where('brand_id', $brand->id);
        }

        if ($request->has('status')) {
            $status = ProposalStatus::tryFrom($request->get('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $proposals = $query->orderBy('scheduled_date')
            ->orderBy('position')
            ->paginate($request->get('per_page', 20));

        return PostProposalResource::collection($proposals);
    }

    public function calendar(Request $request): AnonymousResourceCollection
    {
        $query = PostProposal::forUser($request->user());

        if ($request->has('brand_id')) {
            $brand = Brand::findByPublicIdOrFail($request->get('brand_id'));
            $query->where('brand_id', $brand->id);
        }

        if ($request->has('start') && $request->has('end')) {
            $query->scheduledBetween($request->get('start'), $request->get('end'));
        }

        $proposals = $query->orderBy('scheduled_date')
            ->orderBy('position')
            ->get();

        return PostProposalResource::collection($proposals);
    }

    public function store(StorePostProposalRequest $request): PostProposalResource
    {
        $data = [
            'scheduled_date' => $request->scheduled_date,
            'scheduled_time' => $request->scheduled_time,
            'title' => $request->title,
            'keywords' => $request->keywords,
            'notes' => $request->notes,
            'status' => ProposalStatus::Pending,
        ];

        if ($request->has('brand_id')) {
            $brand = Brand::findByPublicIdOrFail($request->brand_id);
            $data['brand_id'] = $brand->id;
        }

        $proposal = $request->user()->postProposals()->create($data);

        return new PostProposalResource($proposal);
    }

    public function show(Request $request, PostProposal $proposal): PostProposalResource
    {
        $this->authorize('view', $proposal);

        return new PostProposalResource($proposal);
    }

    public function update(UpdatePostProposalRequest $request, PostProposal $proposal): PostProposalResource
    {
        $this->authorize('update', $proposal);

        $data = $request->validated();

        if (isset($data['brand_id'])) {
            $brand = Brand::findByPublicIdOrFail($data['brand_id']);
            $data['brand_id'] = $brand->id;
        }

        $proposal->update($data);

        return new PostProposalResource($proposal->fresh());
    }

    public function nextFreeDate(Request $request): JsonResponse
    {
        $user = $request->user();
        $brandId = null;

        if ($request->has('brand_id')) {
            $brand = Brand::findByPublicIdOrFail($request->get('brand_id'));
            $brandId = $brand->id;
        }

        $startDate = Carbon::tomorrow();
        $endDate = $startDate->copy()->addDays(90);

        $proposalDates = PostProposal::forUser($user)
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->whereBetween('scheduled_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->pluck('scheduled_date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->unique()
            ->toArray();

        $postDates = SocialPost::where('user_id', $user->id)
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->get()
            ->pluck('scheduled_at')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->unique()
            ->toArray();

        $occupiedDates = array_unique(array_merge($proposalDates, $postDates));

        $current = Carbon::tomorrow();
        for ($i = 0; $i < 90; $i++) {
            $dateStr = $current->toDateString();
            if (!in_array($dateStr, $occupiedDates)) {
                return response()->json(['date' => $dateStr]);
            }
            $current->addDay();
        }

        return response()->json(['date' => null]);
    }

    public function generateBatch(Request $request, ProposalBatchGeneratorService $service): JsonResponse
    {
        $request->validate([
            'days' => 'required|integer|min:3|max:30',
            'brand_id' => 'required|string',
            'language' => 'nullable|string|in:pl,en',
        ]);

        $brand = Brand::findByPublicIdOrFail($request->brand_id);

        $apiKey = BrandAiKey::getKeyForProvider($brand, \App\Enums\AiProvider::OpenAi);
        if (! $apiKey) {
            return response()->json([
                'message' => 'No OpenAI API key configured for this brand.',
                'error_code' => 'no_api_key',
            ], 422);
        }

        $totalFrequency = 0;
        foreach (\App\Enums\Platform::cases() as $platform) {
            if ($brand->isPlatformEnabled($platform)) {
                $totalFrequency += $brand->getPostingFrequency($platform);
            }
        }

        if ($totalFrequency === 0) {
            return response()->json([
                'message' => 'No posting frequency configured for this brand.',
                'error_code' => 'no_frequency',
            ], 422);
        }

        $language = $request->input('language', $brand->getLanguage());

        try {
            $proposals = $service->generate($brand, $request->user(), $request->days, $language);

            return response()->json([
                'message' => "{$proposals->count()} proposals generated.",
                'count' => $proposals->count(),
                'proposals' => PostProposalResource::collection($proposals),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function generatePost(Request $request, PostProposal $proposal, ProposalToPostService $service): JsonResponse
    {
        $this->authorize('update', $proposal);

        if (! $proposal->isPending()) {
            return response()->json(['message' => 'Proposal has already been used.', 'error_code' => 'already_used'], 422);
        }

        if (! $proposal->brand_id) {
            return response()->json(['message' => 'Proposal must be assigned to a brand.', 'error_code' => 'no_brand'], 422);
        }

        $apiKey = \App\Models\BrandAiKey::getKeyForProvider($proposal->brand, \App\Enums\AiProvider::OpenAi);
        if (! $apiKey) {
            return response()->json(['message' => 'No OpenAI API key configured for this brand.', 'error_code' => 'no_api_key'], 422);
        }

        try {
            $post = $service->generate($proposal);

            return response()->json([
                'message' => 'Post generated successfully.',
                'post' => new SocialPostResource($post->load(['platformPosts', 'media'])),
                'proposal' => new PostProposalResource($proposal->fresh()),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, PostProposal $proposal): JsonResponse
    {
        $this->authorize('delete', $proposal);

        $proposal->delete();

        return response()->json(['message' => 'Proposal deleted successfully']);
    }
}
