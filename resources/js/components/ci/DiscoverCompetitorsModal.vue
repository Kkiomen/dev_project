<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal from '@/components/common/Modal.vue';

const { t } = useI18n();

const props = defineProps({
    show: { type: Boolean, default: false },
    competitors: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    adding: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'add', 'discover']);

const selected = ref(new Set());

const allSelected = computed(() =>
    props.competitors.length > 0 && selected.value.size === props.competitors.length
);

const toggleSelect = (index) => {
    if (selected.value.has(index)) {
        selected.value.delete(index);
    } else {
        selected.value.add(index);
    }
};

const toggleAll = () => {
    if (allSelected.value) {
        selected.value.clear();
    } else {
        props.competitors.forEach((_, i) => selected.value.add(i));
    }
};

const handleAdd = () => {
    const selectedCompetitors = props.competitors.filter((_, i) => selected.value.has(i));
    if (selectedCompetitors.length > 0) {
        emit('add', selectedCompetitors);
    }
};

const handleClose = () => {
    selected.value.clear();
    emit('close');
};

const relevanceColor = (score) => {
    if (score >= 8) return 'bg-green-500/10 text-green-400 border-green-500/20';
    if (score >= 5) return 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20';
    return 'bg-gray-500/10 text-gray-400 border-gray-500/20';
};

const platformColors = {
    instagram: 'bg-pink-500/10 text-pink-400 border-pink-500/20',
    tiktok: 'bg-purple-500/10 text-purple-400 border-purple-500/20',
    linkedin: 'bg-sky-500/10 text-sky-400 border-sky-500/20',
    youtube: 'bg-red-500/10 text-red-400 border-red-500/20',
    twitter: 'bg-gray-500/10 text-gray-300 border-gray-500/20',
};
</script>

<template>
    <Modal :show="show" @close="handleClose" max-width="2xl">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-white mb-1">{{ t('ci.discover.title') }}</h2>
            <p class="text-sm text-gray-400 mb-5">{{ t('ci.discover.description') }}</p>

            <!-- Loading state -->
            <div v-if="loading" class="flex flex-col items-center justify-center py-12">
                <div class="w-8 h-8 border-2 border-orange-500 border-t-transparent rounded-full animate-spin mb-3"></div>
                <p class="text-sm text-gray-400">{{ t('ci.discover.searching') }}</p>
            </div>

            <!-- No results -->
            <div v-else-if="!loading && competitors.length === 0" class="text-center py-12">
                <div class="w-12 h-12 rounded-full bg-gray-800 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
                <p class="text-sm text-gray-400">{{ t('ci.discover.noResults') }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ t('ci.discover.noResultsDescription') }}</p>
                <button
                    @click="emit('discover')"
                    class="mt-4 rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-500 transition-colors"
                >
                    {{ t('ci.discover.button') }}
                </button>
            </div>

            <!-- Results -->
            <div v-else>
                <!-- Select all -->
                <div class="flex items-center justify-between mb-3">
                    <button
                        @click="toggleAll"
                        class="text-xs text-orange-400 hover:text-orange-300 transition-colors"
                    >
                        {{ allSelected ? t('ci.discover.deselectAll') : t('ci.discover.selectAll') }}
                    </button>
                    <span class="text-xs text-gray-500">
                        {{ selected.size }} / {{ competitors.length }}
                    </span>
                </div>

                <!-- List -->
                <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                    <div
                        v-for="(competitor, index) in competitors"
                        :key="index"
                        @click="toggleSelect(index)"
                        class="rounded-xl border p-4 cursor-pointer transition-colors"
                        :class="selected.has(index)
                            ? 'bg-orange-500/5 border-orange-500/30'
                            : 'bg-gray-900 border-gray-800 hover:border-gray-700'"
                    >
                        <div class="flex items-start gap-3">
                            <!-- Checkbox -->
                            <div class="mt-0.5 flex-shrink-0">
                                <div
                                    class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
                                    :class="selected.has(index)
                                        ? 'bg-orange-600 border-orange-600'
                                        : 'border-gray-600'"
                                >
                                    <svg v-if="selected.has(index)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="text-sm font-semibold text-white truncate">{{ competitor.name }}</h3>
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium border flex-shrink-0"
                                        :class="relevanceColor(competitor.relevance_score)"
                                    >
                                        {{ t('ci.discover.relevance') }}: {{ competitor.relevance_score }}/10
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 mb-2 line-clamp-2">{{ competitor.description }}</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="account in competitor.accounts"
                                        :key="account.platform + account.handle"
                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] border"
                                        :class="platformColors[account.platform] || 'bg-gray-500/10 text-gray-400 border-gray-500/20'"
                                    >
                                        {{ account.platform }}
                                        <span class="text-gray-500">@{{ account.handle }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-6 flex justify-end gap-3">
                <button
                    @click="handleClose"
                    class="rounded-lg border border-gray-700 px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 transition-colors"
                >
                    {{ t('common.cancel') }}
                </button>
                <button
                    v-if="competitors.length > 0"
                    @click="handleAdd"
                    :disabled="selected.size === 0 || adding"
                    class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-500 transition-colors disabled:opacity-50"
                >
                    {{ adding ? t('common.loading') : t('ci.discover.addSelected', { count: selected.size }) }}
                </button>
            </div>
        </div>
    </Modal>
</template>
