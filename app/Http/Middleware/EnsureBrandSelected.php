<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBrandSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $brandId = $user->getCurrentBrandId();

        if (!$brandId) {
            return response()->json([
                'message' => 'No brand selected',
                'code' => 'no_brand_selected',
                'redirect' => '/brands/select',
            ], 428); // Precondition Required
        }

        $brand = Brand::find($brandId);

        if (!$brand || $brand->user_id !== $user->id) {
            // Invalid brand, clear it
            $user->setSetting('current_brand_id', null);
            $user->save();

            return response()->json([
                'message' => 'Invalid brand selected',
                'code' => 'invalid_brand',
                'redirect' => '/brands/select',
            ], 428);
        }

        if (!$brand->is_active) {
            return response()->json([
                'message' => 'Selected brand is inactive',
                'code' => 'brand_inactive',
                'redirect' => '/brands/select',
            ], 428);
        }

        // Bind current brand to container for easy access
        app()->instance('currentBrand', $brand);

        // Add brand to request for convenience
        $request->attributes->set('currentBrand', $brand);

        return $next($request);
    }
}
