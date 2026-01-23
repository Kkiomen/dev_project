import { ref } from 'vue';

export function useDragDrop(options = {}) {
    const { onDrop } = options;

    const dragging = ref(false);
    const draggedItem = ref(null);
    const dragOverTarget = ref(null);

    const startDrag = (event, item) => {
        dragging.value = true;
        draggedItem.value = item;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', JSON.stringify(item));
    };

    const onDragOver = (event, target) => {
        event.preventDefault();
        dragOverTarget.value = target;
    };

    const onDragLeave = () => {
        dragOverTarget.value = null;
    };

    const onDropHandler = (event, target) => {
        event.preventDefault();
        dragOverTarget.value = null;

        if (draggedItem.value && onDrop) {
            onDrop(draggedItem.value, target);
        }

        dragging.value = false;
        draggedItem.value = null;
    };

    const endDrag = () => {
        dragging.value = false;
        draggedItem.value = null;
        dragOverTarget.value = null;
    };

    return {
        dragging,
        draggedItem,
        dragOverTarget,
        startDrag,
        onDragOver,
        onDragLeave,
        onDrop: onDropHandler,
        endDrag,
    };
}
