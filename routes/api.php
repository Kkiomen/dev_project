<?php

use App\Http\Controllers\Admin\DevTaskController;
use App\Http\Controllers\Admin\DevTaskLogController;
use App\Http\Controllers\Admin\DevTaskSubtaskController;
use App\Http\Controllers\Admin\DevTaskAttachmentController;
use App\Http\Controllers\Admin\DevTaskTimeEntryController;
use App\Http\Controllers\Admin\DevTaskFilterController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\AiChatController;
use App\Http\Controllers\Api\V1\ApiTokenController;
use App\Http\Controllers\Api\V1\BoardCardController;
use App\Http\Controllers\Api\V1\BoardColumnController;
use App\Http\Controllers\Api\V1\BoardController;
use App\Http\Controllers\Api\V1\CalendarEventController;
use App\Http\Controllers\Api\V1\DebugRenderController;
use App\Http\Controllers\Api\V1\ApprovalTokenController;
use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\ClientApprovalController;
use App\Http\Controllers\Api\V1\ContentPlanController;
use App\Http\Controllers\Api\V1\PostAiController;
use App\Http\Controllers\Api\V1\PsdFileController;
use App\Http\Controllers\Api\V1\PsdImportController;
use App\Http\Controllers\Api\V1\StockPhotoController;
use App\Http\Controllers\Api\V1\TableController;
use App\Http\Controllers\Api\V1\FieldController;
use App\Http\Controllers\Api\V1\RowController;
use App\Http\Controllers\Api\V1\CellController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\PlatformPostController;
use App\Http\Controllers\Api\V1\PostMediaController;
use App\Http\Controllers\Api\V1\PostAutomationController;
use App\Http\Controllers\Api\V1\SocialPostController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\TemplateLibraryController;
use App\Http\Controllers\Api\V1\TemplatePreviewController;
use App\Http\Controllers\Api\V1\LayerController;
use App\Http\Controllers\Api\V1\GeneratedImageController;
use App\Http\Controllers\Api\V1\TemplateFontController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PlatformCredentialController;
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

// === USER ROUTES ===
$userRoutes = function () {
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
            'onboarding_completed' => $user->onboarding_completed,
            'brands_count' => $user->brands()->active()->count(),
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    });

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
    });

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
    });

    Route::post('/user/onboarding', function (Request $request) {
        $request->validate([
            'role' => 'required|string|max:100',
            'purpose' => 'required|array|min:1',
            'purpose.*' => 'string|max:100',
            'referral_source' => 'required|string|max:100',
        ]);

        $user = $request->user();
        $user->onboarding_data = [
            'role' => $request->input('role'),
            'purpose' => $request->input('purpose'),
            'referral_source' => $request->input('referral_source'),
        ];
        $user->save();

        return ['message' => 'Onboarding data saved'];
    });

    Route::post('/user/onboarding/complete', function (Request $request) {
        $user = $request->user();
        $user->onboarding_completed = true;
        $user->save();

        return ['message' => 'Onboarding completed', 'onboarding_completed' => true];
    });

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
    });
};

// User routes for external API consumers (Sanctum tokens)
Route::middleware('auth:sanctum')->group($userRoutes);

// User routes for panel SPA (session auth)
Route::prefix('panel')->middleware('auth')->group($userRoutes);

// === ADMIN ROUTES ===
$adminRoutes = function () {
    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('users/{user}', [AdminUserController::class, 'show']);
    Route::put('users/{user}', [AdminUserController::class, 'update']);
    Route::put('users/{user}/password', [AdminUserController::class, 'updatePassword']);
    Route::delete('users/{user}', [AdminUserController::class, 'destroy']);
    Route::get('users/{user}/notifications', [AdminUserController::class, 'notifications']);

    // === DEV TASKS (Mini-Jira) ===
    Route::prefix('dev-tasks')->group(function () {
        Route::get('projects', [DevTaskController::class, 'projects']);
        Route::post('projects', [DevTaskController::class, 'createProject']);

        // Saved filters
        Route::get('filters/saved', [DevTaskFilterController::class, 'index']);
        Route::post('filters/saved', [DevTaskFilterController::class, 'store']);
        Route::put('filters/saved/{filter}', [DevTaskFilterController::class, 'update']);
        Route::delete('filters/saved/{filter}', [DevTaskFilterController::class, 'destroy']);
        Route::post('filters/saved/reorder', [DevTaskFilterController::class, 'reorder']);
        Route::post('filters/saved/{filter}/default', [DevTaskFilterController::class, 'setDefault']);

        // Active time entry (user-level)
        Route::get('time-entries/active', [DevTaskTimeEntryController::class, 'active']);

        // Tasks CRUD
        Route::get('/', [DevTaskController::class, 'index']);
        Route::post('/', [DevTaskController::class, 'store']);
        Route::get('/{task}', [DevTaskController::class, 'show']);
        Route::put('/{task}', [DevTaskController::class, 'update']);
        Route::delete('/{task}', [DevTaskController::class, 'destroy']);
        Route::put('/{task}/move', [DevTaskController::class, 'move']);
        Route::post('/{task}/reorder', [DevTaskController::class, 'reorder']);
        Route::post('/{task}/trigger-bot', [DevTaskController::class, 'triggerBot']);
        Route::post('/{task}/generate-plan', [DevTaskController::class, 'generatePlan']);

        // Logs
        Route::get('/{task}/logs', [DevTaskLogController::class, 'index']);
        Route::post('/{task}/logs', [DevTaskLogController::class, 'store']);

        // Subtasks
        Route::get('/{task}/subtasks', [DevTaskSubtaskController::class, 'index']);
        Route::post('/{task}/subtasks', [DevTaskSubtaskController::class, 'store']);
        Route::put('/{task}/subtasks/{subtask}', [DevTaskSubtaskController::class, 'update']);
        Route::patch('/{task}/subtasks/{subtask}/toggle', [DevTaskSubtaskController::class, 'toggle']);
        Route::delete('/{task}/subtasks/{subtask}', [DevTaskSubtaskController::class, 'destroy']);
        Route::post('/{task}/subtasks/reorder', [DevTaskSubtaskController::class, 'reorder']);

        // Attachments
        Route::get('/{task}/attachments', [DevTaskAttachmentController::class, 'index']);
        Route::post('/{task}/attachments', [DevTaskAttachmentController::class, 'store']);
        Route::delete('/{task}/attachments/{attachment}', [DevTaskAttachmentController::class, 'destroy']);
        Route::post('/{task}/attachments/reorder', [DevTaskAttachmentController::class, 'reorder']);

        // Time entries
        Route::get('/{task}/time-entries', [DevTaskTimeEntryController::class, 'index']);
        Route::post('/{task}/time-entries/start', [DevTaskTimeEntryController::class, 'start']);
        Route::post('/{task}/time-entries/{entry}/stop', [DevTaskTimeEntryController::class, 'stop']);
        Route::put('/{task}/time-entries/{entry}', [DevTaskTimeEntryController::class, 'update']);
        Route::delete('/{task}/time-entries/{entry}', [DevTaskTimeEntryController::class, 'destroy']);
        Route::get('/{task}/time-entries/stats', [DevTaskTimeEntryController::class, 'stats']);
    });
};

// Admin routes for external API consumers (Sanctum tokens)
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group($adminRoutes);

// Admin routes for panel SPA (session auth)
Route::prefix('panel/admin')->middleware(['auth', 'admin'])->group($adminRoutes);

// === V1 ROUTES ===
$v1Routes = function () {

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

    // === PLATFORM CREDENTIALS (Facebook/Instagram OAuth) ===
    Route::prefix('brands/{brand}/platforms')->group(function () {
        Route::get('/', [PlatformCredentialController::class, 'index']);
        Route::get('{platform}/auth-url', [PlatformCredentialController::class, 'authUrl']);
        Route::get('facebook/pages', [PlatformCredentialController::class, 'getPages']);
        Route::post('facebook/select-page', [PlatformCredentialController::class, 'selectPage']);
        Route::post('{platform}/verify', [PlatformCredentialController::class, 'verify']);
        Route::delete('{platform}', [PlatformCredentialController::class, 'disconnect']);
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

    // === TEMPLATE PREVIEW ===
    Route::post('library/templates/preview', [TemplatePreviewController::class, 'preview']);
    Route::get('library/templates/preview/health', [TemplatePreviewController::class, 'health']);
    Route::get('library/templates/semantic-tags', [TemplatePreviewController::class, 'semanticTags']);

    // === TEMPLATE LIBRARY ADMIN ===
    Route::middleware('admin')->group(function () {
        Route::post('templates/{template}/add-to-library', [TemplateLibraryController::class, 'addToLibrary']);
        Route::post('templates/{template}/remove-from-library', [TemplateLibraryController::class, 'removeFromLibrary']);
        Route::post('templates/{template}/unlink-from-library', [TemplateLibraryController::class, 'unlinkFromLibrary']);
        Route::delete('library/templates/{template}', [TemplateLibraryController::class, 'destroy']);

        // Create template from group layer
        Route::post('layers/{layer}/create-template', [LayerController::class, 'createTemplateFromGroup']);

        // === PSD IMPORT ===
        Route::prefix('psd')->group(function () {
            Route::post('import', [PsdImportController::class, 'import']);
            Route::post('analyze', [PsdImportController::class, 'analyze']);
            Route::get('health', [PsdImportController::class, 'health']);
        });

        // === DEBUG RENDER (for AI/Cursor testing) ===
        Route::prefix('debug')->group(function () {
            Route::post('render', [DebugRenderController::class, 'render']);
            Route::post('render-psd', [DebugRenderController::class, 'renderPsd']);
            Route::post('psd-original', [DebugRenderController::class, 'psdOriginal']);
            Route::post('compare', [DebugRenderController::class, 'compare']);
            Route::post('simulate-import', [DebugRenderController::class, 'simulateImport']);
            // Vue-based render endpoints (single source of truth)
            Route::post('render-vue', [DebugRenderController::class, 'renderVue']);
            Route::post('render-psd-vue', [DebugRenderController::class, 'renderPsdVue']);
            Route::post('compare-all', [DebugRenderController::class, 'compareAll']);
        });
    });

    // === PSD FILES (Editor) ===
    Route::prefix('psd-files')->group(function () {
        Route::get('/', [PsdFileController::class, 'index']);
        Route::get('{name}', [PsdFileController::class, 'show'])->where('name', '.*\.psd');
        Route::put('{name}', [PsdFileController::class, 'update'])->where('name', '.*\.psd');
        Route::post('{name}/parse', [PsdFileController::class, 'parse'])->where('name', '.*\.psd');
        Route::get('{name}/tags', [PsdFileController::class, 'getTags'])->where('name', '.*\.psd');
        Route::put('{name}/tags', [PsdFileController::class, 'saveTags'])->where('name', '.*\.psd');
        Route::post('{name}/import', [PsdFileController::class, 'import'])->where('name', '.*\.psd');
        Route::post('{name}/preview', [PsdFileController::class, 'preview'])->where('name', '.*\.psd');
        Route::post('{name}/preview-all', [PsdFileController::class, 'previewAllVariants'])->where('name', '.*\.psd');
    });

    // === POST AUTOMATION ===
    Route::get('posts/automation', [PostAutomationController::class, 'index']);
    Route::post('posts/{post}/generate-text', [PostAutomationController::class, 'generateText']);
    Route::post('posts/{post}/generate-image-prompt', [PostAutomationController::class, 'generateImagePrompt']);
    Route::post('posts/{post}/webhook-publish', [PostAutomationController::class, 'webhookPublish']);
    Route::post('posts/bulk-generate-text', [PostAutomationController::class, 'bulkGenerateText']);
    Route::post('posts/bulk-generate-image-prompt', [PostAutomationController::class, 'bulkGenerateImagePrompt']);

    // === SOCIAL POSTS ===
    Route::get('posts', [SocialPostController::class, 'index']);
    Route::get('posts/calendar', [SocialPostController::class, 'calendar']);
    Route::get('posts/pending-approval', [SocialPostController::class, 'pendingApproval']);
    Route::get('posts/verified', [SocialPostController::class, 'verified']);
    Route::post('posts', [SocialPostController::class, 'store']);
    Route::post('posts/ai/generate', [PostAiController::class, 'generate']);
    Route::post('posts/ai/modify', [PostAiController::class, 'modify']);
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
    Route::post('posts/{post}/mark-published', [SocialPostController::class, 'markPublished']);
    Route::post('posts/{post}/mark-failed', [SocialPostController::class, 'markFailed']);

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

    // === API TOKENS (Personal Access Tokens) ===
    Route::get('api-tokens', [ApiTokenController::class, 'index']);
    Route::post('api-tokens', [ApiTokenController::class, 'store']);
    Route::delete('api-tokens/{tokenId}', [ApiTokenController::class, 'destroy']);

    // === APPROVAL TOKENS ===
    Route::get('approval-tokens', [ApprovalTokenController::class, 'index']);
    Route::post('approval-tokens', [ApprovalTokenController::class, 'store']);
    Route::get('approval-tokens/{approvalToken}', [ApprovalTokenController::class, 'show']);
    Route::delete('approval-tokens/{approvalToken}', [ApprovalTokenController::class, 'destroy']);
    Route::post('approval-tokens/{approvalToken}/regenerate', [ApprovalTokenController::class, 'regenerate']);
    Route::get('approval-tokens/{approvalToken}/stats', [ApprovalTokenController::class, 'stats']);

    // === BOARDS (Kanban) ===
    Route::get('boards', [BoardController::class, 'index']);
    Route::post('boards', [BoardController::class, 'store']);
    Route::get('boards/{board}', [BoardController::class, 'show']);
    Route::put('boards/{board}', [BoardController::class, 'update']);
    Route::delete('boards/{board}', [BoardController::class, 'destroy']);

    // Board Columns
    Route::post('boards/{board}/columns', [BoardColumnController::class, 'store']);
    Route::put('columns/{column}', [BoardColumnController::class, 'update']);
    Route::delete('columns/{column}', [BoardColumnController::class, 'destroy']);
    Route::post('columns/{column}/reorder', [BoardColumnController::class, 'reorder']);

    // Board Cards
    Route::post('columns/{column}/cards', [BoardCardController::class, 'store']);
    Route::put('cards/{card}', [BoardCardController::class, 'update']);
    Route::delete('cards/{card}', [BoardCardController::class, 'destroy']);
    Route::put('cards/{card}/move', [BoardCardController::class, 'move']);
    Route::post('cards/{card}/reorder', [BoardCardController::class, 'reorder']);

    // === CALENDAR EVENTS ===
    Route::prefix('events')->group(function () {
        Route::get('/', [CalendarEventController::class, 'index']);
        Route::get('/calendar', [CalendarEventController::class, 'calendar']);
        Route::post('/', [CalendarEventController::class, 'store']);
        Route::get('/{event}', [CalendarEventController::class, 'show']);
        Route::put('/{event}', [CalendarEventController::class, 'update']);
        Route::delete('/{event}', [CalendarEventController::class, 'destroy']);
        Route::post('/{event}/reschedule', [CalendarEventController::class, 'reschedule']);
    });
};

// V1 routes for external API consumers (Sanctum tokens)
Route::prefix('v1')->middleware(['auth:sanctum'])->group($v1Routes);

// V1 routes for panel SPA (session auth)
Route::prefix('panel')->middleware(['auth'])->group($v1Routes);

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
    Route::post('/automation-callback', [WebhookController::class, 'automationCallback'])->name('webhooks.automation-callback');
    Route::get('/health', [WebhookController::class, 'health'])->name('webhooks.health');
});

// === RENDER DATA (No Auth, used by template-renderer service) ===
Route::prefix('v1/render-data')->group(function () {
    Route::post('/', [App\Http\Controllers\Api\V1\RenderDataController::class, 'store']);
    Route::get('/{key}', [App\Http\Controllers\Api\V1\RenderDataController::class, 'show']);
});
