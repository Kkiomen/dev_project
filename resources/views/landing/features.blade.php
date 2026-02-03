<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('features.meta_title') }}</title>
    <meta name="description" content="{{ __('features.meta_description') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css'])

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        @keyframes pulse-slow {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 1; }
        }
        @keyframes blink-cursor {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
        .animate-pulse-slow { animation: pulse-slow 2s ease-in-out infinite; }
        .animate-blink { animation: blink-cursor 1s step-end infinite; }
    </style>
</head>
<body class="bg-brand-bg text-gray-300 font-sans antialiased">

{{-- ==================== NAVIGATION ==================== --}}
@include('landing.partials.nav')

{{-- ==================== PAGE HEADER ==================== --}}
<section class="relative pt-32 pb-16 sm:pt-40 sm:pb-20 overflow-hidden">
    <div class="absolute top-1/3 left-1/4 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl"></div>
    <div class="absolute top-1/3 right-1/4 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 text-center">
        <span class="inline-block px-4 py-1.5 rounded-full border border-brand-border bg-brand-surface/50 text-sm text-indigo-400 mb-6 animate-fade-in">{{ __('features.header_badge') }}</span>
        <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold text-white leading-tight mb-6 animate-fade-in-up">
            {{ __('features.header_title') }}
        </h1>
        <p class="text-lg sm:text-xl text-gray-400 max-w-2xl mx-auto animate-fade-in-up" style="animation-delay: 0.15s;">
            {{ __('features.header_subtitle') }}
        </p>
    </div>
</section>

{{-- ==================== FEATURE 1: AI Content Generation ==================== --}}
<section class="relative py-16 sm:py-24 overflow-hidden">
    <div class="absolute top-1/2 right-0 w-80 h-80 bg-indigo-500/8 rounded-full blur-3xl -translate-y-1/2"></div>

    <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            {{-- Text --}}
            <div class="animate-on-scroll">
                <div class="w-14 h-14 rounded-xl bg-indigo-500/10 flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">{{ __('features.f1_title') }}</h2>
                <p class="text-gray-400 text-lg mb-8 leading-relaxed">{{ __('features.f1_desc') }}</p>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-indigo-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f1_bullet_1') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-indigo-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f1_bullet_2') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-indigo-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f1_bullet_3') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-indigo-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f1_bullet_4') }}</span>
                    </li>
                </ul>
            </div>

            {{-- Mockup: AI Chat Interface --}}
            <div class="animate-on-scroll">
                <div class="landing-card overflow-hidden">
                    {{-- Chat header --}}
                    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-[#1e1e2e]">
                        <div class="w-8 h-8 rounded-lg bg-indigo-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-white">AI Assistant</p>
                            <p class="text-xs text-emerald-400">Online</p>
                        </div>
                        <div class="ml-auto flex gap-1.5">
                            <div class="w-2 h-2 rounded-full bg-gray-600"></div>
                            <div class="w-2 h-2 rounded-full bg-gray-600"></div>
                            <div class="w-2 h-2 rounded-full bg-gray-600"></div>
                        </div>
                    </div>

                    {{-- Chat messages --}}
                    <div class="p-5 space-y-4 min-h-[240px]">
                        {{-- User message --}}
                        <div class="flex justify-end">
                            <div class="bg-indigo-500/20 border border-indigo-500/30 rounded-2xl rounded-tr-md px-4 py-2.5 max-w-[80%]">
                                <p class="text-sm text-gray-200">Write a LinkedIn post about AI in marketing</p>
                            </div>
                        </div>

                        {{-- AI response --}}
                        <div class="flex justify-start">
                            <div class="bg-[#1a1a2e] border border-[#2a2a3e] rounded-2xl rounded-tl-md px-4 py-2.5 max-w-[85%]">
                                <p class="text-sm text-gray-300 leading-relaxed">Here's a LinkedIn post tailored to your brand voice:</p>
                                <div class="mt-2 pl-3 border-l-2 border-indigo-500/40">
                                    <p class="text-sm text-gray-400 italic">The future of marketing isn't about replacing creativity — it's about amplifying it...</p>
                                </div>
                            </div>
                        </div>

                        {{-- Typing indicator --}}
                        <div class="flex justify-start">
                            <div class="bg-[#1a1a2e] border border-[#2a2a3e] rounded-2xl rounded-tl-md px-4 py-3">
                                <div class="flex gap-1.5">
                                    <div class="w-2 h-2 rounded-full bg-indigo-400/60 animate-pulse-slow"></div>
                                    <div class="w-2 h-2 rounded-full bg-indigo-400/60 animate-pulse-slow" style="animation-delay: 0.3s;"></div>
                                    <div class="w-2 h-2 rounded-full bg-indigo-400/60 animate-pulse-slow" style="animation-delay: 0.6s;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Input bar --}}
                    <div class="px-5 pb-4">
                        <div class="flex items-center gap-3 bg-[#0d0d14] border border-[#2a2a3e] rounded-xl px-4 py-3">
                            <p class="text-sm text-gray-500 flex-1">Type your prompt...</p>
                            <div class="w-8 h-8 rounded-lg bg-indigo-500 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ==================== FEATURE 2: Smart Scheduling ==================== --}}
<section class="relative py-16 sm:py-24 bg-brand-surface/30 border-y border-brand-border/50 overflow-hidden">
    <div class="absolute top-1/2 left-0 w-80 h-80 bg-purple-500/8 rounded-full blur-3xl -translate-y-1/2"></div>

    <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            {{-- Mockup: Scheduling Timeline (left on desktop) --}}
            <div class="animate-on-scroll order-2 lg:order-1">
                <div class="landing-card overflow-hidden">
                    {{-- Timeline header --}}
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-[#1e1e2e]">
                        <p class="text-sm font-semibold text-white">{{ __('features.f2_title') }}</p>
                        <span class="text-xs text-gray-500">Today</span>
                    </div>

                    {{-- Timeline items --}}
                    <div class="p-5 space-y-0">
                        {{-- Item 1 - Published --}}
                        <div class="flex gap-4">
                            <div class="flex flex-col items-center">
                                <div class="w-3 h-3 rounded-full bg-emerald-400 ring-4 ring-emerald-400/20 shrink-0"></div>
                                <div class="w-0.5 h-full bg-[#2a2a3e] min-h-[48px]"></div>
                            </div>
                            <div class="pb-6 flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-medium text-gray-300">09:00 AM</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-500/15 text-emerald-400 border border-emerald-500/20">Published</span>
                                </div>
                                <p class="text-sm text-gray-400">LinkedIn — Weekly marketing tips</p>
                                <div class="flex items-center gap-1.5 mt-1.5">
                                    <div class="w-4 h-4 rounded bg-blue-500/20 flex items-center justify-center"><span class="text-[8px] text-blue-400 font-bold">in</span></div>
                                    <span class="text-xs text-gray-500">236 impressions</span>
                                </div>
                            </div>
                        </div>

                        {{-- Item 2 - Scheduled --}}
                        <div class="flex gap-4">
                            <div class="flex flex-col items-center">
                                <div class="w-3 h-3 rounded-full bg-purple-400 ring-4 ring-purple-400/20 shrink-0"></div>
                                <div class="w-0.5 h-full bg-[#2a2a3e] min-h-[48px]"></div>
                            </div>
                            <div class="pb-6 flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-medium text-gray-300">02:30 PM</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-purple-500/15 text-purple-400 border border-purple-500/20">Scheduled</span>
                                </div>
                                <p class="text-sm text-gray-400">Twitter/X — Product update thread</p>
                                <div class="flex items-center gap-1.5 mt-1.5">
                                    <div class="w-4 h-4 rounded bg-gray-500/20 flex items-center justify-center"><span class="text-[10px] text-gray-400 font-bold">X</span></div>
                                    <span class="text-xs text-gray-500">5-part thread</span>
                                </div>
                            </div>
                        </div>

                        {{-- Item 3 - Optimal time --}}
                        <div class="flex gap-4">
                            <div class="flex flex-col items-center">
                                <div class="w-3 h-3 rounded-full bg-amber-400 ring-4 ring-amber-400/20 shrink-0"></div>
                                <div class="w-0.5 h-full bg-[#2a2a3e] min-h-[24px]"></div>
                            </div>
                            <div class="pb-4 flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-medium text-gray-300">06:15 PM</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-500/15 text-amber-400 border border-amber-500/20">Optimal time</span>
                                </div>
                                <p class="text-sm text-gray-400">Instagram — Behind the scenes reel</p>
                                <div class="flex items-center gap-1.5 mt-1.5">
                                    <div class="w-4 h-4 rounded bg-pink-500/20 flex items-center justify-center">
                                        <svg class="w-2.5 h-2.5 text-pink-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0z"/></svg>
                                    </div>
                                    <span class="text-xs text-amber-400/80">AI suggested</span>
                                </div>
                            </div>
                        </div>

                        {{-- Item 4 - Scheduled --}}
                        <div class="flex gap-4">
                            <div class="flex flex-col items-center">
                                <div class="w-3 h-3 rounded-full bg-purple-400/50 ring-4 ring-purple-400/10 shrink-0"></div>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-medium text-gray-500">Tomorrow, 10:00 AM</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-purple-500/10 text-purple-400/60 border border-purple-500/10">Scheduled</span>
                                </div>
                                <p class="text-sm text-gray-500">Facebook — Case study share</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Text (right on desktop) --}}
            <div class="animate-on-scroll order-1 lg:order-2">
                <div class="w-14 h-14 rounded-xl bg-purple-500/10 flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">{{ __('features.f2_title') }}</h2>
                <p class="text-gray-400 text-lg mb-8 leading-relaxed">{{ __('features.f2_desc') }}</p>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-purple-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f2_bullet_1') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-purple-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f2_bullet_2') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-purple-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f2_bullet_3') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-purple-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f2_bullet_4') }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ==================== FEATURE 3: Visual Editor ==================== --}}
<section class="relative py-16 sm:py-24 overflow-hidden">
    <div class="absolute top-1/2 right-0 w-80 h-80 bg-pink-500/8 rounded-full blur-3xl -translate-y-1/2"></div>

    <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            {{-- Text --}}
            <div class="animate-on-scroll">
                <div class="w-14 h-14 rounded-xl bg-pink-500/10 flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">{{ __('features.f3_title') }}</h2>
                <p class="text-gray-400 text-lg mb-8 leading-relaxed">{{ __('features.f3_desc') }}</p>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-pink-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f3_bullet_1') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-pink-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f3_bullet_2') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-pink-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f3_bullet_3') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-pink-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f3_bullet_4') }}</span>
                    </li>
                </ul>
            </div>

            {{-- Mockup: Visual Editor Canvas + Layers Panel --}}
            <div class="animate-on-scroll">
                <div class="landing-card overflow-hidden">
                    {{-- Editor toolbar --}}
                    <div class="flex items-center gap-2 px-4 py-2.5 border-b border-[#1e1e2e]">
                        <div class="flex gap-1.5">
                            <div class="w-3 h-3 rounded-full bg-red-500/60"></div>
                            <div class="w-3 h-3 rounded-full bg-yellow-500/60"></div>
                            <div class="w-3 h-3 rounded-full bg-green-500/60"></div>
                        </div>
                        <div class="flex-1 flex items-center justify-center gap-3">
                            <div class="w-6 h-6 rounded bg-[#1a1a2e] flex items-center justify-center" title="Move">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                            </div>
                            <div class="w-6 h-6 rounded bg-pink-500/20 flex items-center justify-center" title="Text">
                                <span class="text-xs text-pink-400 font-bold">T</span>
                            </div>
                            <div class="w-6 h-6 rounded bg-[#1a1a2e] flex items-center justify-center" title="Shape">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/></svg>
                            </div>
                            <div class="w-6 h-6 rounded bg-[#1a1a2e] flex items-center justify-center" title="Image">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        </div>
                        <span class="text-[10px] text-gray-500">100%</span>
                    </div>

                    <div class="flex">
                        {{-- Canvas area --}}
                        <div class="flex-1 p-4 min-h-[280px] relative bg-[#0a0a12]">
                            {{-- Canvas background (checkerboard-like) --}}
                            <div class="absolute inset-4 border border-dashed border-[#2a2a3e] rounded-lg overflow-hidden">
                                {{-- Header element --}}
                                <div class="mx-4 mt-4 h-6 bg-gradient-to-r from-pink-500/30 to-purple-500/30 rounded"></div>
                                {{-- Text block --}}
                                <div class="mx-4 mt-3 space-y-1.5">
                                    <div class="h-2.5 bg-white/15 rounded w-3/4"></div>
                                    <div class="h-2.5 bg-white/10 rounded w-1/2"></div>
                                </div>
                                {{-- Image placeholder --}}
                                <div class="mx-4 mt-3 h-16 bg-indigo-500/10 rounded border border-indigo-500/20 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-indigo-400/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                {{-- Selected element with handles --}}
                                <div class="mx-4 mt-3 h-8 border-2 border-pink-400 rounded relative bg-pink-500/5">
                                    <span class="absolute inset-0 flex items-center justify-center text-xs text-pink-300">Your Brand Name</span>
                                    <div class="absolute -top-1 -left-1 w-2 h-2 bg-pink-400 rounded-sm"></div>
                                    <div class="absolute -top-1 -right-1 w-2 h-2 bg-pink-400 rounded-sm"></div>
                                    <div class="absolute -bottom-1 -left-1 w-2 h-2 bg-pink-400 rounded-sm"></div>
                                    <div class="absolute -bottom-1 -right-1 w-2 h-2 bg-pink-400 rounded-sm"></div>
                                </div>
                                {{-- CTA button --}}
                                <div class="mx-4 mt-3 w-24 h-7 bg-gradient-to-r from-pink-500/40 to-purple-500/40 rounded flex items-center justify-center">
                                    <span class="text-[10px] text-white/70">Learn More</span>
                                </div>
                            </div>
                        </div>

                        {{-- Layers panel --}}
                        <div class="w-36 sm:w-44 border-l border-[#1e1e2e] bg-[#0d0d16]">
                            <div class="px-3 py-2 border-b border-[#1e1e2e]">
                                <p class="text-[10px] text-gray-500 uppercase tracking-wider font-medium">Layers</p>
                            </div>
                            <div class="py-1">
                                {{-- Layer items --}}
                                <div class="flex items-center gap-2 px-3 py-1.5 bg-pink-500/10 border-l-2 border-pink-400">
                                    <span class="text-[10px] font-bold text-pink-400">T</span>
                                    <span class="text-xs text-gray-300 truncate">Brand Name</span>
                                    <svg class="w-3 h-3 text-gray-500 ml-auto shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-1.5 hover:bg-white/5">
                                    <svg class="w-2.5 h-2.5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14"/></svg>
                                    <span class="text-xs text-gray-400 truncate">Image</span>
                                    <svg class="w-3 h-3 text-gray-500 ml-auto shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-1.5 hover:bg-white/5">
                                    <span class="text-[10px] font-bold text-gray-400">T</span>
                                    <span class="text-xs text-gray-400 truncate">Body Text</span>
                                    <svg class="w-3 h-3 text-gray-500 ml-auto shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-1.5 hover:bg-white/5">
                                    <svg class="w-2.5 h-2.5 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/></svg>
                                    <span class="text-xs text-gray-400 truncate">Header BG</span>
                                    <svg class="w-3 h-3 text-gray-500 ml-auto shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-1.5 hover:bg-white/5">
                                    <svg class="w-2.5 h-2.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/></svg>
                                    <span class="text-xs text-gray-400 truncate">CTA Button</span>
                                    <svg class="w-3 h-3 text-gray-600 ml-auto shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-1.5 hover:bg-white/5 opacity-50">
                                    <svg class="w-2.5 h-2.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/></svg>
                                    <span class="text-xs text-gray-500 truncate">Background</span>
                                    <svg class="w-3 h-3 text-gray-600 ml-auto shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878l4.242 4.242M21 21l-4.879-4.879"/></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ==================== FEATURE 4: Content Calendar ==================== --}}
<section class="relative py-16 sm:py-24 bg-brand-surface/30 border-y border-brand-border/50 overflow-hidden">
    <div class="absolute top-1/2 left-0 w-80 h-80 bg-cyan-500/8 rounded-full blur-3xl -translate-y-1/2"></div>

    <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            {{-- Mockup: Mini Calendar (left on desktop) --}}
            <div class="animate-on-scroll order-2 lg:order-1">
                <div class="landing-card overflow-hidden">
                    {{-- Calendar header --}}
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-[#1e1e2e]">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            <p class="text-sm font-semibold text-white">February 2026</p>
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>
                        <div class="flex gap-1">
                            <span class="px-2 py-0.5 rounded text-[10px] text-gray-400 bg-[#1a1a2e]">Month</span>
                            <span class="px-2 py-0.5 rounded text-[10px] text-cyan-400 bg-cyan-500/15 border border-cyan-500/20">Week</span>
                        </div>
                    </div>

                    {{-- Calendar grid --}}
                    <div class="p-4">
                        {{-- Day names --}}
                        <div class="grid grid-cols-7 gap-1 mb-2">
                            <span class="text-center text-[10px] text-gray-500 font-medium">Mon</span>
                            <span class="text-center text-[10px] text-gray-500 font-medium">Tue</span>
                            <span class="text-center text-[10px] text-gray-500 font-medium">Wed</span>
                            <span class="text-center text-[10px] text-gray-500 font-medium">Thu</span>
                            <span class="text-center text-[10px] text-gray-500 font-medium">Fri</span>
                            <span class="text-center text-[10px] text-gray-500 font-medium">Sat</span>
                            <span class="text-center text-[10px] text-gray-500 font-medium">Sun</span>
                        </div>

                        {{-- Week 1 (prev month days + Feb 1) --}}
                        <div class="grid grid-cols-7 gap-1 mb-1">
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-600">26</span>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-600">27</span>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-600">28</span>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-600">29</span>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-600">30</span>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-600">31</span>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">1</span>
                            </div>
                        </div>

                        {{-- Week 2 --}}
                        <div class="grid grid-cols-7 gap-1 mb-1">
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1 relative">
                                <span class="text-[10px] text-gray-400">2</span>
                                <div class="flex gap-0.5 mt-0.5"><div class="w-1 h-1 rounded-full bg-blue-400"></div><div class="w-1 h-1 rounded-full bg-pink-400"></div></div>
                            </div>
                            <div class="aspect-square rounded-lg bg-cyan-500/10 border border-cyan-500/30 flex flex-col items-center justify-center p-1 ring-2 ring-cyan-400/20">
                                <span class="text-[10px] text-cyan-400 font-bold">3</span>
                                <div class="flex gap-0.5 mt-0.5"><div class="w-1 h-1 rounded-full bg-emerald-400"></div></div>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">4</span>
                                <div class="flex gap-0.5 mt-0.5"><div class="w-1 h-1 rounded-full bg-purple-400"></div></div>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">5</span>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">6</span>
                                <div class="flex gap-0.5 mt-0.5"><div class="w-1 h-1 rounded-full bg-blue-400"></div><div class="w-1 h-1 rounded-full bg-amber-400"></div></div>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">7</span>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">8</span>
                            </div>
                        </div>

                        {{-- Week 3 --}}
                        <div class="grid grid-cols-7 gap-1 mb-1">
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">9</span>
                                <div class="flex gap-0.5 mt-0.5"><div class="w-1 h-1 rounded-full bg-purple-400"></div></div>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">10</span>
                                <div class="flex gap-0.5 mt-0.5"><div class="w-1 h-1 rounded-full bg-blue-400"></div></div>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">11</span>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">12</span>
                                <div class="flex gap-0.5 mt-0.5"><div class="w-1 h-1 rounded-full bg-emerald-400"></div><div class="w-1 h-1 rounded-full bg-pink-400"></div></div>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">13</span>
                                <div class="flex gap-0.5 mt-0.5"><div class="w-1 h-1 rounded-full bg-amber-400"></div></div>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">14</span>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">15</span>
                            </div>
                        </div>

                        {{-- Week 4 --}}
                        <div class="grid grid-cols-7 gap-1">
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">16</span>
                                <div class="flex gap-0.5 mt-0.5"><div class="w-1 h-1 rounded-full bg-blue-400"></div></div>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">17</span>
                                <div class="flex gap-0.5 mt-0.5"><div class="w-1 h-1 rounded-full bg-purple-400"></div><div class="w-1 h-1 rounded-full bg-blue-400"></div></div>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">18</span>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">19</span>
                                <div class="flex gap-0.5 mt-0.5"><div class="w-1 h-1 rounded-full bg-emerald-400"></div></div>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">20</span>
                                <div class="flex gap-0.5 mt-0.5"><div class="w-1 h-1 rounded-full bg-pink-400"></div></div>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">21</span>
                            </div>
                            <div class="aspect-square rounded-lg bg-[#0d0d14] flex flex-col items-center justify-center p-1">
                                <span class="text-[10px] text-gray-400">22</span>
                            </div>
                        </div>
                    </div>

                    {{-- Legend --}}
                    <div class="px-5 pb-4 flex flex-wrap gap-3">
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full bg-blue-400"></div>
                            <span class="text-[10px] text-gray-500">LinkedIn</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full bg-purple-400"></div>
                            <span class="text-[10px] text-gray-500">Twitter/X</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full bg-pink-400"></div>
                            <span class="text-[10px] text-gray-500">Instagram</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full bg-emerald-400"></div>
                            <span class="text-[10px] text-gray-500">Published</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full bg-amber-400"></div>
                            <span class="text-[10px] text-gray-500">Draft</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Text (right on desktop) --}}
            <div class="animate-on-scroll order-1 lg:order-2">
                <div class="w-14 h-14 rounded-xl bg-cyan-500/10 flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">{{ __('features.f4_title') }}</h2>
                <p class="text-gray-400 text-lg mb-8 leading-relaxed">{{ __('features.f4_desc') }}</p>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-cyan-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f4_bullet_1') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-cyan-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f4_bullet_2') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-cyan-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f4_bullet_3') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-cyan-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f4_bullet_4') }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ==================== FEATURE 5: Multi-Brand Management ==================== --}}
<section class="relative py-16 sm:py-24 overflow-hidden">
    <div class="absolute top-1/2 right-0 w-80 h-80 bg-emerald-500/8 rounded-full blur-3xl -translate-y-1/2"></div>

    <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            {{-- Text --}}
            <div class="animate-on-scroll">
                <div class="w-14 h-14 rounded-xl bg-emerald-500/10 flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">{{ __('features.f5_title') }}</h2>
                <p class="text-gray-400 text-lg mb-8 leading-relaxed">{{ __('features.f5_desc') }}</p>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-emerald-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f5_bullet_1') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-emerald-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f5_bullet_2') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-emerald-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f5_bullet_3') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-emerald-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f5_bullet_4') }}</span>
                    </li>
                </ul>
            </div>

            {{-- Mockup: Brand Workspace Cards --}}
            <div class="animate-on-scroll">
                <div class="landing-card overflow-hidden">
                    {{-- Header --}}
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-[#1e1e2e]">
                        <p class="text-sm font-semibold text-white">Workspaces</p>
                        <div class="w-6 h-6 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </div>
                    </div>

                    {{-- Brand cards --}}
                    <div class="p-4 space-y-3">
                        {{-- Active brand --}}
                        <div class="p-4 rounded-xl bg-emerald-500/5 border-2 border-emerald-500/40 relative">
                            <div class="absolute top-3 right-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-500/15 text-emerald-400 border border-emerald-500/20">Active</span>
                            </div>
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500/30 to-cyan-500/30 flex items-center justify-center shrink-0">
                                    <span class="text-sm font-bold text-emerald-300">A</span>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-white">Aisello</p>
                                    <p class="text-xs text-gray-500">SaaS Brand</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-400"></div>
                                    <span class="text-[11px] text-gray-400">24 posts</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 rounded-full bg-purple-400"></div>
                                    <span class="text-[11px] text-gray-400">8 scheduled</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 rounded-full bg-blue-400"></div>
                                    <span class="text-[11px] text-gray-400">3 platforms</span>
                                </div>
                            </div>
                        </div>

                        {{-- Brand 2 --}}
                        <div class="p-4 rounded-xl bg-[#0d0d14] border border-[#2a2a3e] hover:border-gray-600/50 transition-colors">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500/30 to-pink-500/30 flex items-center justify-center shrink-0">
                                    <span class="text-sm font-bold text-purple-300">M</span>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-300">Mike's Blog</p>
                                    <p class="text-xs text-gray-500">Personal Brand</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-400/60"></div>
                                    <span class="text-[11px] text-gray-500">12 posts</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 rounded-full bg-purple-400/60"></div>
                                    <span class="text-[11px] text-gray-500">3 scheduled</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 rounded-full bg-blue-400/60"></div>
                                    <span class="text-[11px] text-gray-500">2 platforms</span>
                                </div>
                            </div>
                        </div>

                        {{-- Brand 3 --}}
                        <div class="p-4 rounded-xl bg-[#0d0d14] border border-[#2a2a3e] hover:border-gray-600/50 transition-colors">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500/30 to-orange-500/30 flex items-center justify-center shrink-0">
                                    <span class="text-sm font-bold text-amber-300">S</span>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-300">Studio XYZ</p>
                                    <p class="text-xs text-gray-500">Client</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-400/60"></div>
                                    <span class="text-[11px] text-gray-500">47 posts</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 rounded-full bg-purple-400/60"></div>
                                    <span class="text-[11px] text-gray-500">12 scheduled</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 rounded-full bg-blue-400/60"></div>
                                    <span class="text-[11px] text-gray-500">4 platforms</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ==================== FEATURE 6: Team Approvals ==================== --}}
<section class="relative py-16 sm:py-24 bg-brand-surface/30 border-y border-brand-border/50 overflow-hidden">
    <div class="absolute top-1/2 left-0 w-80 h-80 bg-amber-500/8 rounded-full blur-3xl -translate-y-1/2"></div>

    <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            {{-- Mockup: Approval Workflow (left on desktop) --}}
            <div class="animate-on-scroll order-2 lg:order-1">
                <div class="landing-card overflow-hidden">
                    {{-- Header --}}
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-[#1e1e2e]">
                        <p class="text-sm font-semibold text-white">Approval Workflow</p>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-500/15 text-amber-400 border border-amber-500/20">In Progress</span>
                    </div>

                    {{-- Workflow steps --}}
                    <div class="p-5">
                        {{-- Step 1 - Done --}}
                        <div class="flex gap-4 mb-0">
                            <div class="flex flex-col items-center">
                                <div class="w-10 h-10 rounded-full bg-emerald-500/20 border-2 border-emerald-500/50 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <div class="w-0.5 h-12 bg-emerald-500/30"></div>
                            </div>
                            <div class="pt-1 pb-4 flex-1">
                                <p class="text-sm font-medium text-white mb-1">Draft Created</p>
                                <p class="text-xs text-gray-500 mb-2">LinkedIn post — Weekly update #12</p>
                                <div class="flex items-center gap-2">
                                    <div class="w-5 h-5 rounded-full bg-gradient-to-br from-indigo-400 to-purple-400 flex items-center justify-center">
                                        <span class="text-[8px] text-white font-bold">JK</span>
                                    </div>
                                    <span class="text-xs text-gray-500">by John K. — 2h ago</span>
                                </div>
                            </div>
                        </div>

                        {{-- Step 2 - In progress --}}
                        <div class="flex gap-4 mb-0">
                            <div class="flex flex-col items-center">
                                <div class="w-10 h-10 rounded-full bg-amber-500/20 border-2 border-amber-500/50 flex items-center justify-center shrink-0 relative">
                                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/></svg>
                                    <div class="absolute -top-0.5 -right-0.5 w-3 h-3 rounded-full bg-amber-400 animate-pulse-slow"></div>
                                </div>
                                <div class="w-0.5 h-12 bg-[#2a2a3e]"></div>
                            </div>
                            <div class="pt-1 pb-4 flex-1">
                                <p class="text-sm font-medium text-amber-300 mb-1">In Review</p>
                                <p class="text-xs text-gray-500 mb-2">Waiting for team feedback</p>
                                <div class="flex items-center gap-2">
                                    <div class="flex -space-x-1.5">
                                        <div class="w-5 h-5 rounded-full bg-gradient-to-br from-pink-400 to-rose-400 flex items-center justify-center ring-2 ring-[#111118]">
                                            <span class="text-[8px] text-white font-bold">AL</span>
                                        </div>
                                        <div class="w-5 h-5 rounded-full bg-gradient-to-br from-cyan-400 to-blue-400 flex items-center justify-center ring-2 ring-[#111118]">
                                            <span class="text-[8px] text-white font-bold">MR</span>
                                        </div>
                                    </div>
                                    <span class="text-xs text-amber-400/80">1/2 approved</span>
                                </div>
                            </div>
                        </div>

                        {{-- Step 3 - Pending --}}
                        <div class="flex gap-4">
                            <div class="flex flex-col items-center">
                                <div class="w-10 h-10 rounded-full bg-[#1a1a2e] border-2 border-[#2a2a3e] flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                            </div>
                            <div class="pt-1 flex-1">
                                <p class="text-sm font-medium text-gray-500 mb-1">Publish</p>
                                <p class="text-xs text-gray-600">Auto-publish when approved</p>
                            </div>
                        </div>
                    </div>

                    {{-- Comment preview --}}
                    <div class="mx-5 mb-4 p-3 rounded-lg bg-[#0d0d14] border border-[#2a2a3e]">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-4 h-4 rounded-full bg-gradient-to-br from-pink-400 to-rose-400 flex items-center justify-center">
                                <span class="text-[6px] text-white font-bold">AL</span>
                            </div>
                            <span class="text-xs text-gray-400 font-medium">Anna L.</span>
                            <span class="text-[10px] text-gray-600">30m ago</span>
                        </div>
                        <p class="text-xs text-gray-400">"Looks great! Maybe tweak the CTA — 'Start today' instead of 'Learn more'?"</p>
                    </div>
                </div>
            </div>

            {{-- Text (right on desktop) --}}
            <div class="animate-on-scroll order-1 lg:order-2">
                <div class="w-14 h-14 rounded-xl bg-amber-500/10 flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">{{ __('features.f6_title') }}</h2>
                <p class="text-gray-400 text-lg mb-8 leading-relaxed">{{ __('features.f6_desc') }}</p>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f6_bullet_1') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f6_bullet_2') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f6_bullet_3') }}</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-gray-300 text-sm leading-relaxed">{{ __('features.f6_bullet_4') }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ==================== CTA ==================== --}}
<section class="relative py-24 sm:py-32 overflow-hidden">
    <div class="absolute inset-0 flex items-center justify-center">
        <div class="w-[600px] h-[600px] bg-indigo-500/10 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 max-w-3xl mx-auto px-4 sm:px-6 text-center animate-on-scroll">
        <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold text-white mb-6">{{ __('features.cta_title') }}</h2>
        <p class="text-lg text-gray-400 mb-10 max-w-xl mx-auto">{{ __('features.cta_subtitle') }}</p>
        <a href="{{ route('register') }}" class="landing-btn-primary text-lg">
            {{ __('features.cta_button') }}
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
