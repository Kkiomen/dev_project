<script setup>
/**
 * Render Preview Page
 *
 * This page renders a template using the REAL EditorCanvas component.
 * This is the SINGLE SOURCE OF TRUTH - renders exactly what the editor shows.
 *
 * Used by template-renderer service to capture screenshots.
 *
 * URL: /render-preview?data={base64EncodedTemplateJSON}
 */
import { ref, onMounted, nextTick, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useGraphicsStore } from '@/stores/graphics';
import EditorCanvas from '@/components/graphics/EditorCanvas.vue';

const route = useRoute();
const graphicsStore = useGraphicsStore();
const editorCanvasRef = ref(null);
const template = ref(null);
const error = ref(null);
const isReady = ref(false);

// Parse template data from URL and load into store
onMounted(async () => {
    try {
        const dataParam = route.query.data;
        if (!dataParam) {
            error.value = 'No data parameter provided';
            return;
        }

        // Decode base64 JSON
        const jsonString = atob(dataParam);
        const data = JSON.parse(jsonString);

        // Extract template data
        const templateData = data.template || data;
        template.value = {
            id: templateData.id || 'render-preview',
            name: templateData.name || 'Render Preview',
            width: templateData.width || 1080,
            height: templateData.height || 1080,
            background_color: templateData.backgroundColor || templateData.background_color || '#ffffff',
            background_image: templateData.backgroundImage || templateData.background_image || null,
        };

        // Load layers into graphics store
        const layers = templateData.layers || [];
        graphicsStore.setLayers(layers);
        graphicsStore.setZoom(1);

        // Wait for images to load
        await nextTick();
        await waitForImages(layers);

        // Wait a bit more for Konva to render
        await new Promise(resolve => setTimeout(resolve, 500));

        isReady.value = true;

        // Signal that rendering is complete (for Puppeteer)
        await nextTick();
        window.__RENDER_COMPLETE__ = true;

    } catch (e) {
        error.value = e.message;
        console.error('Failed to parse template data:', e);
    }
});

// Wait for all images in layers to load
async function waitForImages(layers) {
    const imagePromises = [];

    function processLayers(layerList) {
        for (const layer of layerList) {
            if (layer.type === 'group' && layer.children) {
                processLayers(layer.children);
            } else if (layer.type === 'image' && layer.properties?.src) {
                const promise = new Promise((resolve) => {
                    const img = new Image();
                    img.onload = () => resolve();
                    img.onerror = () => resolve();
                    img.src = layer.properties.src;
                });
                imagePromises.push(promise);
            }
        }
    }

    processLayers(layers);
    await Promise.all(imagePromises);

    // Additional wait for EditorCanvas internal image loading
    await new Promise(resolve => setTimeout(resolve, 300));
}
</script>

<template>
    <div
        id="render-preview-container"
        :style="{
            width: template?.width + 'px',
            height: template?.height + 'px',
            overflow: 'hidden',
            position: 'relative',
        }"
    >
        <div v-if="error" class="error">{{ error }}</div>

        <!-- Use the REAL EditorCanvas component -->
        <EditorCanvas
            v-else-if="template"
            ref="editorCanvasRef"
            :template="template"
            class="render-canvas"
        />
    </div>
</template>

<style scoped>
#render-preview-container {
    margin: 0;
    padding: 0;
    background: transparent;
}

.render-canvas {
    width: 100% !important;
    height: 100% !important;
}

/* Hide any UI elements that EditorCanvas might show */
.render-canvas :deep(.transformer),
.render-canvas :deep([class*="context-menu"]),
.render-canvas :deep([class*="guide"]) {
    display: none !important;
}

.error {
    color: red;
    padding: 20px;
}
</style>
