import { ref } from 'vue';
import { useVideoEditorStore } from '@/stores/videoEditorNew';

const MAX_HISTORY = 50;

export function useNleHistory() {
    const store = useVideoEditorStore();
    const undoStack = ref([]);
    const redoStack = ref([]);

    function captureState() {
        if (!store.composition) return;
        const snapshot = JSON.stringify(store.composition);

        // Don't capture duplicate states
        if (undoStack.value.length > 0 && undoStack.value[undoStack.value.length - 1] === snapshot) {
            return;
        }

        undoStack.value.push(snapshot);
        if (undoStack.value.length > MAX_HISTORY) {
            undoStack.value.shift();
        }
        redoStack.value = [];
    }

    function undo() {
        if (undoStack.value.length === 0) return;
        const current = JSON.stringify(store.composition);
        redoStack.value.push(current);

        const previous = undoStack.value.pop();
        store.composition = JSON.parse(previous);
        store.markDirty();
    }

    function redo() {
        if (redoStack.value.length === 0) return;
        const current = JSON.stringify(store.composition);
        undoStack.value.push(current);

        const next = redoStack.value.pop();
        store.composition = JSON.parse(next);
        store.markDirty();
    }

    function clear() {
        undoStack.value = [];
        redoStack.value = [];
    }

    const canUndo = () => undoStack.value.length > 0;
    const canRedo = () => redoStack.value.length > 0;

    return {
        captureState,
        undo,
        redo,
        clear,
        canUndo,
        canRedo,
        undoStack,
        redoStack,
    };
}
