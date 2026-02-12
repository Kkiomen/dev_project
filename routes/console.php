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

// Scan for new PSD files to import every 2 minutes
Schedule::command('psd:scan')
    ->everyTwoMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Fetch RSS feeds and clean up old articles every 30 minutes
Schedule::command('rss:fetch --cleanup')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/*
|--------------------------------------------------------------------------
| SM Manager Automation
|--------------------------------------------------------------------------
|
| Social Media Manager scheduled tasks
|
*/

// Publish due SM scheduled posts every minute
Schedule::command('sm:publish-due')
    ->everyMinute()
    ->withoutOverlapping();

// Detect crises every 15 minutes
Schedule::command('sm:detect-crisis')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Collect platform metrics every 6 hours
Schedule::command('sm:collect-metrics')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground();

// Score recent posts every 6 hours (offset by 1 hour from metrics)
Schedule::command('sm:score-posts')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();

// Generate weekly reports on Monday morning
Schedule::command('sm:weekly-report')
    ->weeklyOn(1, '06:00')
    ->withoutOverlapping()
    ->runInBackground();

// Generate monthly content plans on the 28th
Schedule::command('sm:generate-plans')
    ->monthlyOn(28, '03:00')
    ->withoutOverlapping()
    ->runInBackground();
