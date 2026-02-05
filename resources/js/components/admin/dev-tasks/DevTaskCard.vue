<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    task: { type: Object, required: true },
    columnId: { type: String, required: true },
    index: { type: Number, required: true },
    isSelected: { type: Boolean, default: false },
});

const emit = defineEmits(['click', 'drop-at']);

const { t } = useI18n();
const dragOver = ref(false);
const isDragging = ref(false);

// Priority config
const priorityConfig = {
    urgent: { icon: '⚡', color: '#DC2626', bg: 'bg-red-50', text: 'text-red-700', border: 'border-red-200' },
    high: { icon: '↑', color: '#F97316', bg: 'bg-orange-50', text: 'text-orange-700', border: 'border-orange-200' },
    medium: { icon: '→', color: '#3B82F6', bg: 'bg-blue-50', text: 'text-blue-700', border: 'border-blue-200' },
    low: { icon: '↓', color: '#6B7280', bg: 'bg-gray-50', text: 'text-gray-600', border: 'border-gray-200' },
};

const priority = computed(() => priorityConfig[props.task.priority] || priorityConfig.medium);

// Due date status
const dueDateStatus = computed(() => {
    if (!props.task.due_date) return null;

    if (props.task.is_overdue) {
        return { type: 'overdue', class: 'bg-red-100 text-red-700' };
    }
    if (props.task.is_due_soon) {
        return { type: 'due_soon', class: 'bg-amber-100 text-amber-700' };
    }
    return { type: 'scheduled', class: 'bg-gray-100 text-gray-600' };
});

const formatDueDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffDays = Math.floor((date - now) / (1000 * 60 * 60 * 24));

    if (diffDays < 0) return `${Math.abs(diffDays)}d`;
    if (diffDays === 0) return t('devTasks.dueDate.today');
    if (diffDays === 1) return t('devTasks.dueDate.tomorrow');
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};

// Subtask progress
const hasSubtasks = computed(() => props.task.subtask_progress?.total > 0);
const subtaskProgress = computed(() => props.task.subtask_progress || { total: 0, completed: 0, percentage: 0 });

// Drag handlers
const handleDragStart = (e) => {
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/task-id', props.task.id);
    e.dataTransfer.setData('text/from-status', props.task.status);
    isDragging.value = true;

    // Custom drag image
    const dragEl = e.target.cloneNode(true);
    dragEl.style.transform = 'rotate(3deg)';
    dragEl.style.opacity = '0.9';
    document.body.appendChild(dragEl);
    e.dataTransfer.setDragImage(dragEl, 0, 0);
    setTimeout(() => document.body.removeChild(dragEl), 0);
};

const handleDragEnd = () => {
    isDragging.value = false;
};

const handleCardDragOver = (e) => {
    e.preventDefault();
    e.stopPropagation();
    e.dataTransfer.dropEffect = 'move';
    dragOver.value = true;
};

const handleCardDragLeave = () => {
    dragOver.value = false;
};

const handleCardDrop = (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragOver.value = false;

    const taskId = e.dataTransfer.getData('text/task-id');
    const fromStatus = e.dataTransfer.getData('text/from-status');

    if (!taskId || taskId === props.task.id) return;

    emit('drop-at', taskId, fromStatus);
};

const getInitials = (name) => {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
};
</script>

<template>
    <div class="relative">
        <!-- Drop indicator -->
        <div
            v-if="dragOver"
            class="absolute -top-1 left-0 right-0 h-0.5 bg-blue-500 rounded-full z-10"
        />

        <!-- Card -->
        <div
            class="task-card group bg-white rounded-lg border shadow-sm cursor-pointer transition-all duration-150"
            :class="[
                isDragging ? 'opacity-50 scale-[0.98] rotate-1' : 'hover:shadow-md',
                isSelected ? 'ring-2 ring-blue-500 border-blue-300' : 'border-gray-200 hover:border-gray-300',
            ]"
            draggable="true"
            @dragstart="handleDragStart"
            @dragend="handleDragEnd"
            @dragover="handleCardDragOver"
            @dragleave="handleCardDragLeave"
            @drop="handleCardDrop"
            @click="$emit('click')"
        >
            <!-- Card content -->
            <div class="p-3">
                <!-- Top row: ID + Priority -->
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-mono font-medium text-blue-600 hover:underline">
                        {{ task.identifier }}
                    </span>
                    <div
                        class="flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium"
                        :class="[priority.bg, priority.text]"
                        :title="t(`devTasks.priorities.${task.priority}`)"
                    >
                        <span class="text-[10px]">{{ priority.icon }}</span>
                        <span class="hidden sm:inline uppercase text-[10px] tracking-wide">{{ task.priority }}</span>
                    </div>
                </div>

                <!-- Title -->
                <h4 class="text-sm font-medium text-gray-900 leading-snug line-clamp-2 group-hover:text-gray-700">
                    {{ task.title }}
                </h4>

                <!-- Subtask progress bar -->
                <div v-if="hasSubtasks" class="mt-2 flex items-center gap-2">
                    <div class="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-300"
                            :class="subtaskProgress.percentage === 100 ? 'bg-green-500' : 'bg-blue-500'"
                            :style="{ width: `${subtaskProgress.percentage}%` }"
                        />
                    </div>
                    <span class="text-[10px] text-gray-500">
                        {{ subtaskProgress.completed }}/{{ subtaskProgress.total }}
                    </span>
                </div>

                <!-- Labels -->
                <div v-if="task.labels?.length" class="flex flex-wrap gap-1 mt-2">
                    <span
                        v-for="label in task.labels.slice(0, 2)"
                        :key="label"
                        class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600"
                    >
                        {{ label }}
                    </span>
                    <span
                        v-if="task.labels.length > 2"
                        class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500"
                    >
                        +{{ task.labels.length - 2 }}
                    </span>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between mt-3 pt-2 border-t border-gray-100">
                    <!-- Left: Project + Time + Due date -->
                    <div class="flex items-center gap-2 text-xs text-gray-400">
                        <span class="font-medium text-gray-500">{{ task.project }}</span>
                        <span v-if="task.estimated_hours" class="flex items-center gap-0.5">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ task.actual_hours || 0 }}/{{ task.estimated_hours }}h
                        </span>
                        <!-- Due date badge -->
                        <span
                            v-if="dueDateStatus"
                            class="flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium"
                            :class="dueDateStatus.class"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            {{ formatDueDate(task.due_date) }}
                        </span>
                    </div>

                    <!-- Right: Attachments + Comments + Assignee -->
                    <div class="flex items-center gap-1.5">
                        <!-- Attachments indicator -->
                        <span v-if="task.attachments_count" class="flex items-center gap-0.5 text-xs text-gray-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                            </svg>
                            {{ task.attachments_count }}
                        </span>

                        <!-- Comments indicator -->
                        <span v-if="task.logs_count" class="flex items-center gap-0.5 text-xs text-gray-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            {{ task.logs_count }}
                        </span>

                        <!-- Assignee avatar -->
                        <div
                            v-if="task.assigned_to"
                            class="w-6 h-6 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-[10px] font-semibold text-white shadow-sm"
                            :title="task.assigned_to.name"
                        >
                            {{ getInitials(task.assigned_to.name) }}
                        </div>
                        <div
                            v-else
                            class="w-6 h-6 rounded-full bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center"
                            :title="t('devTasks.unassigned')"
                        >
                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Selection indicator -->
            <div
                v-if="isSelected"
                class="absolute left-0 top-0 bottom-0 w-1 bg-blue-500 rounded-l-lg"
            />
        </div>
    </div>
</template>

<style scoped>
.task-card {
    transform: translateZ(0);
    will-change: transform, box-shadow;
}

.task-card:active {
    cursor: grabbing;
}

.task-card:hover {
    transform: translateY(-1px);
}
</style>
