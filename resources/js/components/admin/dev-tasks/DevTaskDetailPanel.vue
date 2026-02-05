<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDevTasksStore } from '@/stores/devTasks';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import Button from '@/components/common/Button.vue';
import DevTaskRichTextEditor from './DevTaskRichTextEditor.vue';
import DevTaskDueDate from './DevTaskDueDate.vue';
import DevTaskSubtasks from './DevTaskSubtasks.vue';
import DevTaskAttachments from './DevTaskAttachments.vue';
import DevTaskTimeTracker from './DevTaskTimeTracker.vue';

const props = defineProps({
    task: { type: Object, required: true },
});

const emit = defineEmits(['close', 'updated', 'deleted']);

const { t } = useI18n();
const devTasksStore = useDevTasksStore();
const { confirm } = useConfirm();
const toast = useToast();

// State
const activeSection = ref('description');
const isEditing = ref({});
const saving = ref(false);
const triggeringBot = ref(false);
const generatingPlan = ref(false);

// Form data
const form = ref({});

// Users for mentions (would be fetched from API in real app)
const users = ref([]);

// Initialize form when task changes
watch(() => props.task, (newTask) => {
    if (newTask) {
        form.value = {
            title: newTask.title || '',
            pm_description: newTask.pm_description || '',
            tech_description: newTask.tech_description || '',
            implementation_plan: newTask.implementation_plan || '',
            status: newTask.status || 'backlog',
            priority: newTask.priority || 'medium',
            labels: [...(newTask.labels || [])],
            estimated_hours: newTask.estimated_hours,
            actual_hours: newTask.actual_hours,
            due_date: newTask.due_date,
        };
        devTasksStore.fetchTaskLogs(newTask.id);
    }
}, { immediate: true });

// Handle due date change
const handleDueDateChange = async (newDate) => {
    form.value.due_date = newDate;
    await saveField('due_date');
};

// Priority config
const priorityConfig = {
    urgent: { icon: '⚡', color: '#DC2626', bg: 'bg-red-50', text: 'text-red-700' },
    high: { icon: '↑', color: '#F97316', bg: 'bg-orange-50', text: 'text-orange-700' },
    medium: { icon: '→', color: '#3B82F6', bg: 'bg-blue-50', text: 'text-blue-700' },
    low: { icon: '↓', color: '#6B7280', bg: 'bg-gray-50', text: 'text-gray-600' },
};

const statusConfig = {
    backlog: { color: '#64748B', bg: 'bg-slate-100' },
    in_progress: { color: '#F59E0B', bg: 'bg-amber-100' },
    review: { color: '#3B82F6', bg: 'bg-blue-100' },
    done: { color: '#10B981', bg: 'bg-emerald-100' },
};

const priorities = ['urgent', 'high', 'medium', 'low'];
const statuses = ['backlog', 'in_progress', 'review', 'done'];

// Methods
const saveField = async (field) => {
    saving.value = true;
    try {
        await devTasksStore.updateTask(props.task.id, { [field]: form.value[field] });
        isEditing.value[field] = false;
        emit('updated');
    } catch (error) {
        console.error('Failed to update:', error);
        toast.error(t('devTasks.updateError'));
    } finally {
        saving.value = false;
    }
};

const handleStatusChange = async (newStatus) => {
    saving.value = true;
    try {
        await devTasksStore.moveTask(props.task.id, newStatus);
        emit('updated');
    } catch (error) {
        console.error('Failed to update status:', error);
        toast.error(t('devTasks.updateError'));
    } finally {
        saving.value = false;
    }
};

const handlePriorityChange = async (newPriority) => {
    form.value.priority = newPriority;
    await saveField('priority');
};

const handleDelete = async () => {
    const confirmed = await confirm({
        title: t('devTasks.deleteTask'),
        message: t('devTasks.deleteTaskConfirm', { identifier: props.task.identifier }),
        confirmText: t('common.delete'),
        variant: 'danger',
    });

    if (confirmed) {
        try {
            await devTasksStore.deleteTask(props.task.id);
            emit('deleted');
        } catch (error) {
            console.error('Failed to delete:', error);
            toast.error(t('devTasks.deleteError'));
        }
    }
};

const handleTriggerBot = async () => {
    triggeringBot.value = true;
    try {
        const result = await devTasksStore.triggerBot(props.task.id);
        if (result.success) {
            toast.success(t('devTasks.bot.triggered'));
        } else {
            toast.error(t('devTasks.bot.failed'));
        }
        devTasksStore.fetchTaskLogs(props.task.id);
    } catch (error) {
        toast.error(t('devTasks.bot.failed'));
    } finally {
        triggeringBot.value = false;
    }
};

const handleGeneratePlan = async () => {
    generatingPlan.value = true;
    try {
        const result = await devTasksStore.generatePlan(props.task.id);
        if (result.success) {
            form.value.implementation_plan = result.task?.implementation_plan || form.value.implementation_plan;
            toast.success(t('devTasks.plan.generated'));
            emit('updated');
        } else {
            toast.error(t('devTasks.plan.failed'));
        }
        devTasksStore.fetchTaskLogs(props.task.id);
    } catch (error) {
        toast.error(t('devTasks.plan.failed'));
    } finally {
        generatingPlan.value = false;
    }
};

// Comments
const newComment = ref('');
const addingComment = ref(false);

const handleAddComment = async () => {
    if (!newComment.value.trim()) return;
    addingComment.value = true;
    try {
        await devTasksStore.addComment(props.task.id, newComment.value.trim());
        newComment.value = '';
    } catch (error) {
        toast.error(t('devTasks.logs.commentError'));
    } finally {
        addingComment.value = false;
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleString();
};

const formatRelativeTime = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (minutes < 1) return t('devTasks.justNow');
    if (minutes < 60) return `${minutes}m`;
    if (hours < 24) return `${hours}h`;
    return `${days}d`;
};

const getLogIcon = (type) => {
    const icons = {
        bot_trigger: 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        bot_response: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z',
        plan_generation: 'M13 10V3L4 14h7v7l9-11h-7z',
        status_change: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        comment: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    };
    return icons[type] || icons.comment;
};

const getLogColor = (type, success) => {
    if (!success) return 'text-red-500 bg-red-50';
    const colors = {
        bot_trigger: 'text-purple-500 bg-purple-50',
        bot_response: 'text-blue-500 bg-blue-50',
        plan_generation: 'text-amber-500 bg-amber-50',
        status_change: 'text-green-500 bg-green-50',
        comment: 'text-gray-500 bg-gray-50',
    };
    return colors[type] || colors.comment;
};
</script>

<template>
    <aside class="w-[480px] xl:w-[560px] flex-shrink-0 bg-white border-l border-gray-200 flex flex-col h-full overflow-hidden">
        <!-- Header -->
        <div class="flex-shrink-0 px-4 py-3 border-b border-gray-200 bg-gray-50/50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="text-sm font-mono font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded">
                        {{ task.identifier }}
                    </span>
                    <span class="text-xs text-gray-400">{{ task.project }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <!-- Actions dropdown -->
                    <button
                        @click="handleTriggerBot"
                        :disabled="triggeringBot"
                        class="p-1.5 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition-colors disabled:opacity-50"
                        :title="t('devTasks.bot.trigger')"
                    >
                        <svg v-if="!triggeringBot" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                    <button
                        @click="handleDelete"
                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                        :title="t('devTasks.deleteTask')"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                    <button
                        @click="$emit('close')"
                        class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Scrollable content -->
        <div class="flex-1 overflow-y-auto">
            <!-- Title -->
            <div class="px-4 py-4 border-b border-gray-100">
                <div v-if="isEditing.title" class="space-y-2">
                    <input
                        v-model="form.title"
                        type="text"
                        class="w-full text-lg font-semibold text-gray-900 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
                        @keyup.enter="saveField('title')"
                        @keyup.escape="isEditing.title = false"
                    />
                    <div class="flex gap-2">
                        <Button size="sm" :loading="saving" @click="saveField('title')">{{ t('common.save') }}</Button>
                        <Button size="sm" variant="ghost" @click="isEditing.title = false">{{ t('common.cancel') }}</Button>
                    </div>
                </div>
                <h2
                    v-else
                    @click="isEditing.title = true"
                    class="text-lg font-semibold text-gray-900 cursor-pointer hover:bg-gray-50 -mx-2 px-2 py-1 rounded-lg transition-colors"
                >
                    {{ task.title }}
                </h2>
            </div>

            <!-- Status & Priority row -->
            <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-4">
                <!-- Status -->
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">{{ t('devTasks.fields.status') }}</label>
                    <select
                        v-model="form.status"
                        @change="handleStatusChange(form.status)"
                        class="w-full text-sm font-medium border-gray-200 rounded-lg focus:border-blue-500 focus:ring-blue-500 py-1.5"
                        :class="statusConfig[form.status]?.bg"
                    >
                        <option v-for="status in statuses" :key="status" :value="status">
                            {{ t(`devTasks.columns.${status}`) }}
                        </option>
                    </select>
                </div>

                <!-- Priority -->
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">{{ t('devTasks.fields.priority') }}</label>
                    <select
                        v-model="form.priority"
                        @change="handlePriorityChange(form.priority)"
                        class="w-full text-sm font-medium border-gray-200 rounded-lg focus:border-blue-500 focus:ring-blue-500 py-1.5"
                        :class="priorityConfig[form.priority]?.bg"
                    >
                        <option v-for="p in priorities" :key="p" :value="p">
                            {{ priorityConfig[p].icon }} {{ t(`devTasks.priorities.${p}`) }}
                        </option>
                    </select>
                </div>

                <!-- Hours -->
                <div class="w-24">
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">{{ t('devTasks.fields.hours') }}</label>
                    <div class="flex items-center gap-1 text-sm">
                        <input
                            v-model.number="form.actual_hours"
                            type="number"
                            min="0"
                            class="w-10 text-center border-gray-200 rounded py-1 text-xs"
                            @change="saveField('actual_hours')"
                        />
                        <span class="text-gray-400">/</span>
                        <input
                            v-model.number="form.estimated_hours"
                            type="number"
                            min="0"
                            class="w-10 text-center border-gray-200 rounded py-1 text-xs"
                            @change="saveField('estimated_hours')"
                        />
                        <span class="text-gray-400 text-xs">h</span>
                    </div>
                </div>
            </div>

            <!-- Due Date -->
            <div class="px-4 py-3 border-b border-gray-100">
                <DevTaskDueDate
                    :model-value="form.due_date"
                    :is-overdue="task.is_overdue"
                    :is-due-soon="task.is_due_soon"
                    @update:model-value="handleDueDateChange"
                />
            </div>

            <!-- Section tabs -->
            <div class="px-4 border-b border-gray-100">
                <nav class="flex gap-4 -mb-px overflow-x-auto">
                    <button
                        v-for="section in ['description', 'technical', 'plan', 'checklist', 'files', 'time', 'activity']"
                        :key="section"
                        @click="activeSection = section"
                        class="py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
                        :class="activeSection === section
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'"
                    >
                        {{ t(`devTasks.sections.${section}`) }}
                        <span
                            v-if="section === 'checklist' && task.subtask_progress?.total > 0"
                            class="ml-1 text-xs"
                            :class="task.subtask_progress.completed === task.subtask_progress.total ? 'text-green-600' : 'text-gray-400'"
                        >
                            {{ task.subtask_progress.completed }}/{{ task.subtask_progress.total }}
                        </span>
                        <span
                            v-if="section === 'files' && task.attachments_count > 0"
                            class="ml-1 text-xs text-gray-400"
                        >
                            {{ task.attachments_count }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Section content -->
            <div class="p-4">
                <!-- Description section -->
                <div v-show="activeSection === 'description'">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-gray-500">{{ t('devTasks.fields.pmDescription') }}</label>
                        <DevTaskRichTextEditor
                            v-model="form.pm_description"
                            :placeholder="t('devTasks.fields.pmDescriptionPlaceholder')"
                            :users="users"
                            min-height="200px"
                            @blur="saveField('pm_description')"
                        />
                    </div>
                </div>

                <!-- Technical section -->
                <div v-show="activeSection === 'technical'">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-gray-500">{{ t('devTasks.fields.techDescription') }}</label>
                        <DevTaskRichTextEditor
                            v-model="form.tech_description"
                            :placeholder="t('devTasks.fields.techDescriptionPlaceholder')"
                            :users="users"
                            min-height="200px"
                            @blur="saveField('tech_description')"
                        />
                    </div>
                </div>

                <!-- Plan section -->
                <div v-show="activeSection === 'plan'">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-medium text-gray-500">{{ t('devTasks.fields.implementationPlan') }}</label>
                        <button
                            @click="handleGeneratePlan"
                            :disabled="generatingPlan"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-amber-700 bg-amber-50 hover:bg-amber-100 rounded-lg transition-colors disabled:opacity-50"
                        >
                            <svg v-if="!generatingPlan" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <svg v-else class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ t('devTasks.plan.generate') }}
                        </button>
                    </div>
                    <DevTaskRichTextEditor
                        v-model="form.implementation_plan"
                        :placeholder="t('devTasks.fields.implementationPlanPlaceholder')"
                        :users="users"
                        min-height="300px"
                        @blur="saveField('implementation_plan')"
                    />
                </div>

                <!-- Checklist section -->
                <div v-show="activeSection === 'checklist'">
                    <DevTaskSubtasks
                        :task-id="task.id"
                        :subtasks="task.subtasks || []"
                        :progress="task.subtask_progress || { total: 0, completed: 0, percentage: 0 }"
                        @updated="$emit('updated')"
                    />
                </div>

                <!-- Files section -->
                <div v-show="activeSection === 'files'">
                    <DevTaskAttachments
                        :task-id="task.id"
                        :attachments="task.attachments || []"
                        @updated="$emit('updated')"
                    />
                </div>

                <!-- Time tracking section -->
                <div v-show="activeSection === 'time'">
                    <DevTaskTimeTracker
                        :task-id="task.id"
                        :time-entries="task.time_entries || []"
                        :total-time-spent="task.total_time_spent || 0"
                        @updated="$emit('updated')"
                    />
                </div>

                <!-- Activity section -->
                <div v-show="activeSection === 'activity'">
                    <!-- Add comment -->
                    <div class="mb-4">
                        <div class="flex gap-2">
                            <textarea
                                v-model="newComment"
                                rows="2"
                                class="flex-1 text-sm border-gray-200 rounded-lg focus:border-blue-500 focus:ring-blue-500 resize-none"
                                :placeholder="t('devTasks.logs.addComment')"
                                @keydown.meta.enter="handleAddComment"
                                @keydown.ctrl.enter="handleAddComment"
                            />
                        </div>
                        <div class="flex justify-end mt-2">
                            <Button size="sm" :loading="addingComment" :disabled="!newComment.trim()" @click="handleAddComment">
                                {{ t('devTasks.logs.postComment') }}
                            </Button>
                        </div>
                    </div>

                    <!-- Activity list -->
                    <div class="space-y-3">
                        <div v-if="devTasksStore.logsLoading" class="text-center py-4 text-gray-400 text-sm">
                            {{ t('common.loading') }}
                        </div>
                        <div v-else-if="!devTasksStore.taskLogs.length" class="text-center py-4 text-gray-400 text-sm">
                            {{ t('devTasks.logs.noLogs') }}
                        </div>
                        <div
                            v-else
                            v-for="log in devTasksStore.taskLogs"
                            :key="log.id"
                            class="flex gap-3"
                        >
                            <div
                                class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0"
                                :class="getLogColor(log.type, log.success)"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="getLogIcon(log.type)" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 text-xs text-gray-500 mb-0.5">
                                    <span class="font-medium">{{ log.user?.name || 'System' }}</span>
                                    <span>·</span>
                                    <span :title="formatDate(log.created_at)">{{ formatRelativeTime(log.created_at) }}</span>
                                    <span v-if="!log.success" class="text-red-500 font-medium">{{ t('devTasks.logs.failed') }}</span>
                                </div>
                                <p v-if="log.content" class="text-sm text-gray-700 whitespace-pre-wrap">{{ log.content }}</p>
                                <details v-if="log.metadata && log.type !== 'status_change' && log.type !== 'comment'" class="mt-1">
                                    <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600">
                                        {{ t('devTasks.logs.showDetails') }}
                                    </summary>
                                    <pre class="mt-1 text-xs bg-gray-50 rounded p-2 overflow-x-auto text-gray-600">{{ JSON.stringify(log.metadata, null, 2) }}</pre>
                                </details>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer with timestamps -->
        <div class="flex-shrink-0 px-4 py-2 border-t border-gray-100 bg-gray-50/50 text-xs text-gray-400">
            <div class="flex items-center justify-between">
                <span>{{ t('devTasks.created') }}: {{ formatDate(task.created_at) }}</span>
                <span v-if="task.updated_at !== task.created_at">{{ t('devTasks.updated') }}: {{ formatDate(task.updated_at) }}</span>
            </div>
        </div>
    </aside>
</template>

<style scoped>
aside {
    box-shadow: -4px 0 6px -1px rgba(0, 0, 0, 0.05);
}
</style>
