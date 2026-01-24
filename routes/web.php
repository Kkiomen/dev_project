<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\BaseController;
use App\Http\Controllers\Web\TableController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Vue SPA routes (authenticated)
Route::middleware(['auth', 'verified'])->group(function () {
    // SPA catch-all for Vue Router
    Route::get('/dashboard', fn () => view('spa'))->name('dashboard');
    Route::get('/bases/{any}', fn () => view('spa'))->where('any', '.*');
    Route::get('/tables/{any}', fn () => view('spa'))->where('any', '.*');
    Route::get('/templates/{any?}', fn () => view('spa'))->where('any', '.*')->name('templates');
    Route::get('/docs/{any?}', fn () => view('spa'))->where('any', '.*')->name('docs');

    // Calendar & Social Posts
    Route::get('/calendar', fn () => view('spa'))->name('calendar');
    Route::get('/posts/{any?}', fn () => view('spa'))->where('any', '.*')->name('posts');
    Route::get('/approval-tokens', fn () => view('spa'))->name('approval-tokens');

    // Approval Dashboard
    Route::get('/approval-dashboard', fn () => view('spa'))->name('approval-dashboard');

    // Brands management
    Route::get('/brands/{any?}', fn () => view('spa'))->where('any', '.*')->name('brands');

    // Settings
    Route::get('/settings', fn () => view('spa'))->name('settings');
});

// Public approval route (no auth required)
Route::get('/approve/{token}', fn () => view('spa'))->name('client-approval');

// Legacy Blade routes (keep for backward compatibility during migration)
Route::middleware(['auth', 'verified'])->prefix('legacy')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('legacy.dashboard');
    Route::get('/bases/{base}', [BaseController::class, 'show'])->name('legacy.bases.show');
    Route::get('/tables/{table}', [TableController::class, 'show'])->name('legacy.tables.show');
    Route::get('/tables/{table}/kanban', [TableController::class, 'kanban'])->name('legacy.tables.kanban');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
