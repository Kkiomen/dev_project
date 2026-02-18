<template>
    <div class="flex items-center gap-1.5 w-full h-full overflow-hidden">
        <svg class="w-3 h-3 text-orange-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <span class="text-[10px] text-white/80 truncate">{{ element.name }}</span>
    </div>
</template>

<script setup>
defineProps({
    element: { type: Object, required: true },
});
</script>
