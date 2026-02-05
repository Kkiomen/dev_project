<script setup>
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDevTasksStore } from '@/stores/devTasks';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import Modal from '@/components/common/Modal.vue';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    task: { type: Object, default: null },
});

const emit = defineEmits(['close', 'updated', 'deleted']);

const { t } = useI18n();
const devTasksStore = useDevTasksStore();
const { confirm } = useConfirm();
const toast = useToast();

// State
const activeTab = ref('description');
const isEditingTitle = ref(false);
const saving = ref(false);
const triggeringBot = ref(false);
const generatingPlan = ref(false);

// Form data
const form = ref({});

// Comments
const newComment = ref('');
const addingComment = ref(false);

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
        };
        devTasksStore.fetchTaskLogs(newTask.id);
        isEditingTitle.value = false;
        activeTab.value = 'description';
    }
}, { immediate: true });

// Reset state when modal closes
watch(() => props.show, (show) => {
    if (!show) {
        isEditingTitle.value = false;
        newComment.value = '';
    }
});

// Priority config
const priorityConfig = {
    urgent: { icon: '⚡', color: '#DC2626', bg: 'bg-red-50', text: 'text-red-700', border: 'border-red-200' },
    high: { icon: '↑', color: '#F97316', bg: 'bg-orange-50', text: 'text-orange-700', border: 'border-orange-200' },
    medium: { icon: '→', color: '#3B82F6', bg: 'bg-blue-50', text: 'text-blue-700', border: 'border-blue-200' },
    low: { icon: '↓', color: '#6B7280', bg: 'bg-gray-50', text: 'text-gray-600', border: 'border-gray-200' },
};

const statusConfig = {
    backlog: { color: '#64748B', bg: 'bg-slate-100', text: 'text-slate-700' },
    in_progress: { color: '#F59E0B', bg: 'bg-amber-100', text: 'text-amber-700' },
    review: { color: '#3B82F6', bg: 'bg-blue-100', text: 'text-blue-700' },
    done: { color: '#10B981', bg: 'bg-emerald-100', text: 'text-emerald-700' },
};

const priorities = ['urgent', 'high', 'medium', 'low'];
const statuses = ['backlog', 'in_progress', 'review', 'done'];
const tabs = ['description', 'technical', 'plan'];

// Methods
const saveField = async (field) => {
    saving.value = true;
    try {
        await devTasksStore.updateTask(props.task.id, { [field]: form.value[field] });
        if (field === 'title') {
            isEditingTitle.value = false;
        }
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
            activeTab.value = 'plan';
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
    if (!success) return 'text-red-500 bg-red-50 border-red-200';
    const colors = {
        bot_trigger: 'text-purple-500 bg-purple-50 border-purple-200',
        bot_response: 'text-blue-500 bg-blue-50 border-blue-200',
        plan_generation: 'text-amber-500 bg-amber-50 border-amber-200',
        status_change: 'text-green-500 bg-green-50 border-green-200',
        comment: 'text-gray-500 bg-gray-50 border-gray-200',
    };
    return colors[type] || colors.comment;
};

const getActivityBorderColor = (type, success) => {
    if (!success) return 'border-l-red-500';
    const colors = {
        bot_trigger: 'border-l-purple-500',
        bot_response: 'border-l-blue-500',
        plan_generation: 'border-l-amber-500',
        status_change: 'border-l-green-500',
        comment: 'border-l-gray-300',
    };
    return colors[type] || colors.comment;
};

// Check if activity is from bot/AI
const isAiActivity = (type) => ['bot_trigger', 'bot_response', 'plan_generation'].includes(type);
</script>

<template>
    <Modal :show="show && !!task" max-width="5xl" @close="$emit('close')">
        <div v-if="task" class="flex flex-col max-h-[85vh]">
            <!-- Header -->
            <div class="flex-shrink-0 -m-6 mb-0 px-6 py-4 border-b border-gray-200 bg-gray-50/80">
                <div class="flex items-center justify-between gap-4">
                    <!-- Left: ID + Title -->
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <span class="flex-shrink-0 text-sm font-mono font-semibold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-lg">
                            {{ task.identifier }}
                        </span>

                        <!-- Editable title -->
                        <div v-if="isEditingTitle" class="flex-1 flex items-center gap-2">
                            <input
                                v-model="form.title"
                                type="text"
                                class="flex-1 text-lg font-semibold text-gray-900 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500 py-1.5"
                                @keyup.enter="saveField('title')"
                                @keyup.escape="isEditingTitle = false"
                                autofocus
                            />
                            <Button size="sm" :loading="saving" @click="saveField('title')">{{ t('common.save') }}</Button>
                            <Button size="sm" variant="ghost" @click="isEditingTitle = false">{{ t('common.cancel') }}</Button>
                        </div>
                        <h2
                            v-else
                            @click="isEditingTitle = true"
                            class="flex-1 text-lg font-semibold text-gray-900 truncate cursor-pointer hover:text-blue-600 transition-colors"
                            :title="t('common.clickToEdit')"
                        >
                            {{ task.title }}
                        </h2>
                    </div>

                    <!-- Right: Status, Priority, Close -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <!-- Status dropdown -->
                        <select
                            v-model="form.status"
                            @change="handleStatusChange(form.status)"
                            class="text-sm font-medium border-0 rounded-lg py-1.5 pl-3 pr-8 focus:ring-2 focus:ring-blue-500"
                            :class="[statusConfig[form.status]?.bg, statusConfig[form.status]?.text]"
                        >
                            <option v-for="status in statuses" :key="status" :value="status">
                                {{ t(`devTasks.columns.${status}`) }}
                            </option>
                        </select>

                        <!-- Priority dropdown -->
                        <select
                            v-model="form.priority"
                            @change="handlePriorityChange(form.priority)"
                            class="text-sm font-medium border-0 rounded-lg py-1.5 pl-3 pr-8 focus:ring-2 focus:ring-blue-500"
                            :class="[priorityConfig[form.priority]?.bg, priorityConfig[form.priority]?.text]"
                        >
                            <option v-for="p in priorities" :key="p" :value="p">
                                {{ priorityConfig[p].icon }} {{ t(`devTasks.priorities.${p}`) }}
                            </option>
                        </select>

                        <!-- Close button -->
                        <button
                            @click="$emit('close')"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-200 rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main content area (split layout) -->
            <div class="flex-1 flex min-h-0 -mx-6">
                <!-- Left panel (60%) - Content -->
                <div class="w-3/5 flex flex-col border-r border-gray-200">
                    <!-- AI Actions bar -->
                    <div class="flex-shrink-0 p-4 bg-gradient-to-r from-purple-50 via-blue-50 to-cyan-50 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                </svg>
                                {{ t('devTasks.modal.aiActions') }}
                            </div>

                            <div class="flex-1" />

                            <!-- Trigger Bot button -->
                            <button
                                @click="handleTriggerBot"
                                :disabled="triggeringBot"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg transition-all disabled:opacity-50"
                                :class="triggeringBot
                                    ? 'bg-purple-100 text-purple-700'
                                    : 'bg-white text-purple-700 hover:bg-purple-100 border border-purple-200 hover:border-purple-300 shadow-sm'"
                            >
                                <svg v-if="!triggeringBot" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ triggeringBot ? t('devTasks.modal.aiProcessing') : t('devTasks.bot.trigger') }}
                            </button>

                            <!-- Generate Plan button -->
                            <button
                                @click="handleGeneratePlan"
                                :disabled="generatingPlan"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg transition-all disabled:opacity-50"
                                :class="generatingPlan
                                    ? 'bg-amber-100 text-amber-700'
                                    : 'bg-white text-amber-700 hover:bg-amber-100 border border-amber-200 hover:border-amber-300 shadow-sm'"
                            >
                                <svg v-if="!generatingPlan" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                {{ generatingPlan ? t('devTasks.modal.aiProcessing') : t('devTasks.plan.generate') }}
                            </button>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="flex-shrink-0 px-4 border-b border-gray-200 bg-white">
                        <nav class="flex gap-6 -mb-px">
                            <button
                                v-for="tab in tabs"
                                :key="tab"
                                @click="activeTab = tab"
                                class="py-3 text-sm font-medium border-b-2 transition-colors"
                                :class="activeTab === tab
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            >
                                {{ t(`devTasks.sections.${tab}`) }}
                            </button>
                        </nav>
                    </div>

                    <!-- Tab content -->
                    <div class="flex-1 overflow-y-auto p-4 bg-white">
                        <!-- Description tab -->
                        <div v-show="activeTab === 'description'" class="space-y-1">
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ t('devTasks.fields.pmDescription') }}</label>
                            <textarea
                                v-model="form.pm_description"
                                rows="12"
                                class="w-full text-sm border-gray-200 rounded-lg focus:border-blue-500 focus:ring-blue-500 resize-none"
                                :placeholder="t('devTasks.fields.pmDescriptionPlaceholder')"
                                @blur="saveField('pm_description')"
                            />
                        </div>

                        <!-- Technical tab -->
                        <div v-show="activeTab === 'technical'" class="space-y-1">
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ t('devTasks.fields.techDescription') }}</label>
                            <textarea
                                v-model="form.tech_description"
                                rows="12"
                                class="w-full text-sm border-gray-200 rounded-lg focus:border-blue-500 focus:ring-blue-500 resize-none font-mono"
                                :placeholder="t('devTasks.fields.techDescriptionPlaceholder')"
                                @blur="saveField('tech_description')"
                            />
                        </div>

                        <!-- Plan tab -->
                        <div v-show="activeTab === 'plan'" class="space-y-1">
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ t('devTasks.fields.implementationPlan') }}</label>
                            <textarea
                                v-model="form.implementation_plan"
                                rows="12"
                                class="w-full text-sm border-gray-200 rounded-lg focus:border-blue-500 focus:ring-blue-500 resize-none font-mono"
                                :placeholder="t('devTasks.fields.implementationPlanPlaceholder')"
                                @blur="saveField('implementation_plan')"
                            />
                        </div>
                    </div>
                </div>

                <!-- Right panel (40%) - Comments & Activity -->
                <div class="w-2/5 flex flex-col bg-gray-50/50">
                    <!-- Comments section -->
                    <div class="flex-shrink-0 p-4 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ t('devTasks.modal.comments') }}</h3>
                        <div class="space-y-2">
                            <textarea
                                v-model="newComment"
                                rows="2"
                                class="w-full text-sm border-gray-200 rounded-lg focus:border-blue-500 focus:ring-blue-500 resize-none bg-white"
                                :placeholder="t('devTasks.modal.commentPlaceholder')"
                                @keydown.meta.enter="handleAddComment"
                                @keydown.ctrl.enter="handleAddComment"
                            />
                            <div class="flex justify-end">
                                <Button
                                    size="sm"
                                    :loading="addingComment"
                                    :disabled="!newComment.trim()"
                                    @click="handleAddComment"
                                >
                                    {{ t('devTasks.logs.postComment') }}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Activity section -->
                    <div class="flex-1 overflow-y-auto p-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ t('devTasks.sections.activity') }}</h3>

                        <div v-if="devTasksStore.logsLoading" class="text-center py-8 text-gray-400 text-sm">
                            <svg class="w-5 h-5 animate-spin mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ t('common.loading') }}
                        </div>

                        <div v-else-if="!devTasksStore.taskLogs.length" class="text-center py-8">
                            <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <p class="text-sm text-gray-400">{{ t('devTasks.modal.noActivity') }}</p>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="log in devTasksStore.taskLogs"
                                :key="log.id"
                                class="relative pl-4 border-l-2 transition-colors"
                                :class="[
                                    getActivityBorderColor(log.type, log.success),
                                    isAiActivity(log.type) ? 'bg-gradient-to-r from-purple-50/50 to-transparent -ml-4 pl-8 py-2 rounded-r-lg' : ''
                                ]"
                            >
                                <div class="flex items-start gap-2">
                                    <div
                                        class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 border"
                                        :class="getLogColor(log.type, log.success)"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="getLogIcon(log.type)" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 text-xs text-gray-500 mb-0.5">
                                            <span class="font-medium" :class="isAiActivity(log.type) ? 'text-purple-600' : ''">
                                                {{ log.user?.name || (isAiActivity(log.type) ? 'AI Bot' : 'System') }}
                                            </span>
                                            <span>·</span>
                                            <span :title="formatDate(log.created_at)">{{ formatRelativeTime(log.created_at) }}</span>
                                            <span v-if="!log.success" class="text-red-500 font-medium">{{ t('devTasks.logs.failed') }}</span>
                                        </div>
                                        <p v-if="log.content" class="text-sm text-gray-700 whitespace-pre-wrap break-words">{{ log.content }}</p>
                                        <details v-if="log.metadata && log.type !== 'status_change' && log.type !== 'comment'" class="mt-1">
                                            <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600">
                                                {{ t('devTasks.logs.showDetails') }}
                                            </summary>
                                            <pre class="mt-1 text-xs bg-gray-100 rounded p-2 overflow-x-auto text-gray-600">{{ JSON.stringify(log.metadata, null, 2) }}</pre>
                                        </details>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex-shrink-0 -m-6 mt-0 px-6 py-3 border-t border-gray-200 bg-gray-50/80">
                <div class="flex items-center justify-between">
                    <div class="text-xs text-gray-400 space-x-4">
                        <span>{{ t('devTasks.created') }}: {{ formatDate(task.created_at) }}</span>
                        <span v-if="task.updated_at !== task.created_at">{{ t('devTasks.updated') }}: {{ formatDate(task.updated_at) }}</span>
                    </div>
                    <button
                        @click="handleDelete"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        {{ t('devTasks.deleteTask') }}
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
