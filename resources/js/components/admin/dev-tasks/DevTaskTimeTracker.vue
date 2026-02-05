<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDevTasksStore } from '@/stores/devTasks';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    taskId: { type: String, required: true },
    timeEntries: { type: Array, default: () => [] },
    totalTimeSpent: { type: Number, default: 0 },
});

const emit = defineEmits(['updated']);

const { t } = useI18n();
const devTasksStore = useDevTasksStore();
const toast = useToast();

const activeEntry = computed(() => devTasksStore.activeTimer);
const isRunning = computed(() => activeEntry.value?.task?.id === props.taskId);

const elapsedSeconds = ref(0);
let intervalId = null;

const formattedElapsed = computed(() => {
    const hours = Math.floor(elapsedSeconds.value / 3600);
    const minutes = Math.floor((elapsedSeconds.value % 3600) / 60);
    const seconds = elapsedSeconds.value % 60;

    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

const formattedTotalTime = computed(() => {
    const hours = Math.floor(props.totalTimeSpent / 60);
    const minutes = props.totalTimeSpent % 60;

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
});

const sortedEntries = computed(() => {
    return [...props.timeEntries]
        .filter(e => !e.is_running)
        .sort((a, b) => new Date(b.started_at) - new Date(a.started_at))
        .slice(0, 5);
});

const startTimer = () => {
    if (intervalId) clearInterval(intervalId);
    elapsedSeconds.value = 0;

    if (activeEntry.value?.started_at) {
        const start = new Date(activeEntry.value.started_at);
        elapsedSeconds.value = Math.floor((Date.now() - start.getTime()) / 1000);
    }

    intervalId = setInterval(() => {
        elapsedSeconds.value++;
    }, 1000);
};

const stopTimer = () => {
    if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
    }
};

watch(isRunning, (running) => {
    if (running) {
        startTimer();
    } else {
        stopTimer();
    }
}, { immediate: true });

onMounted(() => {
    devTasksStore.fetchActiveTimer();
});

onUnmounted(() => {
    stopTimer();
});

const handleStart = async () => {
    try {
        await devTasksStore.startTimer(props.taskId);
        emit('updated');
    } catch (error) {
        toast.error(t('devTasks.timeTracking.startError'));
    }
};

const handleStop = async () => {
    if (!activeEntry.value) return;

    try {
        await devTasksStore.stopTimer(activeEntry.value.id);
        emit('updated');
    } catch (error) {
        toast.error(t('devTasks.timeTracking.stopError'));
    }
};

const handleDelete = async (entryId) => {
    try {
        await devTasksStore.deleteTimeEntry(props.taskId, entryId);
        emit('updated');
    } catch (error) {
        toast.error(t('devTasks.timeTracking.deleteError'));
    }
};

const formatEntryDate = (dateString) => {
    const date = new Date(dateString);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    if (date.toDateString() === today.toDateString()) {
        return t('devTasks.timeTracking.today');
    } else if (date.toDateString() === yesterday.toDateString()) {
        return t('devTasks.timeTracking.yesterday');
    } else {
        return date.toLocaleDateString();
    }
};

const formatTime = (dateString) => {
    return new Date(dateString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <div class="time-tracker-panel">
        <!-- Header with total time -->
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm font-medium text-gray-700">
                {{ t('devTasks.timeTracking.title') }}
            </h4>
            <div class="flex items-center gap-2 text-sm">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium text-gray-700">{{ formattedTotalTime }}</span>
            </div>
        </div>

        <!-- Timer control -->
        <div class="bg-gray-50 rounded-lg p-4 mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <div v-if="isRunning" class="flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                        <span class="text-2xl font-mono font-bold text-gray-900">
                            {{ formattedElapsed }}
                        </span>
                    </div>
                    <div v-else class="text-gray-500">
                        {{ t('devTasks.timeTracking.notRunning') }}
                    </div>
                </div>

                <button
                    v-if="isRunning"
                    @click="handleStop"
                    class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors"
                >
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <rect x="5" y="5" width="10" height="10" rx="1" />
                    </svg>
                    {{ t('devTasks.timeTracking.stop') }}
                </button>
                <button
                    v-else
                    @click="handleStart"
                    class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors"
                >
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                    </svg>
                    {{ t('devTasks.timeTracking.start') }}
                </button>
            </div>

            <!-- Warning if timer running on different task -->
            <div
                v-if="activeEntry && !isRunning"
                class="mt-3 p-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700"
            >
                <svg class="w-4 h-4 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                {{ t('devTasks.timeTracking.runningOnOther', { task: activeEntry.task?.identifier }) }}
            </div>
        </div>

        <!-- Recent entries -->
        <div v-if="sortedEntries.length">
            <h5 class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">
                {{ t('devTasks.timeTracking.recentEntries') }}
            </h5>
            <div class="space-y-2">
                <div
                    v-for="entry in sortedEntries"
                    :key="entry.id"
                    class="group flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                >
                    <div class="flex items-center gap-3">
                        <div class="text-xs text-gray-500">
                            {{ formatEntryDate(entry.started_at) }}
                        </div>
                        <div class="text-xs text-gray-400">
                            {{ formatTime(entry.started_at) }} - {{ formatTime(entry.stopped_at) }}
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-700">
                            {{ entry.formatted_duration }}
                        </span>
                        <button
                            @click="handleDelete(entry.id)"
                            class="p-1 text-gray-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition-opacity"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="text-sm text-gray-400 text-center py-4">
            {{ t('devTasks.timeTracking.noEntries') }}
        </div>
    </div>
</template>
