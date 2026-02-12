<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmCommentResource;
use App\Models\Brand;
use App\Models\SmComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmCommentController extends Controller
{
    public function index(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $query = $brand->smComments();

        if ($request->has('platform')) {
            $query->where('platform', $request->input('platform'));
        }

        if ($request->has('sentiment')) {
            $query->where('sentiment', $request->input('sentiment'));
        }

        if ($request->has('is_replied')) {
            $query->where('is_replied', filter_var($request->input('is_replied'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('is_flagged')) {
            $query->where('is_flagged', filter_var($request->input('is_flagged'), FILTER_VALIDATE_BOOLEAN));
        }

        $comments = $query->orderByDesc('posted_at')
            ->paginate(20);

        return SmCommentResource::collection($comments);
    }

    public function show(Request $request, Brand $brand, SmComment $smComment): SmCommentResource
    {
        $this->authorize('view', $brand);

        return new SmCommentResource($smComment);
    }

    public function reply(Request $request, Brand $brand, SmComment $smComment): SmCommentResource
    {
        $this->authorize('update', $brand);

        $request->validate([
            'reply_text' => ['required', 'string', 'max:2000'],
        ]);

        $smComment->markAsReplied($request->reply_text);

        return new SmCommentResource($smComment);
    }

    public function hide(Request $request, Brand $brand, SmComment $smComment): SmCommentResource
    {
        $this->authorize('update', $brand);

        $smComment->is_hidden = true;
        $smComment->save();

        return new SmCommentResource($smComment);
    }

    public function flag(Request $request, Brand $brand, SmComment $smComment): SmCommentResource
    {
        $this->authorize('update', $brand);

        $smComment->is_flagged = true;
        $smComment->save();

        return new SmCommentResource($smComment);
    }
}
