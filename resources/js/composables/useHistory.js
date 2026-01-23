import { ref, computed } from 'vue';

export function useHistory(maxSize = 50) {
    const history = ref([]);
    const currentIndex = ref(-1);

    const canUndo = computed(() => currentIndex.value > 0);
    const canRedo = computed(() => currentIndex.value < history.value.length - 1);

    const save = (state) => {
        // Remove any future states if we're not at the end
        if (currentIndex.value < history.value.length - 1) {
            history.value = history.value.slice(0, currentIndex.value + 1);
        }

        // Add new state
        history.value.push(JSON.parse(JSON.stringify(state)));
        currentIndex.value = history.value.length - 1;

        // Limit history size
        if (history.value.length > maxSize) {
            history.value.shift();
            currentIndex.value--;
        }
    };

    const undo = () => {
        if (!canUndo.value) return null;

        currentIndex.value--;
        return JSON.parse(JSON.stringify(history.value[currentIndex.value]));
    };

    const redo = () => {
        if (!canRedo.value) return null;

        currentIndex.value++;
        return JSON.parse(JSON.stringify(history.value[currentIndex.value]));
    };

    const clear = () => {
        history.value = [];
        currentIndex.value = -1;
    };

    const getCurrentState = () => {
        if (currentIndex.value < 0 || currentIndex.value >= history.value.length) {
            return null;
        }
        return JSON.parse(JSON.stringify(history.value[currentIndex.value]));
    };

    return {
        history,
        currentIndex,
        canUndo,
        canRedo,
        save,
        undo,
        redo,
        clear,
        getCurrentState,
    };
}
