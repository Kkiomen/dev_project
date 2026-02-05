<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import DevTaskCard from './DevTaskCard.vue';

const props = defineProps({
    column: { type: Object, required: true },
    tasks: { type: Array, default: () => [] },
    selectedTaskId: { type: String, default: null },
});

const emit = defineEmits(['task-click', 'move-task']);

const { t } = useI18n();
const dragOverColumn = ref(false);
const collapsed = ref(false);

const columnIcons = {
    backlog: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
    in_progress: 'M13 10V3L4 14h7v7l9-11h-7z',
    review: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
    done: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
};

const handleDragOver = (e) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    dragOverColumn.value = true;
};

const handleDragLeave = (e) => {
    // Only set false if leaving the column entirely
    if (!e.currentTarget.contains(e.relatedTarget)) {
        dragOverColumn.value = false;
    }
};

const handleDrop = (e) => {
    e.preventDefault();
    dragOverColumn.value = false;

    const taskId = e.dataTransfer.getData('text/task-id');
    const fromStatus = e.dataTransfer.getData('text/from-status');

    if (!taskId || !fromStatus) return;

    const newPosition = props.tasks.length;
    emit('move-task', taskId, fromStatus, props.column.id, newPosition);
};

const handleTaskDrop = (taskId, fromStatus, position) => {
    emit('move-task', taskId, fromStatus, props.column.id, position);
};

const handleTaskClick = (task) => {
    emit('task-click', task);
};
</script>

<template>
    <div
        class="kanban-column flex-shrink-0 w-72 lg:w-80 flex flex-col h-full rounded-xl transition-all duration-200"
        :class="[
            dragOverColumn ? 'ring-2 ring-blue-400 ring-offset-2' : '',
            collapsed ? 'w-12' : ''
        ]"
        @dragover="handleDragOver"
        @dragleave="handleDragLeave"
        @drop="handleDrop"
    >
        <!-- Column Header -->
        <div
            class="flex-shrink-0 px-3 py-2.5 rounded-t-xl border-b"
            :class="[column.bgColor, column.borderColor]"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 min-w-0">
                    <!-- Icon -->
                    <div
                        class="w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0"
                        :style="{ backgroundColor: column.color + '20' }"
                    >
                        <svg class="w-3.5 h-3.5" :style="{ color: column.color }" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="columnIcons[column.id]" />
                        </svg>
                    </div>

                    <!-- Title & count -->
                    <h3 class="text-sm font-semibold text-gray-700 truncate">
                        {{ t(`devTasks.columns.${column.id}`) }}
                    </h3>
                    <span
                        class="flex-shrink-0 min-w-[20px] h-5 px-1.5 rounded-full text-xs font-semibold flex items-center justify-center"
                        :style="{ backgroundColor: column.color + '20', color: column.color }"
                    >
                        {{ tasks.length }}
                    </span>
                </div>

                <!-- Column actions -->
                <button
                    @click="collapsed = !collapsed"
                    class="p-1 rounded hover:bg-black/5 text-gray-400 hover:text-gray-600 transition-colors"
                >
                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': collapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Tasks container -->
        <div
            v-show="!collapsed"
            class="flex-1 overflow-y-auto p-2 space-y-2 bg-gray-50/50 rounded-b-xl min-h-[100px]"
            :class="{ 'bg-blue-50/50': dragOverColumn }"
        >
            <TransitionGroup name="task-list" tag="div" class="space-y-2">
                <DevTaskCard
                    v-for="(task, index) in tasks"
                    :key="task.id"
                    :task="task"
                    :column-id="column.id"
                    :index="index"
                    :is-selected="task.id === selectedTaskId"
                    @click="handleTaskClick(task)"
                    @drop-at="(taskId, fromStatus) => handleTaskDrop(taskId, fromStatus, index)"
                />
            </TransitionGroup>

            <!-- Empty state -->
            <div
                v-if="!tasks.length"
                class="flex flex-col items-center justify-center py-8 text-gray-400"
            >
                <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <span class="text-xs">{{ t('devTasks.noTasks') }}</span>
            </div>

            <!-- Drop indicator at bottom -->
            <div
                v-if="dragOverColumn && tasks.length > 0"
                class="h-1 rounded-full bg-blue-400 animate-pulse"
            />
        </div>

        <!-- Collapsed view -->
        <div
            v-if="collapsed"
            class="flex-1 flex flex-col items-center py-4 bg-gray-50/50 rounded-b-xl"
        >
            <span
                class="writing-mode-vertical text-xs font-medium text-gray-500 tracking-wider"
            >
                {{ t(`devTasks.columns.${column.id}`) }}
            </span>
            <span
                class="mt-2 w-6 h-6 rounded-full text-xs font-semibold flex items-center justify-center"
                :style="{ backgroundColor: column.color + '20', color: column.color }"
            >
                {{ tasks.length }}
            </span>
        </div>
    </div>
</template>

<style scoped>
.kanban-column {
    background: linear-gradient(to bottom, transparent, rgba(249, 250, 251, 0.5));
}

.writing-mode-vertical {
    writing-mode: vertical-rl;
    text-orientation: mixed;
}

/* Task list transitions */
.task-list-move,
.task-list-enter-active,
.task-list-leave-active {
    transition: all 0.2s ease;
}

.task-list-enter-from,
.task-list-leave-to {
    opacity: 0;
    transform: translateX(-10px);
}

.task-list-leave-active {
    position: absolute;
}

/* Scrollbar */
.kanban-column > div::-webkit-scrollbar {
    width: 4px;
}
.kanban-column > div::-webkit-scrollbar-track {
    background: transparent;
}
.kanban-column > div::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
    border-radius: 2px;
}
.kanban-column > div::-webkit-scrollbar-thumb:hover {
    background-color: #9ca3af;
}
</style>
