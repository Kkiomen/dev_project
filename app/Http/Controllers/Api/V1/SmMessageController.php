<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmMessageResource;
use App\Models\Brand;
use App\Models\SmMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmMessageController extends Controller
{
    public function index(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $query = $brand->smMessages();

        if ($request->has('platform')) {
            $query->where('platform', $request->input('platform'));
        }

        if ($request->has('is_read')) {
            $query->where('is_read', filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('direction')) {
            $query->where('direction', $request->input('direction'));
        }

        $messages = $query->orderByDesc('sent_at')
            ->paginate(20);

        return SmMessageResource::collection($messages);
    }

    public function show(Request $request, Brand $brand, SmMessage $smMessage): SmMessageResource
    {
        $this->authorize('view', $brand);

        $smMessage->markAsRead();

        return new SmMessageResource($smMessage);
    }

    public function markAsRead(Request $request, Brand $brand, SmMessage $smMessage): SmMessageResource
    {
        $this->authorize('update', $brand);

        $smMessage->markAsRead();

        return new SmMessageResource($smMessage);
    }
}
