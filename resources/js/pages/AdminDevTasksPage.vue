<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDevTasksStore } from '@/stores/devTasks';
import { useToast } from '@/composables/useToast';
import { useDevTaskKeyboard } from '@/composables/useDevTaskKeyboard';
import DevTaskKanban from '@/components/admin/dev-tasks/DevTaskKanban.vue';
import DevTaskDetailModal from '@/components/admin/dev-tasks/DevTaskDetailModal.vue';
import DevTaskCreateModal from '@/components/admin/dev-tasks/DevTaskCreateModal.vue';
import DevTaskQuickFilters from '@/components/admin/dev-tasks/DevTaskQuickFilters.vue';
import DevTaskKeyboardHelp from '@/components/admin/dev-tasks/DevTaskKeyboardHelp.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Dropdown from '@/components/common/Dropdown.vue';

const { t } = useI18n();
const devTasksStore = useDevTasksStore();
const toast = useToast();

// State
const loading = ref(true);
const showCreateModal = ref(false);
const selectedTask = ref(null);
const searchQuery = ref('');
const searchTimeout = ref(null);
const showKeyboardHelp = ref(false);
const searchInputRef = ref(null);

// Filters
const filters = ref({
    project: '',
    priority: '',
    assignee: '',
});

const quickFilter = ref('all'); // all, my, overdue, due_soon

// Keyboard shortcuts
const keyboardEnabled = computed(() => !showCreateModal.value && !showKeyboardHelp.value);

const { selectedTask: keyboardSelectedTask, selectTask, clearSelection } = useDevTaskKeyboard({
    enabled: keyboardEnabled,
    onNewTask: () => { showCreateModal.value = true; },
    onEditTask: (task) => { handleTaskClick(task); },
    onDeleteTask: (task) => { handleTaskClick(task); },
    onOpenHelp: () => { showKeyboardHelp.value = true; },
    onFocusSearch: () => { searchInputRef.value?.focus(); },
    onAddComment: (task) => { handleTaskClick(task); },
});

// Sync keyboard selection with selectedTask
watch(keyboardSelectedTask, (task) => {
    if (task) {
        handleTaskClick(task);
    }
});

// Computed
const filteredTasks = computed(() => {
    let tasks = [...devTasksStore.tasks];

    // Quick filter
    switch (quickFilter.value) {
        case 'my':
            tasks = tasks.filter(t => t.assigned_to?.id === devTasksStore.currentUserId);
            break;
        case 'overdue':
            tasks = tasks.filter(t => t.is_overdue);
            break;
        case 'due_soon':
            tasks = tasks.filter(t => t.is_due_soon);
            break;
    }

    if (filters.value.project) {
        tasks = tasks.filter(t => t.project === filters.value.project);
    }
    if (filters.value.priority) {
        tasks = tasks.filter(t => t.priority === filters.value.priority);
    }
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        tasks = tasks.filter(t =>
            t.title.toLowerCase().includes(query) ||
            t.identifier.toLowerCase().includes(query) ||
            t.pm_description?.toLowerCase().includes(query)
        );
    }

    return tasks;
});

const handleQuickFilterChange = (filter) => {
    quickFilter.value = filter;
    devTasksStore.applyQuickFilter(filter);
};

const activeFiltersCount = computed(() => {
    let count = 0;
    if (filters.value.project) count++;
    if (filters.value.priority) count++;
    if (filters.value.assignee) count++;
    if (searchQuery.value) count++;
    return count;
});

const taskStats = computed(() => {
    const stats = {
        total: filteredTasks.value.length,
        backlog: 0,
        in_progress: 0,
        review: 0,
        done: 0,
    };
    filteredTasks.value.forEach(t => {
        if (stats[t.status] !== undefined) stats[t.status]++;
    });
    return stats;
});

// Methods
const fetchData = async () => {
    loading.value = true;
    try {
        await Promise.all([
            devTasksStore.fetchTasks(),
            devTasksStore.fetchProjects(),
            devTasksStore.fetchSavedFilters(),
            devTasksStore.fetchActiveTimer(),
        ]);
    } catch (error) {
        console.error('Failed to fetch data:', error);
        toast.error(t('devTasks.fetchError'));
    } finally {
        loading.value = false;
    }
};

onMounted(() => fetchData());

const handleTaskClick = (task) => {
    selectedTask.value = task;
    devTasksStore.setCurrentTask(task);
};

const handleCloseDetail = () => {
    selectedTask.value = null;
    devTasksStore.clearCurrentTask();
    clearSelection();
};

const handleTaskCreated = (task) => {
    showCreateModal.value = false;
    toast.success(t('devTasks.taskCreated'));
    // Auto-select created task
    selectedTask.value = task;
    devTasksStore.setCurrentTask(task);
};

const handleTaskUpdated = () => {
    // Refresh selected task data
    if (selectedTask.value) {
        const updated = devTasksStore.tasks.find(t => t.id === selectedTask.value.id);
        if (updated) selectedTask.value = updated;
    }
};

const handleTaskDeleted = () => {
    selectedTask.value = null;
    devTasksStore.clearCurrentTask();
    toast.success(t('devTasks.taskDeleted'));
};

const clearFilters = () => {
    filters.value = { project: '', priority: '', assignee: '' };
    searchQuery.value = '';
};

const priorities = [
    { value: 'urgent', label: t('devTasks.priorities.urgent'), color: '#DC2626' },
    { value: 'high', label: t('devTasks.priorities.high'), color: '#F97316' },
    { value: 'medium', label: t('devTasks.priorities.medium'), color: '#3B82F6' },
    { value: 'low', label: t('devTasks.priorities.low'), color: '#6B7280' },
];
</script>

<template>
    <div class="h-full flex flex-col bg-gray-50/50">
        <!-- Header -->
        <header class="flex-shrink-0 bg-white border-b border-gray-200">
            <!-- Top bar -->
            <div class="px-4 sm:px-6 py-3">
                <div class="flex items-center justify-between gap-4">
                    <!-- Left: Title & breadcrumb -->
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-lg font-semibold text-gray-900">{{ t('devTasks.title') }}</h1>
                            <p class="text-xs text-gray-500">{{ taskStats.total }} {{ t('devTasks.tasksCount') }}</p>
                        </div>
                    </div>

                    <!-- Right: Actions -->
                    <div class="flex items-center gap-2">
                        <button
                            @click="showKeyboardHelp = true"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            :title="t('devTasks.shortcuts.title')"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                        </button>
                        <button
                            @click="fetchData"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            :title="t('common.refresh')"
                        >
                            <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                        <button
                            @click="showCreateModal = true"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ t('devTasks.createTask') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Filters bar -->
            <div class="px-4 sm:px-6 py-2 border-t border-gray-100">
                <DevTaskQuickFilters
                    :current-filter="quickFilter"
                    @filter-change="handleQuickFilterChange"
                />
            </div>

            <!-- Filters bar -->
            <div class="px-4 sm:px-6 py-2 border-t border-gray-100 bg-gray-50/50">
                <div class="flex items-center gap-3 flex-wrap">
                    <!-- Search -->
                    <div class="relative flex-1 max-w-xs">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            ref="searchInputRef"
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('devTasks.searchPlaceholder')"
                            class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 bg-white"
                        />
                    </div>

                    <!-- Project filter -->
                    <select
                        v-model="filters.project"
                        class="text-sm border border-gray-200 rounded-lg py-1.5 pl-3 pr-8 bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    >
                        <option value="">{{ t('devTasks.allProjects') }}</option>
                        <option v-for="project in devTasksStore.projects" :key="project.id" :value="project.prefix">
                            {{ project.prefix }} - {{ project.name }}
                        </option>
                    </select>

                    <!-- Priority filter -->
                    <select
                        v-model="filters.priority"
                        class="text-sm border border-gray-200 rounded-lg py-1.5 pl-3 pr-8 bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    >
                        <option value="">{{ t('devTasks.allPriorities') }}</option>
                        <option v-for="p in priorities" :key="p.value" :value="p.value">
                            {{ p.label }}
                        </option>
                    </select>

                    <!-- Clear filters -->
                    <button
                        v-if="activeFiltersCount > 0"
                        @click="clearFilters"
                        class="inline-flex items-center gap-1 px-2 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        {{ t('devTasks.clearFilters') }} ({{ activeFiltersCount }})
                    </button>

                    <!-- Spacer -->
                    <div class="flex-1" />

                    <!-- Stats pills -->
                    <div class="hidden sm:flex items-center gap-1.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600">
                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                            {{ taskStats.backlog }}
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-amber-50 text-amber-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            {{ taskStats.in_progress }}
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-blue-50 text-blue-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                            {{ taskStats.review }}
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-green-50 text-green-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                            {{ taskStats.done }}
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="flex-1 overflow-hidden">
            <!-- Kanban Board -->
            <div class="h-full overflow-hidden">
                <div v-if="loading" class="flex items-center justify-center h-full">
                    <LoadingSpinner />
                </div>

                <DevTaskKanban
                    v-else
                    :tasks="filteredTasks"
                    :selected-task-id="selectedTask?.id"
                    @task-click="handleTaskClick"
                    @task-updated="handleTaskUpdated"
                />
            </div>
        </div>

        <!-- Task Detail Modal -->
        <DevTaskDetailModal
            :show="!!selectedTask"
            :task="selectedTask"
            @close="handleCloseDetail"
            @updated="handleTaskUpdated"
            @deleted="handleTaskDeleted"
        />

        <!-- Create Task Modal -->
        <DevTaskCreateModal
            :show="showCreateModal"
            @close="showCreateModal = false"
            @created="handleTaskCreated"
        />

        <!-- Keyboard Shortcuts Help -->
        <DevTaskKeyboardHelp
            :show="showKeyboardHelp"
            @close="showKeyboardHelp = false"
        />
    </div>
</template>
