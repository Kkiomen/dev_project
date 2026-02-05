import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useDevTasksStore } from '@/stores/devTasks';

export function useTimeTracker() {
    const devTasksStore = useDevTasksStore();

    const elapsedSeconds = ref(0);
    let intervalId = null;

    const activeTimer = computed(() => devTasksStore.activeTimer);

    const isRunning = computed(() => !!activeTimer.value?.is_running);

    const currentTaskId = computed(() => activeTimer.value?.task?.id);

    const formattedElapsed = computed(() => {
        const hours = Math.floor(elapsedSeconds.value / 3600);
        const minutes = Math.floor((elapsedSeconds.value % 3600) / 60);
        const seconds = elapsedSeconds.value % 60;

        if (hours > 0) {
            return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }
        return `${minutes}:${String(seconds).padStart(2, '0')}`;
    });

    const startCounting = () => {
        if (intervalId) clearInterval(intervalId);

        if (activeTimer.value?.started_at) {
            const start = new Date(activeTimer.value.started_at);
            elapsedSeconds.value = Math.floor((Date.now() - start.getTime()) / 1000);
        } else {
            elapsedSeconds.value = 0;
        }

        intervalId = setInterval(() => {
            elapsedSeconds.value++;
        }, 1000);
    };

    const stopCounting = () => {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
        elapsedSeconds.value = 0;
    };

    watch(isRunning, (running) => {
        if (running) {
            startCounting();
        } else {
            stopCounting();
        }
    }, { immediate: true });

    const startTimer = async (taskId) => {
        return await devTasksStore.startTimer(taskId);
    };

    const stopTimer = async () => {
        if (!activeTimer.value) return;
        return await devTasksStore.stopTimer(activeTimer.value.id);
    };

    const fetchActiveTimer = async () => {
        return await devTasksStore.fetchActiveTimer();
    };

    onMounted(() => {
        fetchActiveTimer();
    });

    onUnmounted(() => {
        stopCounting();
    });

    return {
        activeTimer,
        isRunning,
        currentTaskId,
        elapsedSeconds,
        formattedElapsed,
        startTimer,
        stopTimer,
        fetchActiveTimer,
    };
}
