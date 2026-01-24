<?php

use App\Http\Controllers\Api\V1\AiChatController;
use App\Http\Controllers\Api\V1\ApprovalTokenController;
use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Controllers\Api\V1\ClientApprovalController;
use App\Http\Controllers\Api\V1\PostAiController;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    $user = $request->user();
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
    ]);

    $user = $request->user();
    $user->settings = array_merge($user->settings ?? [], $request->input('settings'));
    $user->save();

    return ['message' => 'Settings updated', 'settings' => $user->settings];
})->middleware('auth:sanctum');

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

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
    Route::post('posts', [SocialPostController::class, 'store']);
    Route::post('posts/ai/generate', [PostAiController::class, 'generate']);
    Route::get('posts/{post}', [SocialPostController::class, 'show']);
    Route::put('posts/{post}', [SocialPostController::class, 'update']);
    Route::delete('posts/{post}', [SocialPostController::class, 'destroy']);
    Route::post('posts/{post}/reschedule', [SocialPostController::class, 'reschedule']);
    Route::post('posts/{post}/duplicate', [SocialPostController::class, 'duplicate']);
    Route::post('posts/{post}/request-approval', [SocialPostController::class, 'requestApproval']);
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
