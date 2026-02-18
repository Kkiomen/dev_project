import { computed } from 'vue';
import { useVideoEditorStore } from '@/stores/videoEditorNew';

const SNAP_THRESHOLD_PX = 8; // pixels within which snapping engages

export function useNleTimeline() {
    const store = useVideoEditorStore();

    const zoom = computed(() => store.zoom);

    function timeToPixel(time) {
        return time * store.zoom;
    }

    function pixelToTime(px) {
        return px / store.zoom;
    }

    /**
     * Snap time to grid or element edges.
     * @param {number} time - time to snap
     * @param {string|null} excludeElementId - element being dragged (exclude from snap targets)
     */
    function snapToGrid(time, excludeElementId = null) {
        if (!store.snapEnabled) return time;

        // Collect snap points from all element edges + playhead
        const snapPoints = getSnapPoints(excludeElementId);

        // Check element edge snapping first (higher priority)
        const snapThreshold = SNAP_THRESHOLD_PX / store.zoom; // convert px threshold to time
        let closest = null;
        let closestDist = snapThreshold;

        for (const point of snapPoints) {
            const dist = Math.abs(time - point);
            if (dist < closestDist) {
                closestDist = dist;
                closest = point;
            }
        }

        if (closest !== null) return closest;

        // Fall back to grid snapping
        const grid = getAdaptiveGrid();
        return Math.round(time / grid) * grid;
    }

    /**
     * Collect all element start/end times as snap points.
     */
    function getSnapPoints(excludeElementId = null) {
        const points = new Set();

        // Playhead
        points.add(store.playhead);

        // Element edges from all tracks
        if (store.composition?.tracks) {
            for (const track of store.composition.tracks) {
                for (const el of track.elements) {
                    if (el.id === excludeElementId) continue;
                    const start = el.time || 0;
                    const end = start + (el.duration || 0);
                    points.add(start);
                    points.add(end);
                }
            }
        }

        return Array.from(points);
    }

    function getAdaptiveGrid() {
        const z = store.zoom;
        if (z >= 100) return 0.1;    // 10 fps grid at high zoom
        if (z >= 50) return 0.5;     // half-second grid
        if (z >= 25) return 1.0;     // 1-second grid
        if (z >= 10) return 5.0;     // 5-second grid
        return 10.0;                  // 10-second grid
    }

    function getTickMarks(viewWidth) {
        const grid = getAdaptiveGrid();
        const totalTime = viewWidth / store.zoom;
        const ticks = [];
        for (let t = 0; t <= totalTime; t += grid) {
            ticks.push({
                time: t,
                x: timeToPixel(t),
                label: formatTime(t),
                isMajor: t % (grid * 5) < 0.001 || t === 0,
            });
        }
        return ticks;
    }

    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        const frac = Math.round((seconds % 1) * 10);
        if (mins > 0) {
            return `${mins}:${secs.toString().padStart(2, '0')}.${frac}`;
        }
        return `${secs}.${frac}s`;
    }

    function formatTimecode(seconds) {
        const hrs = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);
        const frames = Math.floor((seconds % 1) * (store.composition?.fps || 30));
        return `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}:${frames.toString().padStart(2, '0')}`;
    }

    return {
        zoom,
        timeToPixel,
        pixelToTime,
        snapToGrid,
        getAdaptiveGrid,
        getTickMarks,
        formatTime,
        formatTimecode,
    };
}
