<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Controller for temporary render data storage.
 *
 * Used by template-renderer service to pass large template data
 * without exceeding URL length limits.
 */
class RenderDataController extends Controller
{
    /**
     * Store template data temporarily and return a key.
     *
     * POST /api/v1/render-data
     * Body: { template: {...}, width, height, scale }
     */
    public function store(Request $request)
    {
        $data = $request->all();

        // Generate unique key
        $key = 'render_data_' . Str::uuid();

        // Store in cache for 5 minutes (enough for render)
        Cache::put($key, $data, now()->addMinutes(5));

        return response()->json([
            'key' => $key,
        ]);
    }

    /**
     * Retrieve template data by key.
     *
     * GET /api/v1/render-data/{key}
     */
    public function show(string $key)
    {
        $data = Cache::get($key);

        if (!$data) {
            return response()->json([
                'error' => 'Render data not found or expired',
            ], 404);
        }

        // Delete after retrieval (one-time use)
        Cache::forget($key);

        return response()->json($data);
    }
}
