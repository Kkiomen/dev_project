<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    currentPage: { type: Number, required: true },
    lastPage: { type: Number, required: true },
    total: { type: Number, required: true },
    perPage: { type: Number, default: 20 },
    showingKey: { type: String, default: 'postAutomation.pagination.showing' },
});

const emit = defineEmits(['update:currentPage']);

const { t } = useI18n();

const from = computed(() => (props.currentPage - 1) * props.perPage + 1);
const to = computed(() => Math.min(props.currentPage * props.perPage, props.total));

const pages = computed(() => {
    const items = [];
    const current = props.currentPage;
    const last = props.lastPage;

    if (last <= 7) {
        for (let i = 1; i <= last; i++) items.push(i);
        return items;
    }

    items.push(1);

    if (current > 3) items.push('...');

    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    for (let i = start; i <= end; i++) items.push(i);

    if (current < last - 2) items.push('...');

    items.push(last);

    return items;
});

function goToPage(page) {
    if (page === '...' || page === props.currentPage) return;
    emit('update:currentPage', page);
}
</script>

<template>
    <div v-if="lastPage > 1" class="flex flex-col sm:flex-row items-center justify-between gap-3 mt-6">
        <p class="text-sm text-gray-500">
            {{ t(showingKey, { from, to, total }) }}
        </p>
        <div class="flex items-center gap-1">
            <button
                :disabled="currentPage === 1"
                @click="goToPage(currentPage - 1)"
                class="px-2.5 py-1.5 text-sm rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <button
                v-for="(page, idx) in pages"
                :key="idx"
                @click="goToPage(page)"
                :disabled="page === '...'"
                :class="[
                    'px-3 py-1.5 text-sm rounded-lg min-w-[36px]',
                    page === currentPage
                        ? 'bg-blue-600 text-white font-medium'
                        : page === '...'
                            ? 'text-gray-400 cursor-default'
                            : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50',
                ]"
            >
                {{ page }}
            </button>
            <button
                :disabled="currentPage === lastPage"
                @click="goToPage(currentPage + 1)"
                class="px-2.5 py-1.5 text-sm rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
    </div>
</template>
