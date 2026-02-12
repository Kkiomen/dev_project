<?php

use App\Http\Controllers\Api\V1\PlatformCredentialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\BaseController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\TableController;
use App\Http\Controllers\Web\TestController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Register broadcasting authentication routes
Broadcast::routes(['middleware' => ['web', 'auth']]);

Route::get('/', function () {
    return view('welcome');
});

Route::get('/features', function () {
    return view('landing.features');
})->name('features');

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// Onboarding route (auth required, email verification NOT required)
Route::middleware(['auth'])->group(function () {
    Route::get('/onboarding', fn () => view('spa'))->name('onboarding');
});

// Vue SPA routes (authenticated)
Route::middleware(['auth', 'verified'])->group(function () {
    // SPA catch-all for Vue Router
    Route::get('/dashboard', fn () => view('spa'))->name('dashboard');
    Route::get('/data', fn () => view('spa'))->name('data');
    Route::get('/bases', fn () => view('spa'))->name('bases');
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

    // Boards (Kanban)
    Route::get('/boards/{any?}', fn () => view('spa'))->where('any', '.*')->name('boards');

    // Brands management
    Route::get('/brands/{any?}', fn () => view('spa'))->where('any', '.*')->name('brands');

    // Settings
    Route::get('/settings', fn () => view('spa'))->name('settings');

    // RSS Feeds
    Route::get('/rss-feeds/{any?}', fn () => view('spa'))->where('any', '.*')->name('rss-feeds');

    // AI Social Media Manager
    Route::get('/app/manager/{any?}', fn () => view('spa'))->where('any', '.*')->name('manager');

    // Admin panel
    Route::get('/admin/{any?}', fn () => view('spa'))->where('any', '.*')->name('admin')->middleware('admin');

    // PSD Editor (admin only)
    Route::get('/psd-editor', fn () => view('spa'))->name('psd-editor')->middleware('admin');
});

// Public approval route (no auth required)
Route::get('/approve/{token}', fn () => view('spa'))->name('client-approval');

// Render preview route (for template-renderer service, no auth required)
Route::get('/render-preview', fn () => view('spa'))->name('render-preview');

// Legacy Blade routes (keep for backward compatibility during migration)
Route::middleware(['auth', 'verified'])->prefix('legacy')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('legacy.dashboard');
    Route::get('/bases/{base}', [BaseController::class, 'show'])->name('legacy.bases.show');
    Route::get('/tables/{table}', [TableController::class, 'show'])->name('legacy.tables.show');
    Route::get('/tables/{table}/kanban', [TableController::class, 'kanban'])->name('legacy.tables.kanban');
});

Route::get('/test', TestController::class)->middleware('auth')->name('test');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// OAuth callbacks (must be web routes for redirects)
Route::middleware('auth')->group(function () {
    Route::get('/auth/facebook/callback', [PlatformCredentialController::class, 'callback'])
        ->name('auth.facebook.callback');
});

require __DIR__.'/auth.php';
