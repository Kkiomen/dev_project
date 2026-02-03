@php $isHome = request()->is('/'); @endphp

<nav x-data="{ open: false, scrolled: false, langOpen: false }"
     x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 20 })"
     :class="scrolled ? 'bg-brand-bg/80 backdrop-blur-lg border-brand-border' : 'bg-transparent border-transparent'"
     class="fixed top-0 left-0 right-0 z-50 border-b transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            {{-- Logo --}}
            <a href="/" class="flex items-center">
                <img src="{{ asset('assets/images/logo_aisello_white.svg') }}" alt="Aisello" class="h-6">
            </a>

            {{-- Desktop links --}}
            <div class="hidden md:flex items-center gap-8">
                <a href="/features" class="text-sm text-gray-400 hover:text-white transition-colors {{ request()->is('features') ? 'text-white' : '' }}">{{ __('landing.nav_features') }}</a>
                <a href="{{ $isHome ? '#how-it-works' : '/#how-it-works' }}" class="text-sm text-gray-400 hover:text-white transition-colors">{{ __('landing.nav_how_it_works') }}</a>
                <a href="{{ $isHome ? '#benefits' : '/#benefits' }}" class="text-sm text-gray-400 hover:text-white transition-colors">{{ __('landing.nav_benefits') }}</a>
            </div>

            {{-- Desktop CTA + Language switcher --}}
            <div class="hidden md:flex items-center gap-4">
                {{-- Language switcher --}}
                <div class="relative" @click.away="langOpen = false">
                    <button @click="langOpen = !langOpen" class="flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors px-2 py-1 rounded-md hover:bg-brand-surface/50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                        </svg>
                        <span>{{ strtoupper(app()->getLocale()) }}</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="langOpen" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-36 rounded-lg border border-brand-border bg-brand-bg/95 backdrop-blur-lg shadow-lg py-1">
                        <a href="{{ route('locale.switch', 'en') }}"
                           class="flex items-center gap-2 px-4 py-2 text-sm transition-colors {{ app()->getLocale() === 'en' ? 'text-white bg-brand-surface/50' : 'text-gray-400 hover:text-white hover:bg-brand-surface/30' }}">
                            {{ __('landing.nav_lang_en') }}
                        </a>
                        <a href="{{ route('locale.switch', 'pl') }}"
                           class="flex items-center gap-2 px-4 py-2 text-sm transition-colors {{ app()->getLocale() === 'pl' ? 'text-white bg-brand-surface/50' : 'text-gray-400 hover:text-white hover:bg-brand-surface/30' }}">
                            {{ __('landing.nav_lang_pl') }}
                        </a>
                    </div>
                </div>

                @auth
                    <a href="{{ url('/dashboard') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="text-sm text-gray-400 hover:text-white transition-colors">{{ __('landing.nav_login') }}</a>
                    <a href="{{ route('register') }}" class="landing-btn-primary text-sm !py-2 !px-4">{{ __('landing.nav_register') }}</a>
                @endauth
            </div>

            {{-- Mobile hamburger --}}
            <button @click="open = !open" class="md:hidden text-gray-400 hover:text-white" aria-label="Menu">
                <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg x-show="open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Mobile menu --}}
        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="md:hidden pb-4 border-t border-brand-border mt-2 pt-4">
            <div class="flex flex-col gap-3">
                <a href="/features" @click="open = false" class="text-sm text-gray-400 hover:text-white transition-colors {{ request()->is('features') ? 'text-white' : '' }}">{{ __('landing.nav_features') }}</a>
                <a href="{{ $isHome ? '#how-it-works' : '/#how-it-works' }}" @click="open = false" class="text-sm text-gray-400 hover:text-white transition-colors">{{ __('landing.nav_how_it_works') }}</a>
                <a href="{{ $isHome ? '#benefits' : '/#benefits' }}" @click="open = false" class="text-sm text-gray-400 hover:text-white transition-colors">{{ __('landing.nav_benefits') }}</a>

                {{-- Mobile language switcher --}}
                <div class="flex gap-2 mt-1">
                    <a href="{{ route('locale.switch', 'en') }}"
                       class="flex-1 text-center text-sm py-1.5 rounded-md border transition-colors {{ app()->getLocale() === 'en' ? 'border-indigo-500/50 text-white bg-brand-surface/50' : 'border-brand-border text-gray-400 hover:text-white hover:border-brand-border' }}">
                        EN
                    </a>
                    <a href="{{ route('locale.switch', 'pl') }}"
                       class="flex-1 text-center text-sm py-1.5 rounded-md border transition-colors {{ app()->getLocale() === 'pl' ? 'border-indigo-500/50 text-white bg-brand-surface/50' : 'border-brand-border text-gray-400 hover:text-white hover:border-brand-border' }}">
                        PL
                    </a>
                </div>

                <div class="flex gap-3 mt-2">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="landing-btn-primary text-sm !py-2 !px-4 w-full text-center">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="landing-btn-secondary text-sm !py-2 !px-4 flex-1 text-center">{{ __('landing.nav_login') }}</a>
                        <a href="{{ route('register') }}" class="landing-btn-primary text-sm !py-2 !px-4 flex-1 text-center">{{ __('landing.nav_register') }}</a>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</nav>
