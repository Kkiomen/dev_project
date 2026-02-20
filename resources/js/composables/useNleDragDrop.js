import { ref } from 'vue';
import { useVideoEditorStore } from '@/stores/videoEditorNew';
import { useNleTimeline } from './useNleTimeline';
import { useNleHistory } from './useNleHistory';

export function useNleDragDrop() {
    const store = useVideoEditorStore();
    const timeline = useNleTimeline();
    const history = useNleHistory();

    const isDragging = ref(false);
    const dragType = ref(null);     // 'move' | 'trim-start' | 'trim-end' | 'media-drop'
    const dragElementId = ref(null);
    const dragStartX = ref(0);
    const dragStartTime = ref(0);
    const dragStartTimes = ref(new Map()); // id -> startTime for group move
    const dragSourceTrackId = ref(null); // track where drag started

    function startElementDrag(event, elementId, type = 'move') {
        history.captureState();
        isDragging.value = true;
        dragType.value = type;
        dragElementId.value = elementId;
        dragStartX.value = event.clientX;

        const el = store._findElement(elementId);
        if (el) {
            dragStartTime.value = type === 'trim-end' ? el.time + el.duration : el.time;
        }

        // Remember source track for cross-track moves
        const srcTrack = store.composition?.tracks.find(t =>
            t.elements.some(e => e.id === elementId)
        );
        dragSourceTrackId.value = srcTrack?.id || null;

        // Capture start times for all selected elements (group move)
        dragStartTimes.value = new Map();
        if (type === 'move' && store.selectedElementIds.length > 1) {
            for (const id of store.selectedElementIds) {
                const selEl = store._findElement(id);
                if (selEl) {
                    dragStartTimes.value.set(id, selEl.time);
                }
            }
        }

        const onMove = (e) => handleDragMove(e);
        const onUp = () => {
            isDragging.value = false;
            dragType.value = null;
            dragElementId.value = null;
            dragSourceTrackId.value = null;
            dragStartTimes.value = new Map();
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
        };

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    }

    function isTrackCompatible(elementType, trackType) {
        if (elementType === 'audio') return trackType === 'audio';
        return trackType !== 'audio';
    }

    function getTrackIdAtPoint(x, y) {
        const els = document.elementsFromPoint(x, y);
        for (const el of els) {
            if (el.dataset?.trackId) return el.dataset.trackId;
        }
        return null;
    }

    function handleDragMove(event) {
        if (!isDragging.value || !dragElementId.value) return;

        const deltaX = event.clientX - dragStartX.value;
        const deltaTime = timeline.pixelToTime(deltaX);

        if (dragType.value === 'move') {
            // Detect cross-track movement via Y axis
            const hoverTrackId = getTrackIdAtPoint(event.clientX, event.clientY);
            if (hoverTrackId && hoverTrackId !== dragSourceTrackId.value) {
                const el = store._findElement(dragElementId.value);
                const targetTrack = store.composition?.tracks.find(t => t.id === hoverTrackId);
                if (el && targetTrack && isTrackCompatible(el.type, targetTrack.type)) {
                    store.moveElementToTrack(dragElementId.value, hoverTrackId);
                    dragSourceTrackId.value = hoverTrackId;
                }
            }

            // Group move when multiple selected
            if (dragStartTimes.value.size > 1) {
                for (const [id, startTime] of dragStartTimes.value) {
                    const newTime = timeline.snapToGrid(startTime + deltaTime, id);
                    store.moveElement(id, newTime);
                }
            } else {
                const newTime = timeline.snapToGrid(dragStartTime.value + deltaTime, dragElementId.value);
                store.moveElement(dragElementId.value, newTime);
            }
        } else if (dragType.value === 'trim-start') {
            const newTime = timeline.snapToGrid(dragStartTime.value + deltaTime, dragElementId.value);
            store.trimElement(dragElementId.value, 'start', newTime);
        } else if (dragType.value === 'trim-end') {
            const newTime = timeline.snapToGrid(dragStartTime.value + deltaTime, dragElementId.value);
            store.trimElement(dragElementId.value, 'end', newTime);
        }
    }

    function handleMediaDrop(event, trackId) {
        event.preventDefault();

        const mediaData = event.dataTransfer?.getData('application/nle-media');
        if (!mediaData) return;

        try {
            const media = JSON.parse(mediaData);
            const timelineRect = event.currentTarget.getBoundingClientRect();
            const offsetX = event.clientX - timelineRect.left;
            const time = timeline.snapToGrid(timeline.pixelToTime(offsetX));

            history.captureState();
            store.addElement(trackId, {
                type: media.type || 'video',
                name: media.name || 'Dropped Media',
                time,
                duration: media.duration || 5,
                source: media.source || null,
            });
        } catch {
            // Invalid drop data
        }
    }

    function startMediaDrag(event, mediaItem) {
        event.dataTransfer.setData(
            'application/nle-media',
            JSON.stringify(mediaItem)
        );
        event.dataTransfer.effectAllowed = 'copy';
    }

    function handleDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
    }

    return {
        isDragging,
        dragType,
        dragElementId,
        startElementDrag,
        handleMediaDrop,
        startMediaDrag,
        handleDragOver,
    };
}
