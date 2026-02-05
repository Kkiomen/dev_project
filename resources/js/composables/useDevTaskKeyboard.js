import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useDevTasksStore } from '@/stores/devTasks';

export function useDevTaskKeyboard(options = {}) {
    const {
        onNewTask = () => {},
        onEditTask = () => {},
        onDeleteTask = () => {},
        onOpenHelp = () => {},
        onFocusSearch = () => {},
        onOpenFilters = () => {},
        onAddComment = () => {},
        enabled = ref(true),
    } = options;

    const devTasksStore = useDevTasksStore();

    const tasks = computed(() => devTasksStore.allTasks);
    const selectedTaskIndex = ref(-1);
    const selectedTask = computed(() => {
        if (selectedTaskIndex.value < 0 || selectedTaskIndex.value >= tasks.value.length) {
            return null;
        }
        return tasks.value[selectedTaskIndex.value];
    });

    const selectTask = (task) => {
        const index = tasks.value.findIndex(t => t.id === task.id);
        if (index !== -1) {
            selectedTaskIndex.value = index;
        }
    };

    const selectNextTask = () => {
        if (tasks.value.length === 0) return;

        if (selectedTaskIndex.value < tasks.value.length - 1) {
            selectedTaskIndex.value++;
        } else {
            selectedTaskIndex.value = 0;
        }
    };

    const selectPrevTask = () => {
        if (tasks.value.length === 0) return;

        if (selectedTaskIndex.value > 0) {
            selectedTaskIndex.value--;
        } else {
            selectedTaskIndex.value = tasks.value.length - 1;
        }
    };

    const clearSelection = () => {
        selectedTaskIndex.value = -1;
    };

    const moveTaskToStatus = async (statusIndex) => {
        if (!selectedTask.value) return;

        const statuses = ['backlog', 'in_progress', 'review', 'done'];
        const newStatus = statuses[statusIndex - 1];

        if (newStatus && selectedTask.value.status !== newStatus) {
            await devTasksStore.moveTask(selectedTask.value.id, newStatus);
        }
    };

    const handleKeyDown = (e) => {
        if (!enabled.value) return;

        // Ignore if user is typing in an input
        const target = e.target;
        const isTyping = target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.isContentEditable ||
            target.closest('.ProseMirror');

        // Allow Escape always
        if (e.key === 'Escape') {
            e.preventDefault();
            clearSelection();
            return;
        }

        // Don't handle shortcuts when typing
        if (isTyping) return;

        switch (e.key) {
            case 'j':
                e.preventDefault();
                selectNextTask();
                break;

            case 'k':
                e.preventDefault();
                selectPrevTask();
                break;

            case 'Enter':
                if (selectedTask.value) {
                    e.preventDefault();
                    onEditTask(selectedTask.value);
                }
                break;

            case 'n':
                e.preventDefault();
                onNewTask();
                break;

            case 'e':
                if (selectedTask.value) {
                    e.preventDefault();
                    onEditTask(selectedTask.value);
                }
                break;

            case 'd':
                if (selectedTask.value) {
                    e.preventDefault();
                    onDeleteTask(selectedTask.value);
                }
                break;

            case 'c':
                if (selectedTask.value) {
                    e.preventDefault();
                    onAddComment(selectedTask.value);
                }
                break;

            case '?':
                e.preventDefault();
                onOpenHelp();
                break;

            case '/':
                e.preventDefault();
                onFocusSearch();
                break;

            case 'f':
                e.preventDefault();
                onOpenFilters();
                break;

            case '1':
            case '2':
            case '3':
            case '4':
                if (selectedTask.value) {
                    e.preventDefault();
                    moveTaskToStatus(parseInt(e.key));
                }
                break;
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handleKeyDown);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeyDown);
    });

    return {
        selectedTask,
        selectedTaskIndex,
        selectTask,
        selectNextTask,
        selectPrevTask,
        clearSelection,
    };
}
