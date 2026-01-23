<?php

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Controllers\Api\V1\TableController;
use App\Http\Controllers\Api\V1\FieldController;
use App\Http\Controllers\Api\V1\RowController;
use App\Http\Controllers\Api\V1\CellController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\LayerController;
use App\Http\Controllers\Api\V1\GeneratedImageController;
use App\Http\Controllers\Api\V1\TemplateFontController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
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
});
