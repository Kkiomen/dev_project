<?php

namespace App\Services\OAuth;

use App\Models\Brand;
use Illuminate\Support\Facades\Http;

class FacebookOAuthService
{
    private string $appId;
    private string $appSecret;
    private string $redirectUrl;
    private string $graphVersion;

    public function __construct()
    {
        $this->appId = config('services.facebook.app_id');
        $this->appSecret = config('services.facebook.app_secret');
        $this->redirectUrl = config('services.facebook.redirect_url');
        $this->graphVersion = config('services.facebook.graph_version', 'v18.0');
    }

    /**
     * Check if the service is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->appId) && !empty($this->appSecret);
    }

    /**
     * Generate OAuth authorization URL.
     */
    public function getAuthUrl(Brand $brand, array $scopes = []): string
    {
        $defaultScopes = [
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',
            'instagram_basic',
            'instagram_content_publish',
        ];

        $params = [
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => implode(',', $scopes ?: $defaultScopes),
            'state' => encrypt([
                'brand_id' => $brand->id,
                'timestamp' => now()->timestamp,
            ]),
            'response_type' => 'code',
        ];

        return "https://www.facebook.com/{$this->graphVersion}/dialog/oauth?" . http_build_query($params);
    }

    /**
     * Validate and decrypt state parameter.
     */
    public function validateState(string $encryptedState): ?array
    {
        try {
            $state = decrypt($encryptedState);

            // Validate timestamp (must be within last 10 minutes)
            if ($state['timestamp'] < now()->subMinutes(10)->timestamp) {
                return null;
            }

            return $state;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Exchange authorization code for access token.
     */
    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::get("https://graph.facebook.com/{$this->graphVersion}/oauth/access_token", [
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $this->redirectUrl,
            'code' => $code,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to exchange code for token: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Exchange short-lived token for long-lived token (60 days).
     */
    public function getLongLivedToken(string $shortLivedToken): array
    {
        $response = Http::get("https://graph.facebook.com/{$this->graphVersion}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to get long-lived token: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get current user info.
     */
    public function getUser(string $accessToken): array
    {
        $response = Http::get("https://graph.facebook.com/{$this->graphVersion}/me", [
            'access_token' => $accessToken,
            'fields' => 'id,name,email',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to get user info: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get Facebook Pages the user has access to.
     */
    public function getUserPages(string $accessToken): array
    {
        $response = Http::get("https://graph.facebook.com/{$this->graphVersion}/me/accounts", [
            'access_token' => $accessToken,
            'fields' => 'id,name,access_token,instagram_business_account{id,username,profile_picture_url}',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to get pages: ' . $response->body());
        }

        return $response->json()['data'] ?? [];
    }

    /**
     * Get Page Access Token (never expires if obtained from long-lived user token).
     */
    public function getPageAccessToken(string $userToken, string $pageId): string
    {
        $response = Http::get("https://graph.facebook.com/{$this->graphVersion}/{$pageId}", [
            'access_token' => $userToken,
            'fields' => 'access_token',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to get page access token: ' . $response->body());
        }

        return $response->json()['access_token'];
    }

    /**
     * Verify if a token is still valid.
     */
    public function verifyToken(string $accessToken): bool
    {
        $response = Http::get("https://graph.facebook.com/{$this->graphVersion}/debug_token", [
            'input_token' => $accessToken,
            'access_token' => "{$this->appId}|{$this->appSecret}",
        ]);

        if (!$response->successful()) {
            return false;
        }

        $data = $response->json()['data'] ?? [];

        return ($data['is_valid'] ?? false) === true;
    }

    /**
     * Get Instagram Business Account details.
     */
    public function getInstagramAccount(string $accessToken, string $instagramId): array
    {
        $response = Http::get("https://graph.facebook.com/{$this->graphVersion}/{$instagramId}", [
            'access_token' => $accessToken,
            'fields' => 'id,username,profile_picture_url,followers_count,media_count',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to get Instagram account: ' . $response->body());
        }

        return $response->json();
    }
}
