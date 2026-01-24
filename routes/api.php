<?php

use App\Http\Controllers\Api\V1\AiChatController;
use App\Http\Controllers\Api\V1\ApprovalTokenController;
use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\ClientApprovalController;
use App\Http\Controllers\Api\V1\ContentPlanController;
use App\Http\Controllers\Api\V1\PostAiController;
use App\Http\Controllers\Api\V1\StockPhotoController;
use App\Http\Controllers\Api\V1\TableController;
use App\Http\Controllers\Api\V1\FieldController;
use App\Http\Controllers\Api\V1\RowController;
use App\Http\Controllers\Api\V1\CellController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\PlatformPostController;
use App\Http\Controllers\Api\V1\PostMediaController;
use App\Http\Controllers\Api\V1\SocialPostController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\TemplateLibraryController;
use App\Http\Controllers\Api\V1\LayerController;
use App\Http\Controllers\Api\V1\GeneratedImageController;
use App\Http\Controllers\Api\V1\TemplateFontController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// Helper function for deep merging settings
if (!function_exists('array_merge_recursive_distinct')) {
    function array_merge_recursive_distinct(array $array1, array $array2): array {
        $merged = $array1;
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }
}

Route::get('/user', function (Request $request) {
    $user = $request->user();
    $currentBrand = $user->getCurrentBrand();

    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'is_admin' => $user->is_admin,
        'settings' => $user->settings ?? [
            'weekStartsOn' => 1,
            'timeFormat' => '24h',
            'language' => 'pl',
        ],
        'current_brand' => $currentBrand ? [
            'id' => $currentBrand->public_id,
            'name' => $currentBrand->name,
            'onboarding_completed' => $currentBrand->onboarding_completed,
        ] : null,
        'brands_count' => $user->brands()->active()->count(),
        'email_verified_at' => $user->email_verified_at,
        'created_at' => $user->created_at,
        'updated_at' => $user->updated_at,
    ];
})->middleware('auth:sanctum');

Route::put('/user/settings', function (Request $request) {
    $request->validate([
        'settings' => 'required|array',
        'settings.weekStartsOn' => 'sometimes|integer|in:0,1',
        'settings.timeFormat' => 'sometimes|string|in:12h,24h',
        'settings.language' => 'sometimes|string|in:pl,en',
        'settings.timezone' => 'sometimes|string|max:50',
        // AI Settings
        'settings.ai.creativity' => 'sometimes|string|in:low,medium,high',
        'settings.ai.defaultLength' => 'sometimes|string|in:short,medium,long',
        'settings.ai.customInstructions' => 'sometimes|nullable|string|max:1000',
        'settings.ai.autoSuggest' => 'sometimes|boolean',
        // Notification Settings
        'settings.notifications.email' => 'sometimes|boolean',
        'settings.notifications.postPublished' => 'sometimes|boolean',
        'settings.notifications.approvalRequired' => 'sometimes|boolean',
        'settings.notifications.weeklyReport' => 'sometimes|boolean',
    ]);

    $user = $request->user();
    $user->settings = array_merge_recursive_distinct($user->settings ?? [], $request->input('settings'));
    $user->save();

    return ['message' => 'Settings updated', 'settings' => $user->settings];
})->middleware('auth:sanctum');

Route::put('/user/profile', function (Request $request) {
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email,' . $request->user()->id,
    ]);

    $user = $request->user();
    $user->name = $request->input('name');
    $user->email = $request->input('email');
    $user->save();

    return ['message' => 'Profile updated', 'user' => [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ]];
})->middleware('auth:sanctum');

Route::put('/user/password', function (Request $request) {
    $request->validate([
        'current_password' => 'required|string',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = $request->user();

    if (!Hash::check($request->input('current_password'), $user->password)) {
        return response()->json(['message' => 'Current password is incorrect'], 422);
    }

    $user->password = $request->input('password');
    $user->save();

    return ['message' => 'Password updated'];
})->middleware('auth:sanctum');

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // === NOTIFICATIONS ===
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::post('notifications/{notification}/mark-read', [NotificationController::class, 'markAsRead']);

    // === BRANDS ===
    Route::get('brands', [BrandController::class, 'index']);
    Route::post('brands', [BrandController::class, 'store']);
    Route::get('brands/current', [BrandController::class, 'current']);
    Route::get('brands/{brand}', [BrandController::class, 'show']);
    Route::put('brands/{brand}', [BrandController::class, 'update']);
    Route::delete('brands/{brand}', [BrandController::class, 'destroy']);
    Route::post('brands/{brand}/set-current', [BrandController::class, 'setCurrent']);
    Route::post('brands/{brand}/complete-onboarding', [BrandController::class, 'completeOnboarding']);
    Route::post('brands/ai/suggestions', [BrandController::class, 'generateSuggestions']);

    // === BRAND AUTOMATION ===
    Route::prefix('brands/{brand}/automation')->group(function () {
        Route::get('stats', [BrandController::class, 'automationStats']);
        Route::post('enable', [BrandController::class, 'enableAutomation']);
        Route::post('disable', [BrandController::class, 'disableAutomation']);
        Route::post('process', [BrandController::class, 'triggerAutomation']);
        Route::post('extend', [BrandController::class, 'extendQueue']);
        Route::put('settings', [BrandController::class, 'updateAutomationSettings']);
    });

    // === CONTENT PLANNING ===
    Route::post('content-plan/generate', [ContentPlanController::class, 'generate']);
    Route::post('content-plan/generate-content', [ContentPlanController::class, 'generateContent']);
    Route::post('content-plan/regenerate-content', [ContentPlanController::class, 'regenerateContent']);

    // === STOCK PHOTOS ===
    Route::get('stock-photos/search', [StockPhotoController::class, 'search']);
    Route::get('stock-photos/featured', [StockPhotoController::class, 'featured']);

    // === BASES ===
    Route::apiResource('bases', BaseController::class)->parameter('bases', 'base');

    // === TABLES ===
    Route::apiResource('bases.tables', TableController::class)->shallow()->parameter('bases', 'base');
    Route::post('tables/{table}/reorder', [TableController::class, 'reorder']);

    // === FIELDS ===
    Route::apiResource('tables.fields', FieldController::class)->shallow();
    Route::post('fields/{field}/reorder', [FieldController::class, 'reorder']);
    Route::post('fields/{field}/choices', [FieldController::class, 'addChoice']);

    // === ROWS ===
    Route::apiResource('tables.rows', RowController::class)->shallow();
    Route::post('tables/{table}/rows/bulk', [RowController::class, 'bulkCreate']);
    Route::delete('tables/{table}/rows/bulk', [RowController::class, 'bulkDelete']);
    Route::post('rows/{row}/reorder', [RowController::class, 'reorder']);

    // === CELLS ===
    Route::put('rows/{row}/cells/{field}', [CellController::class, 'update']);
    Route::put('rows/{row}/cells', [CellController::class, 'bulkUpdate']);

    // === ATTACHMENTS ===
    Route::post('cells/{cell}/attachments', [AttachmentController::class, 'store']);
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy']);
    Route::post('attachments/{attachment}/reorder', [AttachmentController::class, 'reorder']);

    // === TEMPLATES ===
    // User's templates (no base required)
    Route::get('templates', [TemplateController::class, 'index']);
    Route::post('templates', [TemplateController::class, 'store']);
    Route::get('templates/{template}', [TemplateController::class, 'show']);
    Route::put('templates/{template}', [TemplateController::class, 'update']);
    Route::delete('templates/{template}', [TemplateController::class, 'destroy']);
    Route::post('templates/{template}/reorder', [TemplateController::class, 'reorder']);
    Route::post('templates/{template}/duplicate', [TemplateController::class, 'duplicate']);
    Route::post('templates/{template}/thumbnail', [TemplateController::class, 'uploadThumbnail']);
    Route::post('templates/{template}/background', [TemplateController::class, 'uploadBackgroundImage']);
    Route::post('templates/{template}/generate', [TemplateController::class, 'generate']);

    // === AI CHAT ===
    Route::post('templates/{template}/ai/chat', [AiChatController::class, 'chat']);

    // Base-specific templates (for automation)
    Route::get('bases/{base}/templates', [TemplateController::class, 'indexByBase']);

    // === LAYERS ===
    Route::apiResource('templates.layers', LayerController::class)->shallow();
    Route::post('layers/{layer}/reorder', [LayerController::class, 'reorder']);
    Route::put('templates/{template}/layers', [LayerController::class, 'bulkUpdate']);

    // === GENERATED IMAGES ===
    Route::get('templates/{template}/images', [GeneratedImageController::class, 'index']);
    Route::post('templates/{template}/images', [GeneratedImageController::class, 'store']);
    Route::get('generated-images/{generatedImage}', [GeneratedImageController::class, 'show']);
    Route::delete('generated-images/{generatedImage}', [GeneratedImageController::class, 'destroy']);
    Route::delete('templates/{template}/images/bulk', [GeneratedImageController::class, 'bulkDestroy']);

    // === TEMPLATE FONTS ===
    Route::get('templates/{template}/fonts', [TemplateFontController::class, 'index']);
    Route::post('templates/{template}/fonts', [TemplateFontController::class, 'store']);
    Route::delete('fonts/{font}', [TemplateFontController::class, 'destroy']);

    // === TEMPLATE LIBRARY (available to all users) ===
    Route::get('library/templates', [TemplateLibraryController::class, 'index']);
    Route::post('library/templates/{template}/copy', [TemplateLibraryController::class, 'copy']);
    Route::post('library/templates/{template}/apply', [TemplateLibraryController::class, 'applyToCurrent']);

    // === TEMPLATE LIBRARY ADMIN ===
    Route::middleware('admin')->group(function () {
        Route::post('templates/{template}/add-to-library', [TemplateLibraryController::class, 'addToLibrary']);
        Route::post('templates/{template}/remove-from-library', [TemplateLibraryController::class, 'removeFromLibrary']);
        Route::post('templates/{template}/unlink-from-library', [TemplateLibraryController::class, 'unlinkFromLibrary']);
        Route::delete('library/templates/{template}', [TemplateLibraryController::class, 'destroy']);
    });

    // === SOCIAL POSTS ===
    Route::get('posts', [SocialPostController::class, 'index']);
    Route::get('posts/calendar', [SocialPostController::class, 'calendar']);
    Route::get('posts/pending-approval', [SocialPostController::class, 'pendingApproval']);
    Route::post('posts', [SocialPostController::class, 'store']);
    Route::post('posts/ai/generate', [PostAiController::class, 'generate']);
    Route::post('posts/batch-approve', [SocialPostController::class, 'batchApprove']);
    Route::post('posts/batch-reject', [SocialPostController::class, 'batchReject']);
    Route::get('posts/{post}', [SocialPostController::class, 'show']);
    Route::put('posts/{post}', [SocialPostController::class, 'update']);
    Route::delete('posts/{post}', [SocialPostController::class, 'destroy']);
    Route::post('posts/{post}/reschedule', [SocialPostController::class, 'reschedule']);
    Route::post('posts/{post}/duplicate', [SocialPostController::class, 'duplicate']);
    Route::post('posts/{post}/request-approval', [SocialPostController::class, 'requestApproval']);
    Route::post('posts/{post}/approve', [SocialPostController::class, 'approve']);
    Route::post('posts/{post}/reject', [SocialPostController::class, 'reject']);
    Route::post('posts/{post}/publish', [SocialPostController::class, 'publish']);

    // === PLATFORM POSTS ===
    Route::put('posts/{post}/platforms/{platform}', [PlatformPostController::class, 'update']);
    Route::post('posts/{post}/platforms/{platform}/sync', [PlatformPostController::class, 'sync']);
    Route::post('posts/{post}/platforms/{platform}/toggle', [PlatformPostController::class, 'toggle']);

    // === POST MEDIA ===
    Route::get('posts/{post}/media', [PostMediaController::class, 'index']);
    Route::post('posts/{post}/media', [PostMediaController::class, 'store']);
    Route::delete('media/{media}', [PostMediaController::class, 'destroy']);
    Route::post('posts/{post}/media/reorder', [PostMediaController::class, 'reorder']);
    Route::get('media/{media}/validate/{platform}', [PostMediaController::class, 'validate']);

    // === APPROVAL TOKENS ===
    Route::get('approval-tokens', [ApprovalTokenController::class, 'index']);
    Route::post('approval-tokens', [ApprovalTokenController::class, 'store']);
    Route::get('approval-tokens/{approvalToken}', [ApprovalTokenController::class, 'show']);
    Route::delete('approval-tokens/{approvalToken}', [ApprovalTokenController::class, 'destroy']);
    Route::post('approval-tokens/{approvalToken}/regenerate', [ApprovalTokenController::class, 'regenerate']);
    Route::get('approval-tokens/{approvalToken}/stats', [ApprovalTokenController::class, 'stats']);
});

// === PUBLIC APPROVAL ROUTES (No Auth) ===
Route::prefix('v1/approve/{token}')->group(function () {
    Route::get('/', [ClientApprovalController::class, 'validate']);
    Route::get('/posts', [ClientApprovalController::class, 'posts']);
    Route::get('/posts/{post}', [ClientApprovalController::class, 'show']);
    Route::post('/posts/{post}/respond', [ClientApprovalController::class, 'respond']);
    Route::get('/history', [ClientApprovalController::class, 'history']);
});

// === WEBHOOKS (No Auth, protected by secret) ===
Route::prefix('v1/webhooks')->group(function () {
    Route::post('/publish-result', [WebhookController::class, 'publishResult'])->name('webhooks.publish-result');
    Route::get('/health', [WebhookController::class, 'health'])->name('webhooks.health');
});
