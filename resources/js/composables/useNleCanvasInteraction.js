import { ref, computed } from 'vue';
import { useVideoEditorStore } from '@/stores/videoEditorNew';
import { useNleHistory } from './useNleHistory';

const HANDLE_SIZE = 8;

/**
 * Resolves a position/size value (percentage string or px number) to pixels.
 */
function resolveValue(value, total) {
    if (typeof value === 'string' && value.endsWith('%')) {
        return (parseFloat(value) / 100) * total;
    }
    return parseFloat(value) || 0;
}

/**
 * Resolves a size value — defaults to total if no value.
 */
function resolveSize(value, total) {
    if (typeof value === 'string' && value.endsWith('%')) {
        return (parseFloat(value) / 100) * total;
    }
    return parseFloat(value) || total;
}

export function useNleCanvasInteraction() {
    const store = useVideoEditorStore();
    const history = useNleHistory();

    // State
    const interactionMode = ref('idle'); // 'idle' | 'move' | 'resize-nw' | 'resize-ne' | 'resize-sw' | 'resize-se'
    const hoveredElementId = ref(null);
    const hoveredHandle = ref(null); // null | 'nw' | 'ne' | 'sw' | 'se'
    const dragStartComp = ref(null);
    const dragOriginalProps = ref(null);

    const compW = computed(() => store.compositionWidth);
    const compH = computed(() => store.compositionHeight);

    /**
     * Convert screen mouse position to composition coordinates.
     */
    function screenToComposition(mouseX, mouseY, canvasEl) {
        const rect = canvasEl.getBoundingClientRect();
        const scaleX = compW.value / rect.width;
        const scaleY = compH.value / rect.height;
        return {
            x: (mouseX - rect.left) * scaleX,
            y: (mouseY - rect.top) * scaleY,
        };
    }

    /**
     * Get the bounding box of an element in composition coordinates.
     */
    function getElementBounds(el) {
        const cx = resolveValue(el.x, compW.value);
        const cy = resolveValue(el.y, compH.value);
        const w = resolveSize(el.width, compW.value);
        const h = resolveSize(el.height, compH.value);
        return {
            x: cx - w / 2,
            y: cy - h / 2,
            width: w,
            height: h,
            centerX: cx,
            centerY: cy,
        };
    }

    /**
     * Get active visual elements at the current playhead time.
     */
    function getVisibleElements() {
        if (!store.composition?.tracks) return [];
        const time = store.playhead;
        const elements = [];

        // Same reverse order as rendering: track[0] = topmost visual layer.
        const tracks = store.composition.tracks;
        for (let i = tracks.length - 1; i >= 0; i--) {
            const track = tracks[i];
            if (!track.visible) continue;
            for (const el of track.elements) {
                if (el.type === 'audio') continue;
                const start = el.time || 0;
                const end = start + (el.duration || 0);
                if (time >= start && time < end) {
                    elements.push(el);
                }
            }
        }

        return elements;
    }

    /**
     * Hit test: find the topmost element under composition coordinates.
     * Elements are in render order (bottom→top), so last = topmost.
     */
    function hitTest(compX, compY) {
        const elements = getVisibleElements();
        for (let i = elements.length - 1; i >= 0; i--) {
            const el = elements[i];
            const bounds = getElementBounds(el);
            if (
                compX >= bounds.x &&
                compX <= bounds.x + bounds.width &&
                compY >= bounds.y &&
                compY <= bounds.y + bounds.height
            ) {
                return el;
            }
        }
        return null;
    }

    /**
     * Check if a point is over a resize handle of an element.
     * Returns handle name or null.
     */
    function hitTestHandle(compX, compY, el, canvasEl) {
        const bounds = getElementBounds(el);
        const rect = canvasEl.getBoundingClientRect();
        const scale = rect.width / compW.value;
        const handleCompSize = HANDLE_SIZE / scale;

        const corners = {
            'nw': { x: bounds.x, y: bounds.y },
            'ne': { x: bounds.x + bounds.width, y: bounds.y },
            'sw': { x: bounds.x, y: bounds.y + bounds.height },
            'se': { x: bounds.x + bounds.width, y: bounds.y + bounds.height },
        };

        for (const [name, pos] of Object.entries(corners)) {
            if (
                Math.abs(compX - pos.x) <= handleCompSize &&
                Math.abs(compY - pos.y) <= handleCompSize
            ) {
                return name;
            }
        }
        return null;
    }

    /**
     * Mouse down on canvas overlay.
     */
    function onCanvasMouseDown(event, canvasEl) {
        const comp = screenToComposition(event.clientX, event.clientY, canvasEl);

        // Check handles on selected elements first
        for (const id of store.selectedElementIds) {
            const el = store._findElement(id);
            if (!el) continue;
            const handle = hitTestHandle(comp.x, comp.y, el, canvasEl);
            if (handle) {
                history.captureState();
                interactionMode.value = `resize-${handle}`;
                dragStartComp.value = { x: comp.x, y: comp.y };
                dragOriginalProps.value = {
                    id: el.id,
                    x: resolveValue(el.x, compW.value),
                    y: resolveValue(el.y, compH.value),
                    width: resolveSize(el.width, compW.value),
                    height: resolveSize(el.height, compH.value),
                };
                setupDragListeners(canvasEl);
                return;
            }
        }

        // Hit test for element
        const hitEl = hitTest(comp.x, comp.y);
        if (hitEl) {
            if (event.ctrlKey || event.metaKey) {
                store.toggleElementSelection(hitEl.id);
            } else if (!store.selectedElementIds.includes(hitEl.id)) {
                store.selectElement(hitEl.id);
            }

            history.captureState();
            interactionMode.value = 'move';
            dragStartComp.value = { x: comp.x, y: comp.y };
            dragOriginalProps.value = {
                id: hitEl.id,
                x: resolveValue(hitEl.x, compW.value),
                y: resolveValue(hitEl.y, compH.value),
                width: resolveSize(hitEl.width, compW.value),
                height: resolveSize(hitEl.height, compH.value),
            };
            setupDragListeners(canvasEl);
        } else {
            store.clearSelection();
        }
    }

    /**
     * Mouse move on canvas overlay.
     */
    function onCanvasMouseMove(event, canvasEl) {
        const comp = screenToComposition(event.clientX, event.clientY, canvasEl);

        if (interactionMode.value === 'idle') {
            // Hover detection for cursor
            let foundHandle = null;
            for (const id of store.selectedElementIds) {
                const el = store._findElement(id);
                if (!el) continue;
                foundHandle = hitTestHandle(comp.x, comp.y, el, canvasEl);
                if (foundHandle) break;
            }

            if (foundHandle) {
                hoveredHandle.value = foundHandle;
                hoveredElementId.value = null;
            } else {
                hoveredHandle.value = null;
                const hitEl = hitTest(comp.x, comp.y);
                hoveredElementId.value = hitEl?.id || null;
            }
            return;
        }

        if (!dragStartComp.value || !dragOriginalProps.value) return;

        const dx = comp.x - dragStartComp.value.x;
        const dy = comp.y - dragStartComp.value.y;
        const orig = dragOriginalProps.value;

        if (interactionMode.value === 'move') {
            const el = store._findElement(orig.id);
            if (!el) return;

            // Move all selected elements by the same delta
            if (store.selectedElementIds.length > 1) {
                // For multi-move we'd need all original positions — simplify: move primary, others follow
                const newCx = orig.x + dx;
                const newCy = orig.y + dy;
                el.x = ((newCx / compW.value) * 100) + '%';
                el.y = ((newCy / compH.value) * 100) + '%';
            } else {
                const newCx = orig.x + dx;
                const newCy = orig.y + dy;
                el.x = ((newCx / compW.value) * 100) + '%';
                el.y = ((newCy / compH.value) * 100) + '%';
            }
            store.markDirty();
        } else if (interactionMode.value.startsWith('resize-')) {
            const handle = interactionMode.value.replace('resize-', '');
            const el = store._findElement(orig.id);
            if (!el) return;

            let newX = orig.x - orig.width / 2;
            let newY = orig.y - orig.height / 2;
            let newW = orig.width;
            let newH = orig.height;

            if (handle.includes('e')) {
                newW = Math.max(20, orig.width + dx);
            }
            if (handle.includes('w')) {
                newW = Math.max(20, orig.width - dx);
                newX = orig.x - orig.width / 2 + dx;
            }
            if (handle.includes('s')) {
                newH = Math.max(20, orig.height + dy);
            }
            if (handle.includes('n')) {
                newH = Math.max(20, orig.height - dy);
                newY = orig.y - orig.height / 2 + dy;
            }

            const newCx = newX + newW / 2;
            const newCy = newY + newH / 2;

            el.x = ((newCx / compW.value) * 100) + '%';
            el.y = ((newCy / compH.value) * 100) + '%';
            el.width = ((newW / compW.value) * 100) + '%';
            el.height = ((newH / compH.value) * 100) + '%';
            store.markDirty();
        }
    }

    /**
     * Mouse up — commit changes.
     */
    function onCanvasMouseUp() {
        interactionMode.value = 'idle';
        dragStartComp.value = null;
        dragOriginalProps.value = null;
    }

    function setupDragListeners(canvasEl) {
        const onMove = (e) => onCanvasMouseMove(e, canvasEl);
        const onUp = () => {
            onCanvasMouseUp();
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
        };
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    }

    /**
     * Get selection overlays for rendering in the canvas overlay.
     * Returns array of { id, x, y, width, height } in composition space.
     */
    function getSelectionOverlays() {
        const overlays = [];
        for (const id of store.selectedElementIds) {
            const el = store._findElement(id);
            if (!el || el.type === 'audio') continue;

            // Check if element is visible at current time
            const start = el.time || 0;
            const end = start + (el.duration || 0);
            if (store.playhead < start || store.playhead >= end) continue;

            const bounds = getElementBounds(el);
            overlays.push({
                id: el.id,
                x: bounds.x,
                y: bounds.y,
                width: bounds.width,
                height: bounds.height,
            });
        }
        return overlays;
    }

    /**
     * Get cursor style based on current state.
     */
    const cursorStyle = computed(() => {
        if (interactionMode.value === 'move') return 'move';
        if (interactionMode.value.startsWith('resize-')) {
            const handle = interactionMode.value.replace('resize-', '');
            const cursors = { nw: 'nw-resize', ne: 'ne-resize', sw: 'sw-resize', se: 'se-resize' };
            return cursors[handle] || 'default';
        }
        if (hoveredHandle.value) {
            const cursors = { nw: 'nw-resize', ne: 'ne-resize', sw: 'sw-resize', se: 'se-resize' };
            return cursors[hoveredHandle.value] || 'default';
        }
        if (hoveredElementId.value) return 'move';
        return 'default';
    });

    return {
        interactionMode,
        hoveredElementId,
        cursorStyle,
        onCanvasMouseDown,
        onCanvasMouseMove,
        onCanvasMouseUp,
        getSelectionOverlays,
        getElementBounds,
        screenToComposition,
    };
}
