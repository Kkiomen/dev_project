<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useGraphicsStore } from '@/stores/graphics';

const props = defineProps({
    template: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['edit-text', 'layer-added']);

const { t } = useI18n();
const graphicsStore = useGraphicsStore();

// Context menu state
const contextMenu = ref({
    visible: false,
    x: 0,
    y: 0,
    layerId: null,
});

const containerRef = ref(null);
const stageRef = ref(null);
const transformerRef = ref(null);
const guidesLayerRef = ref(null);
const editingTextId = ref(null);
const textareaRef = ref(null);
const isDragOver = ref(false);
const isShiftPressed = ref(false);

// Pan and zoom state
const panOffset = ref({ x: 0, y: 0 });
const isPanning = ref(false);
const isSpacePressed = ref(false);
const lastMousePos = ref({ x: 0, y: 0 });

const containerWidth = ref(800);
const containerHeight = ref(600);

// Snapping configuration
const SNAP_THRESHOLD = 8; // pixels
const guides = ref({ vertical: [], horizontal: [] });

// Get snap lines for canvas (edges and center)
const getCanvasSnapLines = () => {
    const width = props.template.width;
    const height = props.template.height;
    return {
        vertical: [0, width / 2, width],     // left, center, right
        horizontal: [0, height / 2, height], // top, center, bottom
    };
};

// Snap a single value to snap lines
const snapValue = (value, snapLines) => {
    for (const line of snapLines) {
        if (Math.abs(value - line) < SNAP_THRESHOLD) {
            return { value: line, snapped: true };
        }
    }
    return { value, snapped: false };
};

// Transformer config with boundBoxFunc for snapping during resize
const transformerConfig = computed(() => ({
    anchorSize: 8,
    borderStroke: '#0066ff',
    anchorFill: '#ffffff',
    anchorStroke: '#0066ff',
    keepRatio: isShiftPressed.value,
    enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'top-center', 'bottom-center', 'middle-left', 'middle-right'],
    boundBoxFunc: (oldBox, newBox) => {
        const snapLines = getCanvasSnapLines();
        const newGuides = { vertical: [], horizontal: [] };

        // Snap left edge
        const leftSnap = snapValue(newBox.x, snapLines.vertical);
        if (leftSnap.snapped) {
            const diff = leftSnap.value - newBox.x;
            newBox.x = leftSnap.value;
            newBox.width -= diff;
            newGuides.vertical.push(leftSnap.value);
        }

        // Snap right edge
        const rightEdge = newBox.x + newBox.width;
        const rightSnap = snapValue(rightEdge, snapLines.vertical);
        if (rightSnap.snapped && !leftSnap.snapped) {
            newBox.width = rightSnap.value - newBox.x;
            newGuides.vertical.push(rightSnap.value);
        }

        // Snap top edge
        const topSnap = snapValue(newBox.y, snapLines.horizontal);
        if (topSnap.snapped) {
            const diff = topSnap.value - newBox.y;
            newBox.y = topSnap.value;
            newBox.height -= diff;
            newGuides.horizontal.push(topSnap.value);
        }

        // Snap bottom edge
        const bottomEdge = newBox.y + newBox.height;
        const bottomSnap = snapValue(bottomEdge, snapLines.horizontal);
        if (bottomSnap.snapped && !topSnap.snapped) {
            newBox.height = bottomSnap.value - newBox.y;
            newGuides.horizontal.push(bottomSnap.value);
        }

        // Snap horizontal center
        const centerX = newBox.x + newBox.width / 2;
        const centerXSnap = snapValue(centerX, snapLines.vertical);
        if (centerXSnap.snapped && !leftSnap.snapped && !rightSnap.snapped) {
            newGuides.vertical.push(centerXSnap.value);
        }

        // Snap vertical center
        const centerY = newBox.y + newBox.height / 2;
        const centerYSnap = snapValue(centerY, snapLines.horizontal);
        if (centerYSnap.snapped && !topSnap.snapped && !bottomSnap.snapped) {
            newGuides.horizontal.push(centerYSnap.value);
        }

        // Update guides
        guides.value = newGuides;

        // Minimum size constraint
        newBox.width = Math.max(5, newBox.width);
        newBox.height = Math.max(5, newBox.height);

        return newBox;
    },
}));

// Calculate stage position to center the canvas
const stageConfig = computed(() => {
    const canvasWidth = props.template.width * graphicsStore.zoom;
    const canvasHeight = props.template.height * graphicsStore.zoom;

    const offsetX = (containerWidth.value - canvasWidth) / 2 + panOffset.value.x;
    const offsetY = (containerHeight.value - canvasHeight) / 2 + panOffset.value.y;

    return {
        width: containerWidth.value,
        height: containerHeight.value,
        x: offsetX,
        y: offsetY,
    };
});

// Handle wheel zoom (Ctrl/Cmd + scroll) and pan (scroll without modifier)
const handleWheel = (e) => {
    e.preventDefault();

    const stage = stageRef.value?.getNode();
    if (!stage) return;

    // Zoom with Ctrl/Cmd or Shift
    if (e.ctrlKey || e.metaKey || e.shiftKey) {
        const scaleBy = 1.1;
        const oldZoom = graphicsStore.zoom;

        // Get pointer position relative to stage
        const pointer = stage.getPointerPosition();
        if (!pointer) return;

        // Calculate mouse position on canvas before zoom
        const mousePointTo = {
            x: (pointer.x - stageConfig.value.x) / oldZoom,
            y: (pointer.y - stageConfig.value.y) / oldZoom,
        };

        // Calculate new zoom
        const direction = e.deltaY > 0 ? -1 : 1;
        const newZoom = direction > 0 ? oldZoom * scaleBy : oldZoom / scaleBy;

        // Clamp zoom between 0.1 and 5
        const clampedZoom = Math.max(0.1, Math.min(5, newZoom));
        graphicsStore.setZoom(clampedZoom);

        // Adjust pan to keep mouse position stable
        const newCanvasWidth = props.template.width * clampedZoom;
        const newCanvasHeight = props.template.height * clampedZoom;
        const baseCenterX = (containerWidth.value - newCanvasWidth) / 2;
        const baseCenterY = (containerHeight.value - newCanvasHeight) / 2;

        panOffset.value = {
            x: pointer.x - baseCenterX - mousePointTo.x * clampedZoom,
            y: pointer.y - baseCenterY - mousePointTo.y * clampedZoom,
        };
    } else {
        // Pan with scroll (no modifier)
        panOffset.value = {
            x: panOffset.value.x - e.deltaX,
            y: panOffset.value.y - e.deltaY,
        };
    }
};

// Handle space key for pan mode
const handleKeyDown = (e) => {
    if (e.code === 'Space' && !isSpacePressed.value) {
        isSpacePressed.value = true;
        if (containerRef.value) {
            containerRef.value.style.cursor = 'grab';
        }
    }
    if (e.shiftKey) {
        isShiftPressed.value = true;
    }
};

const handleKeyUp = (e) => {
    if (e.code === 'Space') {
        isSpacePressed.value = false;
        isPanning.value = false;
        if (containerRef.value) {
            containerRef.value.style.cursor = 'default';
        }
    }
    if (!e.shiftKey) {
        isShiftPressed.value = false;
    }
};

// Mouse events for panning
const handleMouseDown = (e) => {
    // Middle mouse button or Space + left click
    if (e.button === 1 || (e.button === 0 && isSpacePressed.value)) {
        e.preventDefault();
        isPanning.value = true;
        lastMousePos.value = { x: e.clientX, y: e.clientY };
        if (containerRef.value) {
            containerRef.value.style.cursor = 'grabbing';
        }
    }
};

const handleMouseMove = (e) => {
    if (isPanning.value) {
        const deltaX = e.clientX - lastMousePos.value.x;
        const deltaY = e.clientY - lastMousePos.value.y;

        panOffset.value = {
            x: panOffset.value.x + deltaX,
            y: panOffset.value.y + deltaY,
        };

        lastMousePos.value = { x: e.clientX, y: e.clientY };
    }
};

const handleMouseUp = (e) => {
    if (isPanning.value) {
        isPanning.value = false;
        if (containerRef.value) {
            containerRef.value.style.cursor = isSpacePressed.value ? 'grab' : 'default';
        }
    }
};

// Reset pan when zoom is reset
const resetPan = () => {
    panOffset.value = { x: 0, y: 0 };
};

// Watch for zoom reset
watch(() => graphicsStore.zoom, (newZoom, oldZoom) => {
    if (newZoom === 1 && oldZoom !== 1) {
        resetPan();
    }
});

// Calculate snap position
const calculateSnap = (pos, size, snapLines) => {
    let snappedPos = pos;
    let guideLine = null;

    const edges = [pos, pos + size / 2, pos + size]; // left/top, center, right/bottom

    for (let i = 0; i < edges.length; i++) {
        for (const line of snapLines) {
            const diff = Math.abs(edges[i] - line);
            if (diff < SNAP_THRESHOLD) {
                // Snap to this line
                if (i === 0) snappedPos = line;
                else if (i === 1) snappedPos = line - size / 2;
                else snappedPos = line - size;
                guideLine = line;
                return { pos: snappedPos, guide: guideLine };
            }
        }
    }

    return { pos: snappedPos, guide: null };
};

// Update container size on resize
const updateContainerSize = () => {
    if (containerRef.value) {
        containerWidth.value = containerRef.value.clientWidth;
        containerHeight.value = containerRef.value.clientHeight;
    }
};

let resizeObserver = null;

onMounted(() => {
    // Initial size update
    updateContainerSize();

    // Delayed update to ensure layout is complete
    setTimeout(updateContainerSize, 0);
    setTimeout(updateContainerSize, 100);

    window.addEventListener('resize', updateContainerSize);
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('keyup', handleKeyUp);

    // Use ResizeObserver for accurate size tracking
    if (containerRef.value) {
        resizeObserver = new ResizeObserver((entries) => {
            for (const entry of entries) {
                if (entry.contentRect.height > 0) {
                    containerWidth.value = entry.contentRect.width;
                    containerHeight.value = entry.contentRect.height;
                }
            }
        });
        resizeObserver.observe(containerRef.value);
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', updateContainerSize);
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('keyup', handleKeyUp);
    if (resizeObserver) {
        resizeObserver.disconnect();
    }
});

// Update transformer when selection changes
watch(() => graphicsStore.selectedLayerIds, async () => {
    await nextTick();
    updateTransformer();
}, { deep: true });

const updateTransformer = () => {
    if (!transformerRef.value) return;

    const transformer = transformerRef.value.getNode();
    const stage = stageRef.value?.getNode();

    if (!stage || graphicsStore.selectedLayerIds.length === 0) {
        transformer.nodes([]);
        return;
    }

    // Find all selected nodes
    const selectedNodes = graphicsStore.selectedLayerIds
        .map(id => stage.findOne(`#${id}`))
        .filter(Boolean);

    if (selectedNodes.length > 0) {
        transformer.nodes(selectedNodes);
    } else {
        transformer.nodes([]);
    }
};

// Handle stage click for deselection
const handleStageClick = (e) => {
    // Click on empty area
    if (e.target === e.target.getStage()) {
        graphicsStore.deselectLayer();
    }
};

// Handle shape selection
const handleShapeClick = (e, layer) => {
    if (!layer.locked) {
        const addToSelection = e.evt?.shiftKey || false;
        graphicsStore.selectLayer(layer.id, addToSelection);
    }
};

// Handle right-click context menu
const handleContextMenu = (e, layer) => {
    e.evt.preventDefault();

    // Select the layer first
    if (!layer.locked) {
        graphicsStore.selectLayer(layer.id);
    }

    // Get position relative to the container
    const containerRect = containerRef.value?.getBoundingClientRect();
    if (!containerRect) return;

    contextMenu.value = {
        visible: true,
        x: e.evt.clientX - containerRect.left,
        y: e.evt.clientY - containerRect.top,
        layerId: layer.id,
    };
};

// Close context menu
const closeContextMenu = () => {
    contextMenu.value.visible = false;
};

// Context menu actions
const contextMenuCopy = () => {
    graphicsStore.copyLayer();
    closeContextMenu();
};

const contextMenuPaste = () => {
    graphicsStore.pasteLayer();
    closeContextMenu();
};

const contextMenuBringForward = () => {
    graphicsStore.bringForward();
    closeContextMenu();
};

const contextMenuSendBackward = () => {
    graphicsStore.sendBackward();
    closeContextMenu();
};

const contextMenuBringToFront = () => {
    graphicsStore.bringToFront();
    closeContextMenu();
};

const contextMenuSendToBack = () => {
    graphicsStore.sendToBack();
    closeContextMenu();
};

const contextMenuDelete = () => {
    if (contextMenu.value.layerId) {
        graphicsStore.deleteLayer(contextMenu.value.layerId);
    }
    closeContextMenu();
};

// Handle double-click for text editing
const handleTextDblClick = (e, layer) => {
    if (layer.locked) return;

    const textNode = e.target;
    const stage = stageRef.value?.getNode();
    if (!stage) return;

    // Hide text node and transformer
    textNode.hide();
    const transformer = transformerRef.value?.getNode();
    if (transformer) {
        transformer.nodes([]);
    }

    // Get text position
    const textPosition = textNode.absolutePosition();
    const stageBox = stage.container().getBoundingClientRect();

    // Create textarea at the text position
    const textarea = document.createElement('textarea');
    document.body.appendChild(textarea);

    textarea.value = layer.properties?.text || '';
    textarea.style.position = 'absolute';
    textarea.style.top = `${stageBox.top + textPosition.y}px`;
    textarea.style.left = `${stageBox.left + textPosition.x}px`;
    textarea.style.width = `${Math.max(textNode.width() * graphicsStore.zoom, 100)}px`;
    textarea.style.minHeight = `${textNode.height() * graphicsStore.zoom}px`;
    textarea.style.fontSize = `${(layer.properties?.fontSize || 24) * graphicsStore.zoom}px`;
    textarea.style.fontFamily = layer.properties?.fontFamily || 'Arial';
    textarea.style.fontWeight = layer.properties?.fontWeight || 'normal';
    textarea.style.fontStyle = layer.properties?.fontStyle || 'normal';
    textarea.style.color = layer.properties?.fill || '#000000';
    textarea.style.textAlign = layer.properties?.align || 'left';
    textarea.style.lineHeight = String(layer.properties?.lineHeight || 1.2);
    textarea.style.border = '2px solid #0066ff';
    textarea.style.padding = '4px';
    textarea.style.margin = '0';
    textarea.style.overflow = 'hidden';
    textarea.style.background = 'rgba(255, 255, 255, 0.9)';
    textarea.style.outline = 'none';
    textarea.style.resize = 'none';
    textarea.style.zIndex = '1000';
    textarea.style.transformOrigin = 'left top';
    textarea.style.transform = `rotate(${layer.rotation || 0}deg)`;

    textarea.focus();
    textarea.select();

    editingTextId.value = layer.id;

    const finishEditing = () => {
        graphicsStore.updateLayerLocally(layer.id, {
            properties: {
                ...layer.properties,
                text: textarea.value,
            },
        });

        document.body.removeChild(textarea);
        textNode.show();
        editingTextId.value = null;
        updateTransformer();
    };

    textarea.addEventListener('blur', finishEditing);
    textarea.addEventListener('keydown', (evt) => {
        if (evt.key === 'Escape') {
            textarea.blur();
        }
        if (evt.key === 'Enter' && !evt.shiftKey) {
            textarea.blur();
        }
    });
};

// Handle shape transform (resize) with snapping
const handleTransform = (e, layer) => {
    const node = e.target;
    const snapLines = getCanvasSnapLines();

    // Get current bounds
    const x = node.x();
    const y = node.y();
    const width = node.width() * node.scaleX();
    const height = node.height() * node.scaleY();

    // Calculate all edges
    const left = x;
    const right = x + width;
    const top = y;
    const bottom = y + height;
    const centerX = x + width / 2;
    const centerY = y + height / 2;

    // Check snapping for all edges
    const newGuides = { vertical: [], horizontal: [] };

    // Vertical snapping (left, center, right edges)
    for (const line of snapLines.vertical) {
        if (Math.abs(left - line) < SNAP_THRESHOLD) {
            newGuides.vertical.push(line);
        } else if (Math.abs(right - line) < SNAP_THRESHOLD) {
            newGuides.vertical.push(line);
        } else if (Math.abs(centerX - line) < SNAP_THRESHOLD) {
            newGuides.vertical.push(line);
        }
    }

    // Horizontal snapping (top, center, bottom edges)
    for (const line of snapLines.horizontal) {
        if (Math.abs(top - line) < SNAP_THRESHOLD) {
            newGuides.horizontal.push(line);
        } else if (Math.abs(bottom - line) < SNAP_THRESHOLD) {
            newGuides.horizontal.push(line);
        } else if (Math.abs(centerY - line) < SNAP_THRESHOLD) {
            newGuides.horizontal.push(line);
        }
    }

    guides.value = newGuides;
};

// Handle shape transform end
const handleTransformEnd = (e, layer) => {
    const node = e.target;

    // Clear guides
    guides.value = { vertical: [], horizontal: [] };

    graphicsStore.updateLayerLocally(layer.id, {
        x: node.x(),
        y: node.y(),
        width: node.width() * node.scaleX(),
        height: node.height() * node.scaleY(),
        rotation: node.rotation(),
        scale_x: 1,
        scale_y: 1,
    });

    // Reset scale since we applied it to width/height
    node.scaleX(1);
    node.scaleY(1);
};

// Handle drag move with snapping
const handleDragMove = (e, layer) => {
    const node = e.target;
    const snapLines = getCanvasSnapLines();

    // Get current position and size
    const x = node.x();
    const y = node.y();
    const width = node.width() * (node.scaleX() || 1);
    const height = node.height() * (node.scaleY() || 1);

    // Calculate snapped positions
    const snapX = calculateSnap(x, width, snapLines.vertical);
    const snapY = calculateSnap(y, height, snapLines.horizontal);

    // Apply snapped position
    node.x(snapX.pos);
    node.y(snapY.pos);

    // Update guides for visual feedback
    guides.value = {
        vertical: snapX.guide !== null ? [snapX.guide] : [],
        horizontal: snapY.guide !== null ? [snapY.guide] : [],
    };
};

// Handle drag end
const handleDragEnd = (e, layer) => {
    // Clear guides
    guides.value = { vertical: [], horizontal: [] };

    graphicsStore.updateLayerLocally(layer.id, {
        x: e.target.x(),
        y: e.target.y(),
    });
};

// Get shadow config for a layer
const getShadowConfig = (layer) => {
    if (!layer.properties?.shadowEnabled) return {};

    return {
        shadowColor: layer.properties?.shadowColor || '#000000',
        shadowBlur: layer.properties?.shadowBlur ?? 10,
        shadowOffsetX: layer.properties?.shadowOffsetX ?? 5,
        shadowOffsetY: layer.properties?.shadowOffsetY ?? 5,
        shadowOpacity: layer.properties?.shadowOpacity ?? 0.5,
        shadowEnabled: true,
    };
};

// Get gradient config for a layer
const getGradientConfig = (layer) => {
    const gradientType = layer.properties?.gradientType || 'linear';
    const startColor = layer.properties?.gradientStartColor || '#3B82F6';
    const endColor = layer.properties?.gradientEndColor || '#8B5CF6';
    const angle = layer.properties?.gradientAngle || 0;
    const width = layer.width || 100;
    const height = layer.height || 100;

    if (gradientType === 'radial') {
        return {
            fillRadialGradientStartPoint: { x: width / 2, y: height / 2 },
            fillRadialGradientEndPoint: { x: width / 2, y: height / 2 },
            fillRadialGradientStartRadius: 0,
            fillRadialGradientEndRadius: Math.max(width, height) / 2,
            fillRadialGradientColorStops: [0, startColor, 1, endColor],
        };
    } else {
        // Linear gradient - calculate start/end points based on angle
        const angleRad = (angle * Math.PI) / 180;
        const halfWidth = width / 2;
        const halfHeight = height / 2;

        // Calculate gradient line endpoints
        const cos = Math.cos(angleRad);
        const sin = Math.sin(angleRad);
        const length = Math.abs(width * cos) + Math.abs(height * sin);

        return {
            fillLinearGradientStartPoint: {
                x: halfWidth - (cos * length) / 2,
                y: halfHeight - (sin * length) / 2,
            },
            fillLinearGradientEndPoint: {
                x: halfWidth + (cos * length) / 2,
                y: halfHeight + (sin * length) / 2,
            },
            fillLinearGradientColorStops: [0, startColor, 1, endColor],
        };
    }
};

// Get shape config based on layer type
const getShapeConfig = (layer) => {
    const baseConfig = {
        id: layer.id,
        x: layer.x,
        y: layer.y,
        width: layer.width,
        height: layer.height,
        rotation: layer.rotation,
        scaleX: layer.scale_x,
        scaleY: layer.scale_y,
        draggable: !layer.locked,
        visible: layer.visible,
        opacity: layer.opacity ?? 1,
        ...getShadowConfig(layer),
    };

    switch (layer.type) {
        case 'text': {
            // Apply text transform
            let text = layer.properties?.text || '';
            const transform = layer.properties?.textTransform;
            if (transform === 'uppercase') text = text.toUpperCase();
            else if (transform === 'lowercase') text = text.toLowerCase();
            else if (transform === 'capitalize') text = text.replace(/\b\w/g, (c) => c.toUpperCase());

            return {
                ...baseConfig,
                text,
                fontSize: layer.properties?.fontSize || 24,
                fontFamily: layer.properties?.fontFamily || 'Arial',
                fontStyle: `${layer.properties?.fontWeight || 'normal'} ${layer.properties?.fontStyle || 'normal'}`,
                fill: layer.properties?.fill || '#000000',
                align: layer.properties?.align || 'left',
                lineHeight: layer.properties?.lineHeight || 1.2,
                letterSpacing: layer.properties?.letterSpacing || 0,
                textDecoration: layer.properties?.textDecoration || '',
            };
        }

        case 'rectangle': {
            const fillKey = `${layer.id}_fill`;
            const fillImage = fillImages.value[fillKey];
            const fillType = layer.properties?.fillType;
            const useImageFill = fillType === 'image' && fillImage;
            const useGradientFill = fillType === 'gradient';

            const gradientConfig = useGradientFill ? getGradientConfig(layer) : {};

            return {
                ...baseConfig,
                fill: (useImageFill || useGradientFill) ? undefined : (layer.properties?.fill || '#CCCCCC'),
                stroke: layer.properties?.stroke,
                strokeWidth: layer.properties?.strokeWidth || 0,
                cornerRadius: layer.properties?.cornerRadius || 0,
                ...(useImageFill ? getFillPatternConfig(layer, fillImage) : {}),
                ...gradientConfig,
            };
        }

        case 'ellipse': {
            const fillKey = `${layer.id}_fill`;
            const fillImage = fillImages.value[fillKey];
            const fillType = layer.properties?.fillType;
            const useImageFill = fillType === 'image' && fillImage;
            const useGradientFill = fillType === 'gradient';

            const gradientConfig = useGradientFill ? getGradientConfig(layer) : {};

            return {
                ...baseConfig,
                radiusX: (layer.width || 100) / 2,
                radiusY: (layer.height || 100) / 2,
                fill: (useImageFill || useGradientFill) ? undefined : (layer.properties?.fill || '#CCCCCC'),
                stroke: layer.properties?.stroke,
                strokeWidth: layer.properties?.strokeWidth || 0,
                offsetX: -(layer.width || 100) / 2,
                offsetY: -(layer.height || 100) / 2,
                ...(useImageFill ? getFillPatternConfig(layer, fillImage, 'ellipse') : {}),
                ...gradientConfig,
            };
        }

        case 'image':
            return {
                ...baseConfig,
            };

        default:
            return baseConfig;
    }
};

// Export functionality
const exportImage = (options = {}) => {
    if (!stageRef.value) return null;

    const stage = stageRef.value.getNode();
    const pixelRatio = options.pixelRatio || 2;
    const format = options.format || 'image/png';
    const quality = options.quality || 1;

    // Store original values
    const originalStageX = stage.x();
    const originalStageY = stage.y();
    const originalStageWidth = stage.width();
    const originalStageHeight = stage.height();

    // Get all layers and store their original scales
    const layers = stage.getLayers();
    const originalLayerScales = layers.map(layer => ({
        scaleX: layer.scaleX(),
        scaleY: layer.scaleY(),
    }));

    // Hide transformer and guides
    const transformer = transformerRef.value?.getNode();
    if (transformer) {
        transformer.nodes([]);
    }

    // Hide guides layer
    const guidesLayer = guidesLayerRef.value?.getNode();
    const guidesWasVisible = guidesLayer?.visible();
    if (guidesLayer) {
        guidesLayer.visible(false);
    }

    // Reset stage position to origin
    stage.x(0);
    stage.y(0);
    stage.width(props.template.width);
    stage.height(props.template.height);

    // Reset all layer scales to 1:1
    layers.forEach(layer => {
        layer.scaleX(1);
        layer.scaleY(1);
    });

    // Force redraw
    stage.batchDraw();

    // Export at template's actual size
    const dataURL = stage.toDataURL({
        x: 0,
        y: 0,
        width: props.template.width,
        height: props.template.height,
        pixelRatio: pixelRatio,
        mimeType: format,
        quality: quality,
    });

    // Restore original values
    stage.x(originalStageX);
    stage.y(originalStageY);
    stage.width(originalStageWidth);
    stage.height(originalStageHeight);

    // Restore layer scales
    layers.forEach((layer, index) => {
        layer.scaleX(originalLayerScales[index].scaleX);
        layer.scaleY(originalLayerScales[index].scaleY);
    });

    // Restore guides visibility
    if (guidesLayer) {
        guidesLayer.visible(guidesWasVisible);
    }

    // Force redraw to restore view
    stage.batchDraw();

    // Restore transformer
    updateTransformer();

    return dataURL;
};

// Download image
const downloadImage = (options = {}) => {
    const dataURL = exportImage(options);
    if (!dataURL) return;

    const filename = options.filename || `${props.template.name}.png`;
    const link = document.createElement('a');
    link.download = filename;
    link.href = dataURL;
    link.click();
};

// Drag and drop handlers
const handleDragOver = (e) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
    isDragOver.value = true;
};

const handleDragLeave = (e) => {
    // Only set to false if leaving the container (not entering a child)
    if (!containerRef.value?.contains(e.relatedTarget)) {
        isDragOver.value = false;
    }
};

const handleDrop = async (e) => {
    e.preventDefault();
    isDragOver.value = false;

    const toolId = e.dataTransfer.getData('application/x-graphics-tool');
    if (!toolId) return;

    // Calculate drop position in canvas coordinates
    const stage = stageRef.value?.getNode();
    if (!stage) return;

    const stageBox = stage.container().getBoundingClientRect();
    const dropX = (e.clientX - stageBox.left - stageConfig.value.x) / graphicsStore.zoom;
    const dropY = (e.clientY - stageBox.top - stageConfig.value.y) / graphicsStore.zoom;

    // Create layer at drop position
    await addLayerAtPosition(toolId, dropX, dropY);
};

// Add layer at specific position
const addLayerAtPosition = async (type, x, y) => {
    // Default sizes for new shapes
    const defaultSizes = {
        text: { width: 200, height: 40 },
        rectangle: { width: 150, height: 100 },
        ellipse: { width: 120, height: 120 },
    };

    const size = defaultSizes[type] || { width: 100, height: 100 };

    // Center the shape at drop position
    const centeredX = Math.max(0, x - size.width / 2);
    const centeredY = Math.max(0, y - size.height / 2);

    // Create layer via store
    const layer = await graphicsStore.addLayer(type, {
        x: centeredX,
        y: centeredY,
        width: size.width,
        height: size.height,
        properties: type === 'text' ? {
            text: 'Text',
            fontSize: 24,
            fontFamily: 'Arial',
            fill: '#000000',
        } : undefined,
    });

    if (layer) {
        // Select the new layer
        graphicsStore.selectLayer(layer.id);

        // If it's a text layer, start editing immediately
        if (type === 'text') {
            await nextTick();
            // Find the text node and trigger edit mode
            setTimeout(() => {
                triggerTextEdit(layer);
            }, 100);
        }

        emit('layer-added', layer);
    }

    return layer;
};

// Trigger text editing for a layer
const triggerTextEdit = (layer) => {
    const stage = stageRef.value?.getNode();
    if (!stage) return;

    const textNode = stage.findOne(`#${layer.id}`);
    if (!textNode) return;

    // Hide text node and transformer
    textNode.hide();
    const transformer = transformerRef.value?.getNode();
    if (transformer) {
        transformer.nodes([]);
    }

    // Get text position
    const textPosition = textNode.absolutePosition();
    const stageBox = stage.container().getBoundingClientRect();

    // Create textarea at the text position
    const textarea = document.createElement('textarea');
    document.body.appendChild(textarea);

    textarea.value = layer.properties?.text || '';
    textarea.style.position = 'absolute';
    textarea.style.top = `${stageBox.top + textPosition.y}px`;
    textarea.style.left = `${stageBox.left + textPosition.x}px`;
    textarea.style.width = `${Math.max(textNode.width() * graphicsStore.zoom, 100)}px`;
    textarea.style.minHeight = `${textNode.height() * graphicsStore.zoom}px`;
    textarea.style.fontSize = `${(layer.properties?.fontSize || 24) * graphicsStore.zoom}px`;
    textarea.style.fontFamily = layer.properties?.fontFamily || 'Arial';
    textarea.style.fontWeight = layer.properties?.fontWeight || 'normal';
    textarea.style.fontStyle = layer.properties?.fontStyle || 'normal';
    textarea.style.color = layer.properties?.fill || '#000000';
    textarea.style.textAlign = layer.properties?.align || 'left';
    textarea.style.lineHeight = String(layer.properties?.lineHeight || 1.2);
    textarea.style.border = '2px solid #0066ff';
    textarea.style.padding = '4px';
    textarea.style.margin = '0';
    textarea.style.overflow = 'hidden';
    textarea.style.background = 'rgba(255, 255, 255, 0.9)';
    textarea.style.outline = 'none';
    textarea.style.resize = 'none';
    textarea.style.zIndex = '1000';
    textarea.style.transformOrigin = 'left top';
    textarea.style.transform = `rotate(${layer.rotation || 0}deg)`;

    textarea.focus();
    textarea.select();

    editingTextId.value = layer.id;

    const finishEditing = () => {
        graphicsStore.updateLayerLocally(layer.id, {
            properties: {
                ...layer.properties,
                text: textarea.value,
            },
        });

        document.body.removeChild(textarea);
        textNode.show();
        editingTextId.value = null;
        updateTransformer();
    };

    textarea.addEventListener('blur', finishEditing);
    textarea.addEventListener('keydown', (evt) => {
        if (evt.key === 'Escape') {
            textarea.blur();
        }
        if (evt.key === 'Enter' && !evt.shiftKey) {
            textarea.blur();
        }
    });
};

// Get image as Blob
const exportToBlob = async (options = {}) => {
    const dataURL = exportImage(options);
    if (!dataURL) return null;

    const response = await fetch(dataURL);
    return response.blob();
};

// Reset zoom and pan to default
const resetView = () => {
    graphicsStore.setZoom(1);
    resetPan();
};

// Expose functions and refs
defineExpose({
    exportImage,
    downloadImage,
    exportToBlob,
    stageRef,
    getNode: () => stageRef.value?.getNode?.(),
    addLayerAtPosition,
    resetPan,
    resetView,
});

// Background image loading
const backgroundImage = ref(null);
watch(() => props.template.background_image, async (newSrc) => {
    if (newSrc) {
        const img = new window.Image();
        img.crossOrigin = 'anonymous'; // Enable CORS for export
        // Handle both base64 data URLs and storage paths
        img.src = newSrc.startsWith('data:') ? newSrc : `/storage/${newSrc}`;
        img.onload = () => {
            backgroundImage.value = img;
        };
    } else {
        backgroundImage.value = null;
    }
}, { immediate: true });

// Layer images loading
const layerImages = ref({});
watch(() => graphicsStore.layers, (layers) => {
    layers.forEach(layer => {
        if (layer.type === 'image' && layer.properties?.src && !layerImages.value[layer.id]) {
            const img = new window.Image();
            img.crossOrigin = 'anonymous'; // Enable CORS for export
            img.src = layer.properties.src;
            img.onload = () => {
                layerImages.value[layer.id] = img;
            };
        }
    });
}, { immediate: true, deep: true });

// Fill images loading for shapes
const fillImages = ref({});
const fillImageSources = ref({}); // Track sources to detect changes
watch(() => graphicsStore.layers, (layers) => {
    layers.forEach(layer => {
        const fillImage = layer.properties?.fillImage;
        const fillKey = `${layer.id}_fill`;

        if (fillImage) {
            // Check if source changed or image doesn't exist
            if (fillImageSources.value[fillKey] !== fillImage) {
                fillImageSources.value[fillKey] = fillImage;
                const img = new window.Image();
                img.crossOrigin = 'anonymous'; // Enable CORS for export
                img.src = fillImage;
                img.onload = () => {
                    fillImages.value = { ...fillImages.value, [fillKey]: img };
                };
            }
        } else if (fillImages.value[fillKey]) {
            // Remove image if fillImage was cleared
            delete fillImages.value[fillKey];
            delete fillImageSources.value[fillKey];
        }
    });
}, { immediate: true, deep: true });

// Calculate fill pattern config for image fills
const getFillPatternConfig = (layer, image, shapeType = 'rectangle') => {
    if (!image || !layer.width || !layer.height) return {};

    const shapeWidth = layer.width;
    const shapeHeight = layer.height;
    const imgWidth = image.width;
    const imgHeight = image.height;
    const fitMode = layer.properties?.fillFit || 'cover';

    let scaleX = 1;
    let scaleY = 1;
    let patternOffsetX = 0; // Offset within the image (where to start sampling)
    let patternOffsetY = 0;
    let patternX = 0; // Position on the shape where pattern starts
    let patternY = 0;
    let repeat = 'no-repeat';

    // For ellipse, local (0,0) is at the center due to offset
    // We need to position pattern at top-left of bounding box
    const isEllipse = shapeType === 'ellipse';
    if (isEllipse) {
        patternX = -shapeWidth / 2;
        patternY = -shapeHeight / 2;
    }

    switch (fitMode) {
        case 'cover': {
            // Scale to cover entire shape (may crop)
            const scaleToFitWidth = shapeWidth / imgWidth;
            const scaleToFitHeight = shapeHeight / imgHeight;
            const scale = Math.max(scaleToFitWidth, scaleToFitHeight);
            scaleX = scale;
            scaleY = scale;
            // Center the image (offset within image)
            patternOffsetX = (imgWidth * scale - shapeWidth) / 2 / scale;
            patternOffsetY = (imgHeight * scale - shapeHeight) / 2 / scale;
            break;
        }
        case 'contain': {
            // Scale to fit inside shape (may have empty space)
            const scaleToFitWidth = shapeWidth / imgWidth;
            const scaleToFitHeight = shapeHeight / imgHeight;
            const scale = Math.min(scaleToFitWidth, scaleToFitHeight);
            scaleX = scale;
            scaleY = scale;
            // Center the image (negative offset to center smaller image)
            patternOffsetX = -(shapeWidth - imgWidth * scale) / 2 / scale;
            patternOffsetY = -(shapeHeight - imgHeight * scale) / 2 / scale;
            break;
        }
        case 'tile': {
            // Keep original size and tile
            repeat = 'repeat';
            break;
        }
        case 'stretch': {
            // Stretch to match shape exactly
            scaleX = shapeWidth / imgWidth;
            scaleY = shapeHeight / imgHeight;
            break;
        }
    }

    return {
        fillPatternImage: image,
        fillPatternScaleX: scaleX,
        fillPatternScaleY: scaleY,
        fillPatternOffsetX: patternOffsetX,
        fillPatternOffsetY: patternOffsetY,
        fillPatternX: patternX,
        fillPatternY: patternY,
        fillPatternRepeat: repeat,
        fill: undefined, // Clear solid fill when using pattern
    };
};
</script>

<template>
    <div
        ref="containerRef"
        class="w-full h-full relative"
        @dragover="handleDragOver"
        @dragleave="handleDragLeave"
        @drop="handleDrop"
        @wheel.prevent="handleWheel"
        @mousedown="handleMouseDown"
        @mousemove="handleMouseMove"
        @mouseup="handleMouseUp"
        @mouseleave="handleMouseUp"
    >
        <!-- Drop zone indicator -->
        <div
            v-if="isDragOver"
            class="absolute inset-0 z-50 pointer-events-none border-2 border-dashed border-blue-500 bg-blue-500/10 flex items-center justify-center"
        >
            <div class="bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg">
                Drop to add
            </div>
        </div>
        <v-stage
            ref="stageRef"
            :config="stageConfig"
            @click="handleStageClick"
        >
            <!-- Background layer -->
            <v-layer :config="{ scaleX: graphicsStore.zoom, scaleY: graphicsStore.zoom }">
                <!-- Canvas background -->
                <v-rect
                    :config="{
                        x: 0,
                        y: 0,
                        width: template.width,
                        height: template.height,
                        fill: template.background_color || '#FFFFFF',
                        listening: false,
                    }"
                />
                <!-- Background image -->
                <v-image
                    v-if="backgroundImage"
                    :config="{
                        x: 0,
                        y: 0,
                        width: template.width,
                        height: template.height,
                        image: backgroundImage,
                        listening: false,
                    }"
                />
            </v-layer>

            <!-- Content layer -->
            <v-layer :config="{ scaleX: graphicsStore.zoom, scaleY: graphicsStore.zoom }">
                <template v-for="layer in graphicsStore.sortedLayers" :key="layer.id">
                    <!-- Text -->
                    <v-text
                        v-if="layer.type === 'text'"
                        :config="getShapeConfig(layer)"
                        @click="(e) => handleShapeClick(e, layer)"
                        @contextmenu="(e) => handleContextMenu(e, layer)"
                        @dblclick="(e) => handleTextDblClick(e, layer)"
                        @dragmove="(e) => handleDragMove(e, layer)"
                        @dragend="(e) => handleDragEnd(e, layer)"
                        @transform="(e) => handleTransform(e, layer)"
                        @transformend="(e) => handleTransformEnd(e, layer)"
                    />

                    <!-- Rectangle -->
                    <v-rect
                        v-else-if="layer.type === 'rectangle'"
                        :config="getShapeConfig(layer)"
                        @click="(e) => handleShapeClick(e, layer)"
                        @contextmenu="(e) => handleContextMenu(e, layer)"
                        @dragmove="(e) => handleDragMove(e, layer)"
                        @dragend="(e) => handleDragEnd(e, layer)"
                        @transform="(e) => handleTransform(e, layer)"
                        @transformend="(e) => handleTransformEnd(e, layer)"
                    />

                    <!-- Ellipse -->
                    <v-ellipse
                        v-else-if="layer.type === 'ellipse'"
                        :config="getShapeConfig(layer)"
                        @click="(e) => handleShapeClick(e, layer)"
                        @contextmenu="(e) => handleContextMenu(e, layer)"
                        @dragmove="(e) => handleDragMove(e, layer)"
                        @dragend="(e) => handleDragEnd(e, layer)"
                        @transform="(e) => handleTransform(e, layer)"
                        @transformend="(e) => handleTransformEnd(e, layer)"
                    />

                    <!-- Image -->
                    <v-image
                        v-else-if="layer.type === 'image' && layerImages[layer.id]"
                        :config="{
                            ...getShapeConfig(layer),
                            image: layerImages[layer.id],
                        }"
                        @click="(e) => handleShapeClick(e, layer)"
                        @contextmenu="(e) => handleContextMenu(e, layer)"
                        @dragmove="(e) => handleDragMove(e, layer)"
                        @dragend="(e) => handleDragEnd(e, layer)"
                        @transform="(e) => handleTransform(e, layer)"
                        @transformend="(e) => handleTransformEnd(e, layer)"
                    />
                </template>

                <!-- Transformer -->
                <v-transformer
                    ref="transformerRef"
                    :config="transformerConfig"
                />
            </v-layer>

            <!-- Guides layer (for snapping visualization) -->
            <v-layer ref="guidesLayerRef" :config="{ scaleX: graphicsStore.zoom, scaleY: graphicsStore.zoom, listening: false }">
                <!-- Vertical guides -->
                <v-line
                    v-for="(x, index) in guides.vertical"
                    :key="'v-' + index"
                    :config="{
                        points: [x, 0, x, template.height],
                        stroke: '#ff3366',
                        strokeWidth: 1 / graphicsStore.zoom,
                        dash: [4 / graphicsStore.zoom, 4 / graphicsStore.zoom],
                    }"
                />
                <!-- Horizontal guides -->
                <v-line
                    v-for="(y, index) in guides.horizontal"
                    :key="'h-' + index"
                    :config="{
                        points: [0, y, template.width, y],
                        stroke: '#ff3366',
                        strokeWidth: 1 / graphicsStore.zoom,
                        dash: [4 / graphicsStore.zoom, 4 / graphicsStore.zoom],
                    }"
                />
            </v-layer>
        </v-stage>

        <!-- Context Menu -->
        <div
            v-if="contextMenu.visible"
            class="absolute bg-white rounded-lg shadow-xl border border-gray-200 py-1 z-50 min-w-[180px]"
            :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
        >
            <!-- Copy -->
            <button
                @click="contextMenuCopy"
                class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                {{ t('graphics.contextMenu.copy') }}
                <span class="ml-auto text-xs text-gray-400">Ctrl+C</span>
            </button>

            <!-- Paste -->
            <button
                @click="contextMenuPaste"
                :disabled="!graphicsStore.clipboard"
                class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                {{ t('graphics.contextMenu.paste') }}
                <span class="ml-auto text-xs text-gray-400">Ctrl+V</span>
            </button>

            <div class="border-t border-gray-200 my-1"></div>

            <!-- Bring Forward -->
            <button
                @click="contextMenuBringForward"
                class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                </svg>
                {{ t('graphics.contextMenu.bringForward') }}
                <span class="ml-auto text-xs text-gray-400">Ctrl+]</span>
            </button>

            <!-- Send Backward -->
            <button
                @click="contextMenuSendBackward"
                class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
                {{ t('graphics.contextMenu.sendBackward') }}
                <span class="ml-auto text-xs text-gray-400">Ctrl+[</span>
            </button>

            <!-- Bring to Front -->
            <button
                @click="contextMenuBringToFront"
                class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 11l7-7 7 7M5 19l7-7 7 7" />
                </svg>
                {{ t('graphics.contextMenu.bringToFront') }}
                <span class="ml-auto text-xs text-gray-400">Ctrl+Shift+]</span>
            </button>

            <!-- Send to Back -->
            <button
                @click="contextMenuSendToBack"
                class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 13l-7 7-7-7m14-8l-7 7-7-7" />
                </svg>
                {{ t('graphics.contextMenu.sendToBack') }}
                <span class="ml-auto text-xs text-gray-400">Ctrl+Shift+[</span>
            </button>

            <div class="border-t border-gray-200 my-1"></div>

            <!-- Delete -->
            <button
                @click="contextMenuDelete"
                class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                {{ t('graphics.contextMenu.delete') }}
                <span class="ml-auto text-xs text-gray-400">Delete</span>
            </button>
        </div>

        <!-- Click overlay to close context menu -->
        <div
            v-if="contextMenu.visible"
            class="fixed inset-0 z-40"
            @click="closeContextMenu"
            @contextmenu.prevent="closeContextMenu"
        ></div>
    </div>
</template>
