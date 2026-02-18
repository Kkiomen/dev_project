import { ref, computed, onMounted, onUnmounted } from 'vue';

/**
 * Editor history composable — command pattern for undo/redo.
 *
 * @param {Function} applyState - callback to apply a state snapshot
 * @param {Function} captureState - callback to capture current state
 */
export function useEditorHistory(applyState, captureState) {
    const undoStack = ref([]);
    const redoStack = ref([]);
    const maxHistory = 50;

    const canUndo = computed(() => undoStack.value.length > 0);
    const canRedo = computed(() => redoStack.value.length > 0);

    function pushState(label = '') {
        const snapshot = captureState();
        undoStack.value.push({ state: snapshot, label });
        if (undoStack.value.length > maxHistory) {
            undoStack.value.shift();
        }
        redoStack.value = [];
    }

    function undo() {
        if (!canUndo.value) return;
        const current = captureState();
        redoStack.value.push({ state: current, label: 'redo' });
        const prev = undoStack.value.pop();
        applyState(prev.state);
    }

    function redo() {
        if (!canRedo.value) return;
        const current = captureState();
        undoStack.value.push({ state: current, label: 'undo' });
        const next = redoStack.value.pop();
        applyState(next.state);
    }

    function clear() {
        undoStack.value = [];
        redoStack.value = [];
    }

    function handleKeyboard(event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'z') {
            event.preventDefault();
            if (event.shiftKey) {
                redo();
            } else {
                undo();
            }
        }
    }

    onMounted(() => {
        window.addEventListener('keydown', handleKeyboard);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeyboard);
    });

    return {
        canUndo,
        canRedo,
        pushState,
        undo,
        redo,
        clear,
    };
}
