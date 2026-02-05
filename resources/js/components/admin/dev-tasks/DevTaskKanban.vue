<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDevTasksStore } from '@/stores/devTasks';
import { useToast } from '@/composables/useToast';
import DevTaskColumn from './DevTaskColumn.vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    selectedTaskId: { type: String, default: null },
});

const emit = defineEmits(['task-click', 'task-updated']);

const { t } = useI18n();
const devTasksStore = useDevTasksStore();
const toast = useToast();

const columns = [
    { id: 'backlog', color: '#64748B', bgColor: 'bg-slate-50', borderColor: 'border-slate-200' },
    { id: 'in_progress', color: '#F59E0B', bgColor: 'bg-amber-50', borderColor: 'border-amber-200' },
    { id: 'review', color: '#3B82F6', bgColor: 'bg-blue-50', borderColor: 'border-blue-200' },
    { id: 'done', color: '#10B981', bgColor: 'bg-emerald-50', borderColor: 'border-emerald-200' },
];

const tasksByStatus = computed(() => {
    const grouped = {};
    columns.forEach(col => {
        grouped[col.id] = props.tasks
            .filter(t => t.status === col.id)
            .sort((a, b) => a.position - b.position);
    });
    return grouped;
});

const handleMoveTask = async (taskId, fromStatus, toStatus, newPosition) => {
    devTasksStore.moveTaskOptimistic(taskId, fromStatus, toStatus, newPosition);

    try {
        await devTasksStore.moveTask(taskId, toStatus, newPosition);
        emit('task-updated');
    } catch (error) {
        console.error('Failed to move task:', error);
        await devTasksStore.fetchTasks();
        toast.error(t('devTasks.moveError'));
    }
};

const handleTaskClick = (task) => {
    emit('task-click', task);
};
</script>

<template>
    <div class="kanban-board h-full overflow-x-auto overflow-y-hidden">
        <div class="flex h-full gap-4 p-4 min-w-max">
            <DevTaskColumn
                v-for="column in columns"
                :key="column.id"
                :column="column"
                :tasks="tasksByStatus[column.id]"
                :selected-task-id="selectedTaskId"
                @task-click="handleTaskClick"
                @move-task="handleMoveTask"
            />
        </div>
    </div>
</template>

<style scoped>
.kanban-board {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.kanban-board::-webkit-scrollbar {
    height: 8px;
}
.kanban-board::-webkit-scrollbar-track {
    background: transparent;
}
.kanban-board::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 4px;
}
.kanban-board::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8;
}
</style>
