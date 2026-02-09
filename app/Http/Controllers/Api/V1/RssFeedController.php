<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRssFeedRequest;
use App\Http\Requests\Api\UpdateRssFeedRequest;
use App\Http\Resources\RssArticleResource;
use App\Http\Resources\RssFeedResource;
use App\Jobs\FetchRssFeedJob;
use App\Models\RssArticle;
use App\Models\RssFeed;
use App\Services\RssFeedService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RssFeedController extends Controller
{
    public function __construct(
        protected RssFeedService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $brand = $request->user()->getCurrentBrand();

        if (!$brand) {
            return RssFeedResource::collection(collect());
        }

        $feeds = $brand->rssFeeds()
            ->latest()
            ->paginate($request->get('per_page', 20));

        return RssFeedResource::collection($feeds);
    }

    public function store(StoreRssFeedRequest $request): RssFeedResource|\Illuminate\Http\JsonResponse
    {
        $brand = $request->user()->getCurrentBrand();

        abort_unless($brand, 400, 'No active brand selected.');
        abort_unless($brand->canUserEdit($request->user()), 403);

        try {
            $feed = $this->service->addFeed(
                $brand,
                $request->validated('url'),
                $request->validated('name'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new RssFeedResource($feed))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RssFeed $feed): RssFeedResource
    {
        $this->authorize('view', $feed);

        return new RssFeedResource($feed);
    }

    public function update(UpdateRssFeedRequest $request, RssFeed $feed): RssFeedResource
    {
        $this->authorize('update', $feed);

        $feed->update($request->validated());

        return new RssFeedResource($feed);
    }

    public function destroy(RssFeed $feed)
    {
        $this->authorize('delete', $feed);

        $feed->delete();

        return response()->noContent();
    }

    public function articles(Request $request, RssFeed $feed): AnonymousResourceCollection
    {
        $this->authorize('view', $feed);

        $articles = $feed->articles()
            ->latest('published_at')
            ->paginate($request->get('per_page', 20));

        return RssArticleResource::collection($articles);
    }

    public function refresh(RssFeed $feed): RssFeedResource
    {
        $this->authorize('update', $feed);

        FetchRssFeedJob::dispatch($feed);

        return new RssFeedResource($feed);
    }

    public function allArticles(Request $request): AnonymousResourceCollection
    {
        $brand = $request->user()->getCurrentBrand();

        if (!$brand) {
            return RssArticleResource::collection(collect());
        }

        $query = RssArticle::forBrand($brand)
            ->with('feed')
            ->latest('published_at');

        if ($feedId = $request->get('feed_id')) {
            $feed = RssFeed::where('public_id', $feedId)->firstOrFail();
            $query->forFeed($feed);
        }

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        if ($since = $request->get('since')) {
            $query->publishedAfter($since);
        }

        if ($category = $request->get('category')) {
            $query->whereJsonContains('categories', $category);
        }

        return RssArticleResource::collection(
            $query->paginate($request->get('per_page', 20))
        );
    }
}
