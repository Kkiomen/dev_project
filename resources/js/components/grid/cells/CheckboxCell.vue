<script setup>
defineProps({
    value: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggle']);
</script>

<template>
    <div class="min-h-[32px] px-2 py-1 flex items-center">
        <input
            type="checkbox"
            :checked="value"
            @change="emit('toggle')"
            @click.stop
            class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
        />
    </div>
</template>
