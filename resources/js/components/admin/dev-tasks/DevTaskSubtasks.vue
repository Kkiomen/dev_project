<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDevTasksStore } from '@/stores/devTasks';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    taskId: { type: String, required: true },
    subtasks: { type: Array, default: () => [] },
    progress: { type: Object, default: () => ({ total: 0, completed: 0, percentage: 0 }) },
});

const emit = defineEmits(['updated']);

const { t } = useI18n();
const devTasksStore = useDevTasksStore();
const toast = useToast();

const newSubtaskTitle = ref('');
const addingSubtask = ref(false);
const editingId = ref(null);
const editingTitle = ref('');
const draggingId = ref(null);

const sortedSubtasks = computed(() => {
    return [...props.subtasks].sort((a, b) => a.position - b.position);
});

const handleAddSubtask = async () => {
    if (!newSubtaskTitle.value.trim()) return;

    addingSubtask.value = true;
    try {
        await devTasksStore.createSubtask(props.taskId, newSubtaskTitle.value.trim());
        newSubtaskTitle.value = '';
        emit('updated');
    } catch (error) {
        toast.error(t('devTasks.subtasks.addError'));
    } finally {
        addingSubtask.value = false;
    }
};

const handleToggle = async (subtask) => {
    try {
        await devTasksStore.toggleSubtask(props.taskId, subtask.id);
        emit('updated');
    } catch (error) {
        toast.error(t('devTasks.subtasks.toggleError'));
    }
};

const handleStartEdit = (subtask) => {
    editingId.value = subtask.id;
    editingTitle.value = subtask.title;
};

const handleSaveEdit = async () => {
    if (!editingTitle.value.trim() || !editingId.value) return;

    try {
        await devTasksStore.updateSubtask(props.taskId, editingId.value, editingTitle.value.trim());
        editingId.value = null;
        editingTitle.value = '';
        emit('updated');
    } catch (error) {
        toast.error(t('devTasks.subtasks.updateError'));
    }
};

const handleCancelEdit = () => {
    editingId.value = null;
    editingTitle.value = '';
};

const handleDelete = async (subtaskId) => {
    try {
        await devTasksStore.deleteSubtask(props.taskId, subtaskId);
        emit('updated');
    } catch (error) {
        toast.error(t('devTasks.subtasks.deleteError'));
    }
};

const handleDragStart = (e, subtaskId) => {
    draggingId.value = subtaskId;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/subtask-id', subtaskId);
};

const handleDragOver = (e) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
};

const handleDrop = async (e, targetSubtask) => {
    e.preventDefault();
    const sourceId = e.dataTransfer.getData('text/subtask-id');

    if (sourceId === targetSubtask.id) {
        draggingId.value = null;
        return;
    }

    const newOrder = sortedSubtasks.value.map(s => s.id);
    const sourceIndex = newOrder.indexOf(sourceId);
    const targetIndex = newOrder.indexOf(targetSubtask.id);

    newOrder.splice(sourceIndex, 1);
    newOrder.splice(targetIndex, 0, sourceId);

    try {
        await devTasksStore.reorderSubtasks(props.taskId, newOrder);
        emit('updated');
    } catch (error) {
        toast.error(t('devTasks.subtasks.reorderError'));
    }

    draggingId.value = null;
};

const handleDragEnd = () => {
    draggingId.value = null;
};
</script>

<template>
    <div class="subtasks-panel">
        <!-- Header with progress -->
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-medium text-gray-700">
                {{ t('devTasks.subtasks.title') }}
            </h4>
            <div v-if="progress.total > 0" class="flex items-center gap-2">
                <div class="w-24 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-green-500 rounded-full transition-all duration-300"
                        :style="{ width: `${progress.percentage}%` }"
                    />
                </div>
                <span class="text-xs text-gray-500">
                    {{ progress.completed }}/{{ progress.total }}
                </span>
            </div>
        </div>

        <!-- Subtasks list -->
        <div class="space-y-1 mb-3">
            <div
                v-for="subtask in sortedSubtasks"
                :key="subtask.id"
                class="group flex items-center gap-2 py-1.5 px-2 rounded-lg hover:bg-gray-50 transition-colors"
                :class="{ 'opacity-50': draggingId === subtask.id }"
                draggable="true"
                @dragstart="handleDragStart($event, subtask.id)"
                @dragover="handleDragOver"
                @drop="handleDrop($event, subtask)"
                @dragend="handleDragEnd"
            >
                <!-- Drag handle -->
                <button class="cursor-grab text-gray-300 hover:text-gray-500 opacity-0 group-hover:opacity-100 transition-opacity">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z" />
                    </svg>
                </button>

                <!-- Checkbox -->
                <button
                    @click="handleToggle(subtask)"
                    class="flex-shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
                    :class="subtask.is_completed
                        ? 'bg-green-500 border-green-500 text-white'
                        : 'border-gray-300 hover:border-green-500'"
                >
                    <svg v-if="subtask.is_completed" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </button>

                <!-- Title -->
                <div v-if="editingId === subtask.id" class="flex-1 flex items-center gap-2">
                    <input
                        v-model="editingTitle"
                        type="text"
                        class="flex-1 text-sm border-gray-300 rounded py-1 px-2 focus:border-blue-500 focus:ring-blue-500"
                        @keyup.enter="handleSaveEdit"
                        @keyup.escape="handleCancelEdit"
                        autofocus
                    />
                    <button @click="handleSaveEdit" class="text-green-600 hover:text-green-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                    <button @click="handleCancelEdit" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <span
                    v-else
                    @dblclick="handleStartEdit(subtask)"
                    class="flex-1 text-sm cursor-pointer"
                    :class="subtask.is_completed ? 'text-gray-400 line-through' : 'text-gray-700'"
                >
                    {{ subtask.title }}
                </span>

                <!-- Actions -->
                <div v-if="editingId !== subtask.id" class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                        @click="handleStartEdit(subtask)"
                        class="p-1 text-gray-400 hover:text-gray-600 rounded"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </button>
                    <button
                        @click="handleDelete(subtask.id)"
                        class="p-1 text-gray-400 hover:text-red-600 rounded"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>

            <div v-if="!subtasks.length" class="text-sm text-gray-400 text-center py-4">
                {{ t('devTasks.subtasks.empty') }}
            </div>
        </div>

        <!-- Add new subtask -->
        <div class="flex items-center gap-2">
            <input
                v-model="newSubtaskTitle"
                type="text"
                class="flex-1 text-sm border-gray-200 rounded-lg py-2 px-3 focus:border-blue-500 focus:ring-blue-500"
                :placeholder="t('devTasks.subtasks.addPlaceholder')"
                @keyup.enter="handleAddSubtask"
                :disabled="addingSubtask"
            />
            <button
                @click="handleAddSubtask"
                :disabled="!newSubtaskTitle.trim() || addingSubtask"
                class="px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                <svg v-if="addingSubtask" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </button>
        </div>
    </div>
</template>
