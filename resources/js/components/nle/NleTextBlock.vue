<template>
    <div class="flex items-center gap-1.5 w-full h-full overflow-hidden">
        <svg class="w-3 h-3 text-purple-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />
        </svg>
        <span class="text-[10px] text-white/80 truncate italic">
            {{ element.text || element.name }}
        </span>
    </div>
</template>

<script setup>
defineProps({
    element: { type: Object, required: true },
});
</script>
