import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

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

    const completeTask = (taskId, success, error = null, data = null) => {
        const task = tasks.value.find((t) => t.task_id === taskId);
        if (task) {
            task.completed = true;
            task.success = success;
            task.error = error;
            task.result_data = data;
            task.completed_at = new Date().toISOString();

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
