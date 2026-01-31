<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Content automation scheduling
|
*/

// Process automation hourly - fills queue and generates content
Schedule::command('automation:process')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Check for posts to publish every minute
Schedule::command('posts:publish-due')
    ->everyMinute()
    ->withoutOverlapping();

// Initialize week tracking on Monday at midnight
Schedule::command('automation:init-week')
    ->weeklyOn(1, '00:00')
    ->withoutOverlapping();

// Clean up old template preview files every hour
Schedule::command('previews:cleanup')
    ->hourly()
    ->withoutOverlapping();
