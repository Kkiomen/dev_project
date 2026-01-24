<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\PlatformCredential;
use App\Services\OAuth\FacebookOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlatformCredentialController extends Controller
{
    /**
     * GET /brands/{brand}/platforms
     * List all connected platforms for a brand.
     */
    public function index(Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        $credentials = $brand->platformCredentials()->get();

        return response()->json([
            'platforms' => [
                'facebook' => $this->formatCredential($credentials->firstWhere('platform', 'facebook')),
                'instagram' => $this->formatCredential($credentials->firstWhere('platform', 'instagram')),
            ],
        ]);
    }

    /**
     * GET /brands/{brand}/platforms/{platform}/auth-url
     * Get OAuth authorization URL for a platform.
     */
    public function authUrl(Brand $brand, string $platform): JsonResponse
    {
        $this->authorize('update', $brand);

        if ($platform !== 'facebook') {
            return response()->json(['error' => 'Unsupported platform'], 400);
        }

        $service = new FacebookOAuthService();

        if (!$service->isConfigured()) {
            return response()->json(['error' => 'Facebook OAuth is not configured'], 500);
        }

        $url = $service->getAuthUrl($brand);

        return response()->json(['auth_url' => $url]);
    }

    /**
     * GET /auth/facebook/callback
     * Handle OAuth callback from Facebook (web route).
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $error = $request->get('error');

        if ($error) {
            Log::warning('Facebook OAuth error', ['error' => $error]);
            return redirect('/brands?error=oauth_denied');
        }

        if (!$code || !$state) {
            return redirect('/brands?error=invalid_callback');
        }

        $service = new FacebookOAuthService();
        $stateData = $service->validateState($state);

        if (!$stateData) {
            return redirect('/brands?error=invalid_state');
        }

        $brandId = $stateData['brand_id'];
        $brand = Brand::find($brandId);

        if (!$brand) {
            return redirect('/brands?error=brand_not_found');
        }

        // Check authorization
        if (!$request->user() || !$brand->canUserEdit($request->user())) {
            return redirect('/brands?error=unauthorized');
        }

        try {
            // Exchange code for token
            $tokenData = $service->exchangeCodeForToken($code);
            $longLivedToken = $service->getLongLivedToken($tokenData['access_token']);

            // Get user's pages
            $pages = $service->getUserPages($longLivedToken['access_token']);

            if (empty($pages)) {
                return redirect("/brands/{$brand->public_id}?error=no_pages");
            }

            // Store in session for page selection
            session(['facebook_oauth' => [
                'brand_id' => $brandId,
                'user_token' => $longLivedToken['access_token'],
                'expires_in' => $longLivedToken['expires_in'] ?? 5184000, // 60 days default
                'pages' => $pages,
            ]]);

            // Redirect to page selection UI
            return redirect("/brands/{$brand->public_id}/settings/platforms?step=select-page");

        } catch (\Exception $e) {
            Log::error('Facebook OAuth callback failed', [
                'brand_id' => $brandId,
                'error' => $e->getMessage(),
            ]);

            return redirect("/brands/{$brand->public_id}?error=oauth_failed");
        }
    }

    /**
     * GET /brands/{brand}/platforms/facebook/pages
     * Get available Facebook pages from session (for selection UI).
     */
    public function getPages(Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        $oauthData = session('facebook_oauth');

        if (!$oauthData || $oauthData['brand_id'] !== $brand->id) {
            return response()->json(['error' => 'No pending OAuth data'], 400);
        }

        return response()->json([
            'pages' => collect($oauthData['pages'])->map(fn ($page) => [
                'id' => $page['id'],
                'name' => $page['name'],
                'has_instagram' => isset($page['instagram_business_account']),
                'instagram' => $page['instagram_business_account'] ?? null,
            ]),
        ]);
    }

    /**
     * POST /brands/{brand}/platforms/facebook/select-page
     * Select a Facebook page to connect.
     */
    public function selectPage(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        $request->validate([
            'page_id' => 'required|string',
        ]);

        $oauthData = session('facebook_oauth');

        if (!$oauthData || $oauthData['brand_id'] !== $brand->id) {
            return response()->json(['error' => 'No pending OAuth data'], 400);
        }

        $pages = collect($oauthData['pages']);
        $selectedPage = $pages->firstWhere('id', $request->page_id);

        if (!$selectedPage) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        try {
            $service = new FacebookOAuthService();
            $pageToken = $service->getPageAccessToken($oauthData['user_token'], $selectedPage['id']);

            // Save Facebook credential
            $brand->platformCredentials()->updateOrCreate(
                ['platform' => 'facebook'],
                [
                    'platform_user_id' => $selectedPage['id'],
                    'platform_user_name' => $selectedPage['name'],
                    'access_token' => $pageToken, // Page tokens don't expire
                    'token_expires_at' => null,
                    'metadata' => [
                        'page_id' => $selectedPage['id'],
                    ],
                ]
            );

            // Save Instagram credential if available
            if (isset($selectedPage['instagram_business_account'])) {
                $igAccount = $selectedPage['instagram_business_account'];
                $brand->platformCredentials()->updateOrCreate(
                    ['platform' => 'instagram'],
                    [
                        'platform_user_id' => $igAccount['id'],
                        'platform_user_name' => $igAccount['username'] ?? null,
                        'access_token' => $pageToken, // Uses same page token
                        'token_expires_at' => null,
                        'metadata' => [
                            'instagram_business_id' => $igAccount['id'],
                            'facebook_page_id' => $selectedPage['id'],
                        ],
                    ]
                );
            }

            session()->forget('facebook_oauth');

            Log::info('Facebook/Instagram connected', [
                'brand_id' => $brand->id,
                'page_id' => $selectedPage['id'],
                'has_instagram' => isset($selectedPage['instagram_business_account']),
            ]);

            return response()->json([
                'success' => true,
                'connected' => [
                    'facebook' => true,
                    'instagram' => isset($selectedPage['instagram_business_account']),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save platform credentials', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to save credentials'], 500);
        }
    }

    /**
     * DELETE /brands/{brand}/platforms/{platform}
     * Disconnect a platform.
     */
    public function disconnect(Brand $brand, string $platform): JsonResponse
    {
        $this->authorize('update', $brand);

        $deleted = $brand->platformCredentials()
            ->where('platform', $platform)
            ->delete();

        // If disconnecting Facebook, also disconnect Instagram (they share tokens)
        if ($platform === 'facebook') {
            $brand->platformCredentials()
                ->where('platform', 'instagram')
                ->delete();
        }

        Log::info('Platform disconnected', [
            'brand_id' => $brand->id,
            'platform' => $platform,
        ]);

        return response()->json(['success' => $deleted > 0]);
    }

    /**
     * POST /brands/{brand}/platforms/{platform}/verify
     * Verify if a platform token is still valid.
     */
    public function verify(Brand $brand, string $platform): JsonResponse
    {
        $this->authorize('view', $brand);

        $credential = $brand->platformCredentials()
            ->where('platform', $platform)
            ->first();

        if (!$credential) {
            return response()->json([
                'connected' => false,
                'valid' => false,
            ]);
        }

        $service = new FacebookOAuthService();
        $isValid = $service->verifyToken($credential->access_token);

        return response()->json([
            'connected' => true,
            'valid' => $isValid,
            'is_expired' => $credential->isExpired(),
            'is_expiring_soon' => $credential->isExpiringSoon(),
        ]);
    }

    /**
     * Format credential for API response.
     */
    private function formatCredential(?PlatformCredential $credential): ?array
    {
        if (!$credential) {
            return null;
        }

        return [
            'connected' => true,
            'account_name' => $credential->platform_user_name,
            'account_id' => $credential->platform_user_id,
            'connected_at' => $credential->created_at->toIso8601String(),
            'is_expired' => $credential->isExpired(),
            'is_expiring_soon' => $credential->isExpiringSoon(),
        ];
    }
}
