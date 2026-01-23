import { ref, onMounted, onUnmounted } from 'vue';

export function useResize(onResizeEnd) {
    const resizing = ref(false);
    const resizeTarget = ref(null);
    const resizeStartX = ref(0);
    const resizeStartWidth = ref(0);

    const startResize = (event, target, initialWidth) => {
        resizing.value = true;
        resizeTarget.value = target;
        resizeStartX.value = event.clientX;
        resizeStartWidth.value = initialWidth;
    };

    const onMouseMove = (event) => {
        if (!resizing.value) return;

        const diff = event.clientX - resizeStartX.value;
        const newWidth = Math.max(80, resizeStartWidth.value + diff);

        // Emit intermediate resize for live preview
        if (resizeTarget.value?.onResize) {
            resizeTarget.value.onResize(newWidth);
        }
    };

    const onMouseUp = () => {
        if (!resizing.value) return;

        const diff = resizeStartX.value ? (event?.clientX || 0) - resizeStartX.value : 0;
        const newWidth = Math.max(80, resizeStartWidth.value + diff);

        if (onResizeEnd && resizeTarget.value) {
            onResizeEnd(resizeTarget.value, newWidth);
        }

        resizing.value = false;
        resizeTarget.value = null;
    };

    onMounted(() => {
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });

    onUnmounted(() => {
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
    });

    return {
        resizing,
        startResize,
    };
}
