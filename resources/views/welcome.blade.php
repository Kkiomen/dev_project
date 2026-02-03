<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('landing.meta_title') }}</title>
    <meta name="description" content="{{ __('landing.meta_description') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css'])

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-brand-bg text-gray-300 font-sans antialiased">

{{-- ==================== NAVIGATION ==================== --}}
@include('landing.partials.nav')

{{-- ==================== HERO ==================== --}}
<section class="relative min-h-screen flex items-center justify-center overflow-hidden pt-16">
    {{-- Decorative glow orbs --}}
    <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl animate-pulse-glow"></div>
    <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl animate-pulse-glow" style="animation-delay: 2s;"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 text-center">
        {{-- Badge --}}
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-brand-border bg-brand-surface/50 text-sm text-gray-400 mb-8 animate-fade-in">
            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            {{ __('landing.hero_badge') }}
        </div>

        {{-- Headline --}}
        <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold text-white leading-tight mb-6 animate-fade-in-up">
            {{ __('landing.hero_title_start') }}
            <span class="landing-gradient-text">{{ __('landing.hero_title_gradient') }}</span>
            <br>{{ __('landing.hero_title_end') }}
        </h1>

        {{-- Subtitle --}}
        <p class="text-lg sm:text-xl text-gray-400 max-w-2xl mx-auto mb-10 animate-fade-in-up" style="animation-delay: 0.15s;">
            {{ __('landing.hero_subtitle') }}
        </p>

        {{-- CTA buttons --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 animate-fade-in-up" style="animation-delay: 0.3s;">
            <a href="{{ route('register') }}" class="landing-btn-primary text-base w-full sm:w-auto">
                {{ __('landing.hero_cta_primary') }}
                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>
            <a href="#how-it-works" class="landing-btn-secondary text-base w-full sm:w-auto">
                {{ __('landing.hero_cta_secondary') }}
            </a>
        </div>
    </div>
</section>

{{-- ==================== REPLACES ==================== --}}
<section class="relative py-16 sm:py-20 border-b border-brand-border">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <p class="text-sm uppercase tracking-widest text-gray-500 mb-8 animate-on-scroll">{{ __('landing.replaces_title') }}</p>

        <div class="flex flex-wrap items-center justify-center gap-3 sm:gap-4 animate-on-scroll">
            {{-- Airtable --}}
            <div class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-brand-border bg-brand-surface/40 hover:border-indigo-500/30 transition-colors">
                <svg class="w-5 h-5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                </svg>
                <span class="text-sm text-gray-300 font-medium">{{ __('landing.replaces_airtable') }}</span>
            </div>

            {{-- BannerBear --}}
            <div class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-brand-border bg-brand-surface/40 hover:border-purple-500/30 transition-colors">
                <svg class="w-5 h-5 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="text-sm text-gray-300 font-medium">{{ __('landing.replaces_bannerbear') }}</span>
            </div>

            {{-- Canva --}}
            <div class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-brand-border bg-brand-surface/40 hover:border-pink-500/30 transition-colors">
                <svg class="w-5 h-5 text-pink-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                </svg>
                <span class="text-sm text-gray-300 font-medium">{{ __('landing.replaces_canva') }}</span>
            </div>

            {{-- Buffer --}}
            <div class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-brand-border bg-brand-surface/40 hover:border-cyan-500/30 transition-colors">
                <svg class="w-5 h-5 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm text-gray-300 font-medium">{{ __('landing.replaces_buffer') }}</span>
            </div>

            {{-- Hootsuite --}}
            <div class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-brand-border bg-brand-surface/40 hover:border-emerald-500/30 transition-colors">
                <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="text-sm text-gray-300 font-medium">{{ __('landing.replaces_hootsuite') }}</span>
            </div>

            {{-- Notion --}}
            <div class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-brand-border bg-brand-surface/40 hover:border-gray-400/30 transition-colors">
                <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="text-sm text-gray-300 font-medium">{{ __('landing.replaces_notion') }}</span>
            </div>

            {{-- Zapier --}}
            <div class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-brand-border bg-brand-surface/40 hover:border-amber-500/30 transition-colors">
                <svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <span class="text-sm text-gray-300 font-medium">{{ __('landing.replaces_zapier') }}</span>
            </div>

            {{-- ChatGPT --}}
            <div class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-brand-border bg-brand-surface/40 hover:border-green-500/30 transition-colors">
                <svg class="w-5 h-5 text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                <span class="text-sm text-gray-300 font-medium">{{ __('landing.replaces_chatgpt') }}</span>
            </div>
        </div>
    </div>
</section>

{{-- ==================== FEATURES ==================== --}}
<section id="features" class="relative py-24 sm:py-32">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Section header --}}
        <div class="text-center mb-16 animate-on-scroll">
            <span class="inline-block px-4 py-1.5 rounded-full border border-brand-border bg-brand-surface/50 text-sm text-indigo-400 mb-4">{{ __('landing.features_badge') }}</span>
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold text-white mb-4">{{ __('landing.features_title') }}</h2>
            <p class="text-gray-400 max-w-2xl mx-auto text-lg">{{ __('landing.features_subtitle') }}</p>
        </div>

        {{-- Feature grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {{-- Feature 1: AI Content --}}
            <div class="landing-card p-6 animate-on-scroll">
                <div class="w-12 h-12 rounded-lg bg-indigo-500/10 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ __('landing.feature_1_title') }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ __('landing.feature_1_desc') }}</p>
            </div>

            {{-- Feature 2: Scheduling --}}
            <div class="landing-card p-6 animate-on-scroll">
                <div class="w-12 h-12 rounded-lg bg-purple-500/10 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ __('landing.feature_2_title') }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ __('landing.feature_2_desc') }}</p>
            </div>

            {{-- Feature 3: Visual Editor --}}
            <div class="landing-card p-6 animate-on-scroll">
                <div class="w-12 h-12 rounded-lg bg-pink-500/10 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ __('landing.feature_3_title') }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ __('landing.feature_3_desc') }}</p>
            </div>

            {{-- Feature 4: Calendar --}}
            <div class="landing-card p-6 animate-on-scroll">
                <div class="w-12 h-12 rounded-lg bg-cyan-500/10 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ __('landing.feature_4_title') }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ __('landing.feature_4_desc') }}</p>
            </div>

            {{-- Feature 5: Multi-Brand --}}
            <div class="landing-card p-6 animate-on-scroll">
                <div class="w-12 h-12 rounded-lg bg-emerald-500/10 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ __('landing.feature_5_title') }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ __('landing.feature_5_desc') }}</p>
            </div>

            {{-- Feature 6: Approvals --}}
            <div class="landing-card p-6 animate-on-scroll">
                <div class="w-12 h-12 rounded-lg bg-amber-500/10 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ __('landing.feature_6_title') }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ __('landing.feature_6_desc') }}</p>
            </div>
        </div>
    </div>
</section>

{{-- ==================== HOW IT WORKS ==================== --}}
<section id="how-it-works" class="relative py-24 sm:py-32">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Section header --}}
        <div class="text-center mb-16 animate-on-scroll">
            <span class="inline-block px-4 py-1.5 rounded-full border border-brand-border bg-brand-surface/50 text-sm text-indigo-400 mb-4">{{ __('landing.how_badge') }}</span>
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold text-white mb-4">{{ __('landing.how_title') }}</h2>
            <p class="text-gray-400 max-w-2xl mx-auto text-lg">{{ __('landing.how_subtitle') }}</p>
        </div>

        {{-- Steps --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            {{-- Step 1 --}}
            <div class="relative animate-on-scroll">
                <div class="text-6xl font-bold text-indigo-500/10 mb-4">01</div>
                <h3 class="text-xl font-semibold text-white mb-3">{{ __('landing.how_step_1_title') }}</h3>
                <p class="text-gray-400 leading-relaxed">{{ __('landing.how_step_1_desc') }}</p>
            </div>

            {{-- Step 2 --}}
            <div class="relative animate-on-scroll">
                <div class="text-6xl font-bold text-purple-500/10 mb-4">02</div>
                <h3 class="text-xl font-semibold text-white mb-3">{{ __('landing.how_step_2_title') }}</h3>
                <p class="text-gray-400 leading-relaxed">{{ __('landing.how_step_2_desc') }}</p>
            </div>

            {{-- Step 3 --}}
            <div class="relative animate-on-scroll">
                <div class="text-6xl font-bold text-pink-500/10 mb-4">03</div>
                <h3 class="text-xl font-semibold text-white mb-3">{{ __('landing.how_step_3_title') }}</h3>
                <p class="text-gray-400 leading-relaxed">{{ __('landing.how_step_3_desc') }}</p>
            </div>
        </div>
    </div>
</section>

{{-- ==================== BENEFITS ==================== --}}
<section id="benefits" class="relative py-24 sm:py-32">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Section header --}}
        <div class="text-center mb-16 animate-on-scroll">
            <span class="inline-block px-4 py-1.5 rounded-full border border-brand-border bg-brand-surface/50 text-sm text-indigo-400 mb-4">{{ __('landing.benefits_badge') }}</span>
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold text-white mb-4">{{ __('landing.benefits_title') }}</h2>
            <p class="text-gray-400 max-w-2xl mx-auto text-lg">{{ __('landing.benefits_subtitle') }}</p>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-16">
            <div class="text-center p-8 landing-card animate-on-scroll">
                <div class="text-4xl sm:text-5xl font-bold landing-gradient-text mb-2">{{ __('landing.benefits_stat_1_value') }}</div>
                <p class="text-gray-400">{{ __('landing.benefits_stat_1_label') }}</p>
            </div>
            <div class="text-center p-8 landing-card animate-on-scroll">
                <div class="text-4xl sm:text-5xl font-bold landing-gradient-text mb-2">{{ __('landing.benefits_stat_2_value') }}</div>
                <p class="text-gray-400">{{ __('landing.benefits_stat_2_label') }}</p>
            </div>
            <div class="text-center p-8 landing-card animate-on-scroll">
                <div class="text-4xl sm:text-5xl font-bold landing-gradient-text mb-2">{{ __('landing.benefits_stat_3_value') }}</div>
                <p class="text-gray-400">{{ __('landing.benefits_stat_3_label') }}</p>
            </div>
        </div>

        {{-- Benefit cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="landing-card p-6 animate-on-scroll">
                <div class="w-10 h-10 rounded-lg bg-indigo-500/10 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ __('landing.benefit_1_title') }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ __('landing.benefit_1_desc') }}</p>
            </div>

            <div class="landing-card p-6 animate-on-scroll">
                <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ __('landing.benefit_2_title') }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ __('landing.benefit_2_desc') }}</p>
            </div>

            <div class="landing-card p-6 animate-on-scroll">
                <div class="w-10 h-10 rounded-lg bg-pink-500/10 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ __('landing.benefit_3_title') }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ __('landing.benefit_3_desc') }}</p>
            </div>

            <div class="landing-card p-6 animate-on-scroll">
                <div class="w-10 h-10 rounded-lg bg-cyan-500/10 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ __('landing.benefit_4_title') }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ __('landing.benefit_4_desc') }}</p>
            </div>

            <div class="landing-card p-6 animate-on-scroll">
                <div class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ __('landing.benefit_5_title') }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ __('landing.benefit_5_desc') }}</p>
            </div>

            <div class="landing-card p-6 animate-on-scroll">
                <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ __('landing.benefit_6_title') }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ __('landing.benefit_6_desc') }}</p>
            </div>
        </div>
    </div>
</section>

{{-- ==================== CTA ==================== --}}
<section class="relative py-24 sm:py-32 overflow-hidden">
    {{-- Glow background --}}
    <div class="absolute inset-0 flex items-center justify-center">
        <div class="w-[600px] h-[600px] bg-indigo-500/10 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 max-w-3xl mx-auto px-4 sm:px-6 text-center animate-on-scroll">
        <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold text-white mb-6">{{ __('landing.cta_title') }}</h2>
        <p class="text-lg text-gray-400 mb-10 max-w-xl mx-auto">{{ __('landing.cta_subtitle') }}</p>
        <a href="{{ route('register') }}" class="landing-btn-primary text-lg">
            {{ __('landing.cta_button') }}
            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
            </svg>
        </a>
    </div>
</section>

{{-- ==================== FOOTER ==================== --}}
@include('landing.partials.footer')

{{-- ==================== SCROLL ANIMATIONS ==================== --}}
@include('landing.partials.scroll-animations')

</body>
</html>
