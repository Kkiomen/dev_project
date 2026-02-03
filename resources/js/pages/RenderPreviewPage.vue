<script setup>
/**
 * Render Preview Page
 *
 * This page renders a template using the REAL EditorCanvas component.
 * This is the SINGLE SOURCE OF TRUTH - renders exactly what the editor shows.
 *
 * Used by template-renderer service to capture screenshots.
 *
 * URL formats:
 *   /render-preview?key={cacheKey}  - Fetch data from API (preferred, handles large data)
 *   /render-preview?data={base64}   - Legacy: decode from URL (limited by URL length)
 */
import { ref, onMounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import { useGraphicsStore } from '@/stores/graphics';
import EditorCanvas from '@/components/graphics/EditorCanvas.vue';
import axios from 'axios';

const route = useRoute();
const graphicsStore = useGraphicsStore();
const editorCanvasRef = ref(null);
const template = ref(null);
const error = ref(null);
const isReady = ref(false);

// Fetch template data - either from API (key) or decode from URL (data)
async function fetchTemplateData() {
    const keyParam = route.query.key;
    const dataParam = route.query.data;

    if (keyParam) {
        // New method: fetch from API using key
        const response = await axios.get(`/api/v1/render-data/${keyParam}`);
        return response.data;
    } else if (dataParam) {
        // Legacy method: decode base64 from URL
        const jsonString = atob(dataParam);
        return JSON.parse(jsonString);
    } else {
        throw new Error('No key or data parameter provided');
    }
}

// Extract all unique font families from layers
function extractFonts(layers) {
    const fonts = new Set();

    function processLayers(layerList) {
        for (const layer of layerList) {
            if (layer.type === 'group' && layer.children) {
                processLayers(layer.children);
            }
            // Check for fontFamily in text/textbox layers
            if (layer.properties?.fontFamily) {
                fonts.add(layer.properties.fontFamily);
            }
        }
    }

    processLayers(layers);
    return [...fonts];
}

// Load all fonts used in the template - robust loading with CSS + font file verification
async function loadFonts(layers) {
    const fonts = extractFonts(layers);
    console.log('[RenderPreview] Loading fonts:', fonts);

    if (fonts.length === 0) return fonts;

    // Step 1: Ensure font CSS is loaded (add link if not exists)
    for (const font of fonts) {
        const encodedFont = encodeURIComponent(font);
        const existingLink = document.querySelector(`link[href*="${encodedFont}"]`);
        if (!existingLink) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = `https://fonts.googleapis.com/css2?family=${encodedFont}:wght@400;600;700&display=swap`;
            document.head.appendChild(link);
            console.log('[RenderPreview] Added font CSS for:', font);
        } else {
            console.log('[RenderPreview] Font CSS already exists for:', font);
        }
    }

    // Step 2: Wait a moment for CSS to be parsed
    await new Promise(resolve => setTimeout(resolve, 100));

    // Step 3: Force font file download via document.fonts.load()
    // This is the critical step - it ensures actual font binary files are fetched
    console.log('[RenderPreview] Forcing font file download...');
    const fontLoadPromises = [];
    for (const font of fonts) {
        // Load all common weights
        fontLoadPromises.push(document.fonts.load(`400 48px "${font}"`).catch(e => {
            console.warn('[RenderPreview] Failed to load font 400:', font, e);
            return null;
        }));
        fontLoadPromises.push(document.fonts.load(`600 48px "${font}"`).catch(e => null));
        fontLoadPromises.push(document.fonts.load(`700 48px "${font}"`).catch(e => null));
        fontLoadPromises.push(document.fonts.load(`bold 48px "${font}"`).catch(e => null));
    }
    await Promise.all(fontLoadPromises);
    console.log('[RenderPreview] Font files loaded');

    // Step 4: Wait for all fonts to be ready
    await document.fonts.ready;

    // Step 5: Verify fonts are loaded using document.fonts.check
    const status = fonts.map(font => ({
        font,
        check400: document.fonts.check(`400 48px "${font}"`),
        check700: document.fonts.check(`700 48px "${font}"`),
        checkBold: document.fonts.check(`bold 48px "${font}"`),
    }));
    console.log('[RenderPreview] Fonts verification:', JSON.stringify(status));

    // Step 6: Additional wait for browser font rendering engine to be ready
    await new Promise(resolve => setTimeout(resolve, 500));

    return fonts;
}

// Parse template data from URL and load into store
onMounted(async () => {
    try {
        const data = await fetchTemplateData();

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

        const layers = templateData.layers || [];

        // IMPORTANT: Load fonts BEFORE setting layers
        // This ensures Konva renders with correct fonts from the start
        const loadedFonts = await loadFonts(layers);

        // Now set layers - Konva will render with correct fonts
        graphicsStore.setLayers(layers);
        graphicsStore.setZoom(1);

        // Wait for Vue to process the layer changes
        await nextTick();

        // Wait for images to load
        await waitForImages(layers);

        // Wait for Vue/Konva to render
        await nextTick();
        await new Promise(resolve => setTimeout(resolve, 200));

        // Force Konva to redraw all text elements with correct fonts
        // This is critical because Konva may have cached text rendering before fonts were available
        if (editorCanvasRef.value && loadedFonts.length > 0) {
            const stage = editorCanvasRef.value.getNode?.();
            if (stage) {
                console.log('[RenderPreview] Forcing Konva text re-render');
                const textNodes = stage.find('Text');
                console.log('[RenderPreview] Found', textNodes.length, 'text nodes');

                textNodes.forEach(textNode => {
                    const currentFont = textNode.fontFamily();
                    const currentText = textNode.text();
                    console.log('[RenderPreview] Text node:', currentText?.substring(0, 20), 'font:', currentFont);

                    // Force Konva to re-render by:
                    // 1. Temporarily changing fontFamily
                    // 2. Clearing any cached text measurement
                    // 3. Restoring the original fontFamily
                    textNode.fontFamily('Arial');
                    textNode._clearCache?.('textArr');
                    textNode._clearCache?.('textWidth');
                    textNode._clearCache?.('textHeight');
                    textNode.fontFamily(currentFont);
                });

                // Force stage to redraw
                stage.batchDraw();
                console.log('[RenderPreview] Konva batchDraw complete');
            }
        }

        // Wait for the redraw to complete
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

                // Also preload mask if exists
                if (layer.properties?.maskSrc) {
                    const maskPromise = new Promise((resolve) => {
                        const maskImg = new Image();
                        maskImg.onload = () => resolve();
                        maskImg.onerror = () => resolve();
                        maskImg.src = layer.properties.maskSrc;
                    });
                    imagePromises.push(maskPromise);
                }
            }
        }
    }

    processLayers(layers);
    await Promise.all(imagePromises);

    // Wait for EditorCanvas to process images through Konva
    // Large images (2000x2000) need more time
    const imageCount = imagePromises.length;
    const waitTime = Math.max(1000, imageCount * 200);
    await new Promise(resolve => setTimeout(resolve, waitTime));
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
