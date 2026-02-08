<script setup>
defineProps({
    keyword: { type: String, required: true },
    removable: { type: Boolean, default: false },
});

const emit = defineEmits(['remove']);
</script>

<template>
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
        {{ keyword }}
        <button
            v-if="removable"
            type="button"
            class="text-blue-500 hover:text-blue-700 -mr-0.5"
            @click.stop="emit('remove', keyword)"
        >
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </span>
</template>
