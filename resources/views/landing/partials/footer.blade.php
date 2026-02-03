<footer class="border-t border-brand-border py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10">
            {{-- Brand --}}
            <div>
                <div class="flex items-center mb-4">
                    <img src="{{ asset('assets/images/logo_aisello_white.svg') }}" alt="Aisello" class="h-6">
                </div>
                <p class="text-gray-500 text-sm">{{ __('landing.footer_description') }}</p>
            </div>

            {{-- Product --}}
            <div>
                <h4 class="text-white font-semibold mb-4">{{ __('landing.footer_product') }}</h4>
                <ul class="space-y-2">
                    <li><a href="/features" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">{{ __('landing.footer_features') }}</a></li>
                    <li><a href="#" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">{{ __('landing.footer_pricing') }}</a></li>
                    <li><a href="#" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">{{ __('landing.footer_integrations') }}</a></li>
                    <li><a href="#" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">{{ __('landing.footer_changelog') }}</a></li>
                </ul>
            </div>

            {{-- Company --}}
            <div>
                <h4 class="text-white font-semibold mb-4">{{ __('landing.footer_company') }}</h4>
                <ul class="space-y-2">
                    <li><a href="#" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">{{ __('landing.footer_about') }}</a></li>
                    <li><a href="#" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">{{ __('landing.footer_blog') }}</a></li>
                    <li><a href="#" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">{{ __('landing.footer_careers') }}</a></li>
                    <li><a href="#" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">{{ __('landing.footer_contact') }}</a></li>
                </ul>
            </div>

            {{-- Legal --}}
            <div>
                <h4 class="text-white font-semibold mb-4">{{ __('landing.footer_legal') }}</h4>
                <ul class="space-y-2">
                    <li><a href="#" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">{{ __('landing.footer_privacy') }}</a></li>
                    <li><a href="#" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">{{ __('landing.footer_terms') }}</a></li>
                    <li><a href="#" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">{{ __('landing.footer_cookies') }}</a></li>
                </ul>
            </div>
        </div>

        {{-- Copyright --}}
        <div class="border-t border-brand-border mt-12 pt-8 text-center">
            <p class="text-gray-600 text-sm">&copy; {{ date('Y') }} Aisello. {{ __('landing.footer_copyright') }}</p>
        </div>
    </div>
</footer>
