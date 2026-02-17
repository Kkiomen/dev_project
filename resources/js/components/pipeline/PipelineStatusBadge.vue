<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    status: { type: String, required: true },
    size: { type: String, default: 'md' }, // 'sm' | 'md'
});

const { t } = useI18n();

const colorClasses = computed(() => {
    const colors = {
        pending: 'bg-gray-500/20 text-gray-400',
        running: 'bg-blue-500/20 text-blue-400',
        completed: 'bg-green-500/20 text-green-400',
        failed: 'bg-red-500/20 text-red-400',
    };
    return colors[props.status] || colors.pending;
});

const sizeClasses = computed(() => {
    return props.size === 'sm' ? 'px-1.5 py-0.5 text-[9px]' : 'px-2 py-0.5 text-[10px]';
});

const label = computed(() => t(`pipeline.runStatus.${props.status}`));

const isRunning = computed(() => props.status === 'running');
</script>

<template>
    <span :class="['inline-flex items-center gap-1 font-medium rounded-full shrink-0', colorClasses, sizeClasses]">
        <span v-if="isRunning" class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse" />
        {{ label }}
    </span>
</template>
