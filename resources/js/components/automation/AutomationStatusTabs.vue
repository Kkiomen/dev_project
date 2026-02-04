<script setup>
import { useI18n } from 'vue-i18n';

const props = defineProps({
    activeStatus: { type: String, default: '' },
    stats: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:activeStatus']);

const { t } = useI18n();

const tabs = [
    { value: '', key: 'all', color: 'bg-gray-600', activeBg: 'bg-gray-100', activeText: 'text-gray-900' },
    { value: 'draft', key: 'draft', color: 'bg-gray-400', activeBg: 'bg-gray-100', activeText: 'text-gray-700' },
    { value: 'pending_approval', key: 'pending', color: 'bg-yellow-400', activeBg: 'bg-yellow-100', activeText: 'text-yellow-700' },
    { value: 'approved', key: 'approved', color: 'bg-green-400', activeBg: 'bg-green-100', activeText: 'text-green-700' },
    { value: 'scheduled', key: 'scheduled', color: 'bg-blue-400', activeBg: 'bg-blue-100', activeText: 'text-blue-700' },
    { value: 'published', key: 'published', color: 'bg-purple-400', activeBg: 'bg-purple-100', activeText: 'text-purple-700' },
    { value: 'failed', key: 'failed', color: 'bg-red-400', activeBg: 'bg-red-100', activeText: 'text-red-700' },
];

function getCount(key) {
    if (key === 'all') return props.stats.total ?? 0;
    if (key === 'pending') return props.stats.pending_approval ?? props.stats.pending ?? 0;
    return props.stats[key] ?? 0;
}

function selectTab(value) {
    emit('update:activeStatus', value);
}
</script>

<template>
    <div class="mb-4 -mx-1 overflow-x-auto">
        <div class="flex items-center gap-1.5 px-1 min-w-max">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                @click="selectTab(tab.value)"
                :class="[
                    'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors whitespace-nowrap',
                    activeStatus === tab.value
                        ? `${tab.activeBg} ${tab.activeText}`
                        : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50',
                ]"
            >
                <span
                    class="w-2 h-2 rounded-full shrink-0"
                    :class="tab.color"
                />
                {{ tab.key === 'all' ? t('postAutomation.tabs.all') : t(`postAutomation.stats.${tab.key}`) }}
                <span
                    class="text-xs px-1.5 py-0.5 rounded-full"
                    :class="activeStatus === tab.value ? 'bg-white/60' : 'bg-gray-100'"
                >
                    {{ getCount(tab.key) }}
                </span>
            </button>
        </div>
    </div>
</template>
