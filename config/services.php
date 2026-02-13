<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'rayso' => [
        'url' => env('RAYSO_URL', 'http://rayso:3333'),
        'timeout' => env('RAYSO_TIMEOUT', 30),
    ],

    'image_analysis' => [
        'url' => env('IMAGE_ANALYSIS_URL', 'http://image-analysis:3334'),
        'timeout' => env('IMAGE_ANALYSIS_TIMEOUT', 30),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 4096),
    ],

    'pexels' => [
        'api_key' => env('PEXELS_API_KEY'),
        'base_url' => 'https://api.pexels.com/v1',
    ],

    'unsplash' => [
        'access_key' => env('UNSPLASH_ACCESS_KEY'),
        'base_url' => 'https://api.unsplash.com',
    ],

    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL'),
        'webhook_secret' => env('N8N_WEBHOOK_SECRET'),
    ],

    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect_url' => env('FACEBOOK_OAUTH_REDIRECT_URL'),
        'graph_version' => 'v18.0',
    ],

    'psd_parser' => [
        'url' => env('PSD_PARSER_URL', 'http://psd-parser:3335'),
        'timeout' => env('PSD_PARSER_TIMEOUT', 120),
    ],

    'template_renderer' => [
        'url' => env('TEMPLATE_RENDERER_URL', 'http://template-renderer:3336'),
        'timeout' => env('TEMPLATE_RENDERER_TIMEOUT', 60),
        'laravel_url' => env('TEMPLATE_RENDERER_LARAVEL_URL', 'http://laravel.test'),
    ],

    'dev_bot' => [
        'url' => env('DEV_BOT_URL', 'http://dev-bot:3337/trigger'),
        'timeout' => env('DEV_BOT_TIMEOUT', 30),
    ],

    'transcriber' => [
        'url' => env('TRANSCRIBER_URL', 'http://transcriber:3340'),
        'timeout' => env('TRANSCRIBER_TIMEOUT', 300),
    ],

    'video_editor' => [
        'url' => env('VIDEO_EDITOR_URL', 'http://video-editor:3341'),
        'timeout' => env('VIDEO_EDITOR_TIMEOUT', 600),
    ],

];
