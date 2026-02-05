<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDevTasksStore } from '@/stores/devTasks';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    currentFilter: { type: String, default: 'all' },
});

const emit = defineEmits(['filter-change']);

const { t } = useI18n();
const devTasksStore = useDevTasksStore();
const toast = useToast();

const showSaveModal = ref(false);
const newFilterName = ref('');
const savingFilter = ref(false);

const quickFilters = [
    { id: 'all', icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
    { id: 'my', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { id: 'overdue', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { id: 'due_soon', icon: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
];

const savedFilters = computed(() => devTasksStore.savedFilters);
const overdueCount = computed(() => devTasksStore.overdueTasksCount);
const myTasksCount = computed(() => devTasksStore.myTasks.length);

const handleFilterClick = (filterId) => {
    emit('filter-change', filterId);
};

const handleSavedFilterClick = (filter) => {
    devTasksStore.applyQuickFilter('saved', filter.filters);
    emit('filter-change', `saved:${filter.id}`);
};

const openSaveModal = () => {
    newFilterName.value = '';
    showSaveModal.value = true;
};

const handleSaveFilter = async () => {
    if (!newFilterName.value.trim()) return;

    savingFilter.value = true;
    try {
        const currentFilters = {
            project: devTasksStore.currentProject,
            search: devTasksStore.searchQuery,
            status: devTasksStore.statusFilter,
            priority: devTasksStore.priorityFilter,
            assignee: devTasksStore.assigneeFilter,
        };

        await devTasksStore.createSavedFilter({
            name: newFilterName.value.trim(),
            filters: currentFilters,
        });

        showSaveModal.value = false;
        toast.success(t('devTasks.quickFilters.saved'));
    } catch (error) {
        toast.error(t('devTasks.quickFilters.saveError'));
    } finally {
        savingFilter.value = false;
    }
};

const handleDeleteFilter = async (filterId) => {
    try {
        await devTasksStore.deleteSavedFilter(filterId);
        toast.success(t('devTasks.quickFilters.deleted'));
    } catch (error) {
        toast.error(t('devTasks.quickFilters.deleteError'));
    }
};
</script>

<template>
    <div class="quick-filters flex items-center gap-2 flex-wrap">
        <!-- Quick filter buttons -->
        <button
            v-for="filter in quickFilters"
            :key="filter.id"
            @click="handleFilterClick(filter.id)"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg transition-colors"
            :class="currentFilter === filter.id
                ? 'bg-blue-100 text-blue-700'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="filter.icon" />
            </svg>
            <span>{{ t(`devTasks.quickFilters.${filter.id}`) }}</span>

            <!-- Badge for counts -->
            <span
                v-if="filter.id === 'overdue' && overdueCount > 0"
                class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-red-500 text-white"
            >
                {{ overdueCount }}
            </span>
            <span
                v-if="filter.id === 'my' && myTasksCount > 0"
                class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-blue-500 text-white"
            >
                {{ myTasksCount }}
            </span>
        </button>

        <!-- Divider -->
        <div v-if="savedFilters.length" class="w-px h-6 bg-gray-300 mx-1" />

        <!-- Saved filters -->
        <div
            v-for="filter in savedFilters"
            :key="filter.id"
            class="group relative"
        >
            <button
                @click="handleSavedFilterClick(filter)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg transition-colors"
                :class="currentFilter === `saved:${filter.id}`
                    ? 'bg-purple-100 text-purple-700'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                </svg>
                {{ filter.name }}
            </button>
            <button
                @click.stop="handleDeleteFilter(filter.id)"
                class="absolute -top-1 -right-1 w-4 h-4 bg-gray-500 hover:bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Save current filter button -->
        <button
            @click="openSaveModal"
            class="inline-flex items-center gap-1 px-2 py-1.5 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
            :title="t('devTasks.quickFilters.saveCurrentFilter')"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        </button>

        <!-- Save filter modal -->
        <Teleport to="body">
            <div
                v-if="showSaveModal"
                @click.self="showSaveModal = false"
                class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        {{ t('devTasks.quickFilters.saveFilter') }}
                    </h3>

                    <input
                        v-model="newFilterName"
                        type="text"
                        class="w-full border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500 mb-4"
                        :placeholder="t('devTasks.quickFilters.filterNamePlaceholder')"
                        @keyup.enter="handleSaveFilter"
                        autofocus
                    />

                    <div class="flex justify-end gap-2">
                        <button
                            @click="showSaveModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                        >
                            {{ t('common.cancel') }}
                        </button>
                        <button
                            @click="handleSaveFilter"
                            :disabled="!newFilterName.trim() || savingFilter"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg disabled:opacity-50 transition-colors"
                        >
                            <svg v-if="savingFilter" class="w-4 h-4 animate-spin mr-1 inline-block" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ t('common.save') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
