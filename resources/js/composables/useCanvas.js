import { ref, computed, watch } from 'vue';

export function useCanvas(template, containerRef) {
    const zoom = ref(1);
    const panX = ref(0);
    const panY = ref(0);

    const containerWidth = ref(800);
    const containerHeight = ref(600);

    // Update container size
    const updateContainerSize = () => {
        if (containerRef.value) {
            containerWidth.value = containerRef.value.clientWidth;
            containerHeight.value = containerRef.value.clientHeight;
        }
    };

    // Calculate the scale to fit canvas in container
    const fitScale = computed(() => {
        if (!template.value) return 1;

        const padding = 40;
        const availableWidth = containerWidth.value - padding * 2;
        const availableHeight = containerHeight.value - padding * 2;

        const scaleX = availableWidth / template.value.width;
        const scaleY = availableHeight / template.value.height;

        return Math.min(scaleX, scaleY, 1);
    });

    // Calculate canvas position to center it
    const canvasPosition = computed(() => {
        if (!template.value) return { x: 0, y: 0 };

        const canvasWidth = template.value.width * zoom.value;
        const canvasHeight = template.value.height * zoom.value;

        const x = Math.max(0, (containerWidth.value - canvasWidth) / 2) + panX.value;
        const y = Math.max(0, (containerHeight.value - canvasHeight) / 2) + panY.value;

        return { x, y };
    });

    // Zoom controls
    const zoomIn = () => {
        zoom.value = Math.min(zoom.value * 1.2, 5);
    };

    const zoomOut = () => {
        zoom.value = Math.max(zoom.value / 1.2, 0.1);
    };

    const setZoom = (value) => {
        zoom.value = Math.max(0.1, Math.min(5, value));
    };

    const resetZoom = () => {
        zoom.value = 1;
        panX.value = 0;
        panY.value = 0;
    };

    const fitToScreen = () => {
        zoom.value = fitScale.value;
        panX.value = 0;
        panY.value = 0;
    };

    // Pan controls
    const pan = (deltaX, deltaY) => {
        panX.value += deltaX;
        panY.value += deltaY;
    };

    const resetPan = () => {
        panX.value = 0;
        panY.value = 0;
    };

    // Convert screen coordinates to canvas coordinates
    const screenToCanvas = (screenX, screenY) => {
        const pos = canvasPosition.value;
        return {
            x: (screenX - pos.x) / zoom.value,
            y: (screenY - pos.y) / zoom.value,
        };
    };

    // Convert canvas coordinates to screen coordinates
    const canvasToScreen = (canvasX, canvasY) => {
        const pos = canvasPosition.value;
        return {
            x: canvasX * zoom.value + pos.x,
            y: canvasY * zoom.value + pos.y,
        };
    };

    return {
        zoom,
        panX,
        panY,
        containerWidth,
        containerHeight,
        fitScale,
        canvasPosition,
        updateContainerSize,
        zoomIn,
        zoomOut,
        setZoom,
        resetZoom,
        fitToScreen,
        pan,
        resetPan,
        screenToCanvas,
        canvasToScreen,
    };
}
