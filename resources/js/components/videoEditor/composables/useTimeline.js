import { computed } from 'vue';

/**
 * Timeline utility composable — converts time ↔ pixels based on zoom level.
 *
 * @param {import('vue').Ref<number>} zoom - pixelsPerSecond
 * @param {import('vue').Ref<number>} duration - total timeline duration in seconds
 */
export function useTimeline(zoom, duration) {
    const timelineWidth = computed(() => Math.ceil(duration.value * zoom.value));

    function timeToX(seconds) {
        return seconds * zoom.value;
    }

    function xToTime(px) {
        return px / zoom.value;
    }

    /**
     * Snap a time value to the nearest grid point based on zoom level.
     */
    function snapToGrid(time, gridSize = null) {
        const grid = gridSize ?? getGridInterval(zoom.value);
        return Math.round(time / grid) * grid;
    }

    /**
     * Determine tick interval (in seconds) based on zoom level.
     */
    function getGridInterval(pxPerSec) {
        if (pxPerSec >= 200) return 0.1;
        if (pxPerSec >= 100) return 0.5;
        if (pxPerSec >= 50) return 1;
        if (pxPerSec >= 20) return 5;
        if (pxPerSec >= 10) return 10;
        return 30;
    }

    /**
     * Determine major tick interval for labels.
     */
    function getMajorInterval(pxPerSec) {
        if (pxPerSec >= 200) return 1;
        if (pxPerSec >= 100) return 5;
        if (pxPerSec >= 50) return 5;
        if (pxPerSec >= 20) return 10;
        if (pxPerSec >= 10) return 30;
        return 60;
    }

    function formatTime(seconds) {
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60);
        const ms = Math.floor((seconds % 1) * 10);
        if (seconds < 60) return `${s}.${ms}s`;
        return `${m}:${s.toString().padStart(2, '0')}`;
    }

    return {
        timelineWidth,
        timeToX,
        xToTime,
        snapToGrid,
        getGridInterval,
        getMajorInterval,
        formatTime,
    };
}
