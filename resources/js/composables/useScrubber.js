import { ref, onUnmounted } from 'vue';

/**
 * Composable for scrubby slider functionality on numeric inputs
 * Click and drag left/right to decrease/increase values (like Photoshop)
 */
export function useScrubber(options = {}) {
    const {
        sensitivity = 1,      // How much value changes per pixel
        min = -Infinity,
        max = Infinity,
        step = 1,
        decimals = 0,
    } = options;

    const isDragging = ref(false);
    const startX = ref(0);
    const startValue = ref(0);

    let currentCallback = null;
    let currentElement = null;

    const handleMouseMove = (e) => {
        if (!isDragging.value) return;

        const deltaX = e.clientX - startX.value;
        const deltaValue = deltaX * sensitivity * step;
        let newValue = startValue.value + deltaValue;

        // Apply constraints
        newValue = Math.max(min, Math.min(max, newValue));

        // Round to step/decimals
        if (decimals === 0) {
            newValue = Math.round(newValue / step) * step;
        } else {
            newValue = parseFloat(newValue.toFixed(decimals));
        }

        if (currentCallback) {
            currentCallback(newValue);
        }
    };

    const handleMouseUp = () => {
        isDragging.value = false;
        document.body.style.cursor = '';
        document.body.style.userSelect = '';

        document.removeEventListener('mousemove', handleMouseMove);
        document.removeEventListener('mouseup', handleMouseUp);
    };

    const startScrub = (e, initialValue, callback) => {
        // Only start on left mouse button
        if (e.button !== 0) return;

        isDragging.value = true;
        startX.value = e.clientX;
        startValue.value = parseFloat(initialValue) || 0;
        currentCallback = callback;
        currentElement = e.target;

        document.body.style.cursor = 'ew-resize';
        document.body.style.userSelect = 'none';

        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseup', handleMouseUp);
    };

    onUnmounted(() => {
        document.removeEventListener('mousemove', handleMouseMove);
        document.removeEventListener('mouseup', handleMouseUp);
    });

    return {
        isDragging,
        startScrub,
    };
}

/**
 * Creates scrubber handlers for a specific input configuration
 */
export function createScrubberHandlers(options = {}) {
    const {
        sensitivity = 1,
        min = -Infinity,
        max = Infinity,
        step = 1,
        decimals = 0,
    } = options;

    let isDragging = false;
    let startX = 0;
    let startValue = 0;
    let callback = null;

    const onMouseMove = (e) => {
        if (!isDragging) return;

        const deltaX = e.clientX - startX;
        const deltaValue = deltaX * sensitivity * step;
        let newValue = startValue + deltaValue;

        // Apply constraints
        newValue = Math.max(min, Math.min(max, newValue));

        // Round to step/decimals
        if (decimals === 0) {
            newValue = Math.round(newValue / step) * step;
        } else {
            newValue = parseFloat(newValue.toFixed(decimals));
        }

        if (callback) {
            callback(newValue);
        }
    };

    const onMouseUp = () => {
        isDragging = false;
        document.body.style.cursor = '';
        document.body.style.userSelect = '';

        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
    };

    const onMouseDown = (e, initialValue, updateFn) => {
        if (e.button !== 0) return;

        isDragging = true;
        startX = e.clientX;
        startValue = parseFloat(initialValue) || 0;
        callback = updateFn;

        document.body.style.cursor = 'ew-resize';
        document.body.style.userSelect = 'none';

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    };

    return { onMouseDown };
}
