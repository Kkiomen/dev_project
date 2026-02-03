<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 border border-transparent rounded-lg font-semibold text-sm text-white tracking-wide hover:shadow-[0_0_30px_rgba(99,102,241,0.4)] hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-brand-accent focus:ring-offset-2 focus:ring-offset-brand-bg transition-all duration-200']) }}>
    {{ $slot }}
</button>
