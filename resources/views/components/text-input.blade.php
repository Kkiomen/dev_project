@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-brand-bg border-brand-border text-white placeholder-gray-500 focus:border-brand-accent focus:ring-brand-accent rounded-lg shadow-sm']) }}>
