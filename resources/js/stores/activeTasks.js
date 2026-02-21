import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { useToast } from '@/composables/useToast';
import i18n from '@/i18n';

// Task types triggered by user action — show success toast
const USER_TRIGGERED_TYPES = new Set([
    'content_generation',
    'post_publishing',
    'image_generation',
    'video_transcription',
    'video_render',
    'video_silence_removal',
    'timeline_export',
    'post_content_generation',
    'content_plan_generation',
    'psd_import',
    'template_classification',
    'thumbnail_generation',
    'pipeline_execution',
    'strategy_generation',
    'sm_content_plan',
    'sm_post_content',
    'weekly_report',
]);

export const useActiveTasksStore = defineStore('activeTasks', () => {
    const tasks = ref([]);

    const hasActiveTasks = computed(() => tasks.value.length > 0);
    const activeCount = computed(() => tasks.value.length);

    const addTask = (task) => {
        // Check if task already exists
        const exists = tasks.value.some((t) => t.task_id === task.task_id);
        if (!exists) {
            tasks.value.push({
                ...task,
                started_at: task.started_at || new Date().toISOString(),
            });
        }
    };

    const removeTask = (taskId) => {
        tasks.value = tasks.value.filter((t) => t.task_id !== taskId);
    };

    const showTaskToast = (task, success, error) => {
        const toast = useToast();
        const { t } = i18n.global;
        const typeKey = task.task_type.replace(/_/g, '.');

        if (success && USER_TRIGGERED_TYPES.has(task.task_type)) {
            const key = `tasks.toast.${typeKey}.success`;
            toast.success(t(key));
        } else if (!success) {
            const key = `tasks.toast.${typeKey}.error`;
            toast.error(t(key, { error: error || '' }));
        }
    };

    const completeTask = (taskId, success, error = null, data = null) => {
        const task = tasks.value.find((t) => t.task_id === taskId);
        if (task) {
            task.completed = true;
            task.success = success;
            task.error = error;
            task.result_data = data;
            task.completed_at = new Date().toISOString();

            showTaskToast(task, success, error);

            // Remove after delay to show completion state
            setTimeout(() => {
                removeTask(taskId);
            }, 3000);
        }
    };

    const setupWebSocket = (userId) => {
        if (!window.Echo || !userId) return;

        window.Echo.private(`user.${userId}`)
            .listen('.task.started', (e) => {
                addTask(e);
            })
            .listen('.task.completed', (e) => {
                completeTask(e.task_id, e.success, e.error, e.data);
            });
    };

    const cleanupWebSocket = (userId) => {
        if (!window.Echo || !userId) return;
        // Note: Don't leave channel here as notifications also use it
    };

    return {
        tasks,
        hasActiveTasks,
        activeCount,
        addTask,
        removeTask,
        completeTask,
        setupWebSocket,
        cleanupWebSocket,
    };
});
