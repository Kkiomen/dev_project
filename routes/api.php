<?php

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Controllers\Api\V1\TableController;
use App\Http\Controllers\Api\V1\FieldController;
use App\Http\Controllers\Api\V1\RowController;
use App\Http\Controllers\Api\V1\CellController;
use App\Http\Controllers\Api\V1\AttachmentController;
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
});
