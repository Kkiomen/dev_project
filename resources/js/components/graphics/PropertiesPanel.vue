<script setup>
import { computed, watch, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useGraphicsStore } from '@/stores/graphics';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';
import FontPicker from './FontPicker.vue';
import ScrubberInput from '@/components/common/ScrubberInput.vue';

const { t } = useI18n();
const router = useRouter();
const graphicsStore = useGraphicsStore();
const authStore = useAuthStore();

const selectedLayer = computed(() => graphicsStore.selectedLayer);
const currentTemplate = computed(() => graphicsStore.currentTemplate);

// API docs state
const showApiDocs = ref(false);
const copiedSection = ref(null);

// Get all modifiable layers with their properties
const modifiableLayers = computed(() => {
    return graphicsStore.layers.map(layer => {
        let modifiableProps = [];
        if (layer.type === 'text') {
            modifiableProps = ['text', 'fill', 'fontFamily', 'fontSize'];
        } else if (layer.type === 'textbox') {
            modifiableProps = ['text', 'fill', 'textColor', 'fontFamily', 'fontSize', 'padding', 'cornerRadius'];
        } else if (layer.type === 'image') {
            modifiableProps = ['src'];
        } else if (layer.type === 'rectangle' || layer.type === 'ellipse') {
            if (layer.properties?.fillType === 'image') {
                modifiableProps = ['fillImage', 'fillFit'];
            } else {
                modifiableProps = ['fill', 'stroke'];
            }
        }

        return {
            id: layer.id,
            name: layer.name,
            type: layer.type,
            modifiableProps,
            properties: layer.properties,
        };
    });
});

// Generate complete example modifications object (keyed by layer name)
const exampleModifications = computed(() => {
    const mods = {};
    modifiableLayers.value.forEach(layer => {
        const key = layer.name;

        if (layer.type === 'text') {
            mods[key] = {
                text: 'Your text here',
            };
        } else if (layer.type === 'textbox') {
            mods[key] = {
                text: 'Button Text',
                fill: '#3B82F6',
                textColor: '#FFFFFF',
            };
        } else if (layer.type === 'image') {
            mods[key] = {
                src: 'https://your-image-url.jpg',
            };
        } else if (layer.type === 'rectangle' || layer.type === 'ellipse') {
            if (layer.properties?.fillType === 'image') {
                mods[key] = {
                    fillImage: 'https://your-image-url.jpg',
                    fillFit: 'cover',
                };
            } else {
                mods[key] = {
                    fill: '#FF5733',
                };
            }
        }
    });
    return mods;
});

// API base URL
const apiBaseUrl = computed(() => window.location.origin);

// Template ID (the API uses 'id' which is actually public_id)
const templateId = computed(() => currentTemplate.value?.id);

// Full API endpoints
const endpoints = computed(() => ({
    generate: `${apiBaseUrl.value}/api/v1/templates/${templateId.value}/generate`,
}));

// Generate curl command
const curlCommand = computed(() => {
    const body = JSON.stringify({ modifications: exampleModifications.value, format: 'png', quality: 100, scale: 1 });
    return `curl -X POST "${endpoints.value.generate}" \\
  -H "Content-Type: application/json" \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -d '${body}'`;
});

// Copy to clipboard
const copyToClipboard = async (text, section) => {
    try {
        await navigator.clipboard.writeText(text);
        copiedSection.value = section;
        setTimeout(() => {
            copiedSection.value = null;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

// Convert hex color to rgba with opacity
const hexToRgba = (hex, opacity) => {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${opacity})`;
};

// Local state for text editing (synced with layer)
const localText = ref('');
const isTextFocused = ref(false);

watch(() => selectedLayer.value?.properties?.text, (newText) => {
    // Only update local text if not currently editing (to avoid cursor jumping)
    if (!isTextFocused.value) {
        localText.value = newText || '';
    }
}, { immediate: true });

const updateProperty = (key, value) => {
    if (!selectedLayer.value) return;

    const updates = {
        properties: {
            [key]: value,
        },
    };

    // Note: Text layer auto-resize is handled by EditorCanvas watcher

    // Store handles deep merging of properties
    graphicsStore.updateLayerLocally(selectedLayer.value.id, updates);
};

const updateLayer = (key, value) => {
    if (!selectedLayer.value) return;

    graphicsStore.updateLayerLocally(selectedLayer.value.id, {
        [key]: value,
    });
};

const updateNumeric = (key, value) => {
    if (!selectedLayer.value) return;

    graphicsStore.updateLayerLocally(selectedLayer.value.id, {
        [key]: parseFloat(value) || 0,
    });
};

const updateText = () => {
    if (!selectedLayer.value) return;
    updateProperty('text', localText.value);
};

const onTextFocus = () => {
    isTextFocused.value = true;
};

const onTextBlur = () => {
    isTextFocused.value = false;
};

// Template/Canvas updates
const updateTemplateProperty = (key, value) => {
    if (!currentTemplate.value) return;
    graphicsStore.currentTemplate[key] = value;
    graphicsStore.isDirty = true;
};

// Handle background image upload
const handleBackgroundImageUpload = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        updateTemplateProperty('background_image', e.target.result);
    };
    reader.readAsDataURL(file);
};

const removeBackgroundImage = () => {
    updateTemplateProperty('background_image', null);
};

// Handle fill image upload for shapes
const handleFillImageUpload = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        updateProperty('fillImage', e.target.result);
    };
    reader.readAsDataURL(file);
};

// Handle image replacement for image layers
// Supports linked smart objects - changing one updates all instances
const handleImageReplace = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        const newSrc = e.target.result;

        // Check if this is a linked smart object
        const smartObjectSourceId = selectedLayer.value?.properties?.smartObjectSourceId;

        if (smartObjectSourceId) {
            // Use the linked smart object update method
            const updatedCount = graphicsStore.updateLinkedSmartObjectImage(selectedLayer.value.id, newSrc);
            console.log(`[SmartObject] Image replaced in ${updatedCount} linked layer(s)`);
        } else {
            // Regular image layer - just update this one
            updateProperty('src', newSrc);
        }
    };
    reader.readAsDataURL(file);
};

// Handle image fit mode change
const updateImageFit = (fit) => {
    updateProperty('fit', fit);
};

const fontWeights = [
    { value: 'normal', label: 'Regular' },
    { value: '300', label: 'Light' },
    { value: '500', label: 'Medium' },
    { value: '600', label: 'Semibold' },
    { value: 'bold', label: 'Bold' },
    { value: '900', label: 'Black' },
];

const textAligns = [
    { value: 'left', icon: 'M3 4h18M3 8h12M3 12h18M3 16h8' },
    { value: 'center', icon: 'M3 4h18M6 8h12M3 12h18M8 16h8' },
    { value: 'right', icon: 'M3 4h18M9 8h12M3 12h18M13 16h8' },
];

const textDirections = [
    { value: 'horizontal', icon: 'M4 6h16M4 10h16M4 14h16M4 18h16' },
    { value: 'vertical', icon: 'M6 4v16M10 4v16M14 4v16M18 4v16' },
];

const textTransforms = [
    { value: 'none', label: 'Aa' },
    { value: 'uppercase', label: 'AA' },
    { value: 'lowercase', label: 'aa' },
    { value: 'capitalize', label: 'Aa' },
];

// Toggle text decoration (underline, line-through)
const toggleTextDecoration = (decoration) => {
    const current = selectedLayer.value?.properties?.textDecoration || '';
    const decorations = current.split(' ').filter(Boolean);

    if (decorations.includes(decoration)) {
        // Remove decoration
        const newDecorations = decorations.filter(d => d !== decoration);
        return newDecorations.length > 0 ? newDecorations.join(' ') : '';
    } else {
        // Add decoration
        decorations.push(decoration);
        return decorations.join(' ');
    }
};

// Semantic tags for template preview (admin only)
const semanticTagOptions = computed(() => {
    const layerType = selectedLayer.value?.type;
    if (!layerType) return [];

    const textTags = [
        { value: 'header', label: t('graphics.semantic_tags.header') },
        { value: 'subtitle', label: t('graphics.semantic_tags.subtitle') },
        { value: 'paragraph', label: t('graphics.semantic_tags.paragraph') },
        { value: 'social_handle', label: t('graphics.semantic_tags.social_handle') },
    ];

    const colorTags = [
        { value: 'primary_color', label: t('graphics.semantic_tags.primary_color') },
        { value: 'secondary_color', label: t('graphics.semantic_tags.secondary_color') },
    ];

    const textColorTags = [
        { value: 'text_primary_color', label: t('graphics.semantic_tags.text_primary_color') },
        { value: 'text_secondary_color', label: t('graphics.semantic_tags.text_secondary_color') },
    ];

    const imageTags = [
        { value: 'main_image', label: t('graphics.semantic_tags.main_image') },
    ];

    if (layerType === 'text' || layerType === 'textbox') {
        return [...textTags, ...colorTags, ...textColorTags];
    } else if (layerType === 'rectangle' || layerType === 'ellipse') {
        return colorTags;
    } else if (layerType === 'image') {
        return imageTags;
    }

    return [];
});

const currentSemanticTag = computed(() => selectedLayer.value?.properties?.semanticTag || '');

const updateSemanticTag = (tag) => {
    updateProperty('semanticTag', tag || null);
};

// Create template from group (admin only)
const creatingTemplate = ref(false);
const isGroupLayer = computed(() => selectedLayer.value?.type === 'group');

const createTemplateFromGroup = async (addToLibrary = false) => {
    if (!selectedLayer.value || selectedLayer.value.type !== 'group') return;

    creatingTemplate.value = true;
    try {
        const response = await axios.post(`/api/v1/layers/${selectedLayer.value.id}/create-template`, {
            name: selectedLayer.value.name,
            add_to_library: addToLibrary,
        });

        // Navigate to the new template
        if (response.data.data?.id) {
            router.push({ name: 'template.editor', params: { templateId: response.data.data.id } });
        }
    } catch (error) {
        console.error('Failed to create template from group:', error);
        alert(error.response?.data?.message || 'Failed to create template');
    } finally {
        creatingTemplate.value = false;
    }
};
</script>

<template>
    <div class="flex flex-col h-full text-xs bg-white">
        <!-- Header -->
        <div class="px-3 py-2.5 border-b border-gray-200 flex items-center justify-between">
            <span class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                {{ selectedLayer ? t('graphics.layers.properties') : t('graphics.canvas.title') }}
            </span>
        </div>

        <!-- Canvas properties (when no layer selected) -->
        <div v-if="!selectedLayer && currentTemplate" class="flex-1 overflow-y-auto">
            <!-- Canvas info -->
            <div class="px-3 py-4 border-b border-gray-200">
                <div class="flex items-center gap-2.5 mb-4">
                    <div class="w-8 h-8 flex items-center justify-center bg-purple-50 text-purple-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ currentTemplate.name }}</div>
                        <div class="text-[10px] text-gray-500">{{ currentTemplate.width }} × {{ currentTemplate.height }} px</div>
                    </div>
                </div>

                <!-- Canvas Size -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">{{ t('graphics.canvas.size') }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[10px] text-gray-500 mb-1">{{ t('graphics.canvas.width') }}</label>
                            <input
                                :value="currentTemplate.width"
                                @input="updateTemplateProperty('width', parseInt($event.target.value) || 1080)"
                                type="number"
                                min="100"
                                max="4096"
                                class="w-full px-2.5 py-2 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-500 mb-1">{{ t('graphics.canvas.height') }}</label>
                            <input
                                :value="currentTemplate.height"
                                @input="updateTemplateProperty('height', parseInt($event.target.value) || 1080)"
                                type="number"
                                min="100"
                                max="4096"
                                class="w-full px-2.5 py-2 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white transition-colors"
                            />
                        </div>
                    </div>

                    <!-- Preset sizes -->
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            @click="updateTemplateProperty('width', 1080); updateTemplateProperty('height', 1080)"
                            :class="[
                                'px-2 py-1 text-[10px] rounded transition-colors',
                                currentTemplate.width === 1080 && currentTemplate.height === 1080
                                    ? 'bg-blue-100 text-blue-700'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            ]"
                        >
                            1080×1080
                        </button>
                        <button
                            @click="updateTemplateProperty('width', 1080); updateTemplateProperty('height', 1920)"
                            :class="[
                                'px-2 py-1 text-[10px] rounded transition-colors',
                                currentTemplate.width === 1080 && currentTemplate.height === 1920
                                    ? 'bg-blue-100 text-blue-700'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            ]"
                        >
                            1080×1920
                        </button>
                        <button
                            @click="updateTemplateProperty('width', 1200); updateTemplateProperty('height', 630)"
                            :class="[
                                'px-2 py-1 text-[10px] rounded transition-colors',
                                currentTemplate.width === 1200 && currentTemplate.height === 630
                                    ? 'bg-blue-100 text-blue-700'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            ]"
                        >
                            1200×630
                        </button>
                        <button
                            @click="updateTemplateProperty('width', 1280); updateTemplateProperty('height', 720)"
                            :class="[
                                'px-2 py-1 text-[10px] rounded transition-colors',
                                currentTemplate.width === 1280 && currentTemplate.height === 720
                                    ? 'bg-blue-100 text-blue-700'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            ]"
                        >
                            1280×720
                        </button>
                    </div>
                </div>
            </div>

            <!-- Background color -->
            <div class="px-3 py-4 border-b border-gray-200">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                    </svg>
                    <span class="text-xs font-medium text-gray-900">
                        {{ t('graphics.canvas.backgroundColor') }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        :value="currentTemplate.background_color || '#FFFFFF'"
                        @input="updateTemplateProperty('background_color', $event.target.value)"
                        type="color"
                        class="w-9 h-9 rounded cursor-pointer border border-gray-300"
                        style="padding: 2px;"
                    />
                    <input
                        :value="currentTemplate.background_color || '#FFFFFF'"
                        @input="updateTemplateProperty('background_color', $event.target.value)"
                        type="text"
                        class="flex-1 px-2.5 py-2 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white uppercase transition-colors font-mono"
                    />
                </div>
            </div>

            <!-- Background image -->
            <div class="px-3 py-4 border-b border-gray-200">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-xs font-medium text-gray-900">
                        {{ t('graphics.canvas.backgroundImage') }}
                    </span>
                </div>

                <!-- Current background image preview -->
                <div v-if="currentTemplate.background_image" class="mb-2">
                    <div class="relative rounded-lg overflow-hidden border border-gray-200">
                        <img
                            :src="currentTemplate.background_image"
                            alt="Background"
                            class="w-full h-24 object-cover"
                        />
                        <button
                            @click="removeBackgroundImage"
                            class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors"
                            title="Remove"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Upload button -->
                <label class="flex items-center justify-center gap-2 px-3 py-2.5 bg-gray-50 border border-gray-200 border-dashed rounded-lg cursor-pointer hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    <span class="text-xs text-gray-600">{{ t('graphics.canvas.uploadImage') }}</span>
                    <input
                        type="file"
                        accept="image/*"
                        @change="handleBackgroundImageUpload"
                        class="hidden"
                    />
                </label>
            </div>

            <!-- API Documentation Section -->
            <div class="border-b border-gray-200">
                <button
                    @click="showApiDocs = !showApiDocs"
                    class="w-full px-3 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors"
                >
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">{{ t('graphics.apiDocs.title') }}</span>
                    </div>
                    <svg
                        :class="['w-4 h-4 text-gray-400 transition-transform', showApiDocs ? 'rotate-180' : '']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div v-if="showApiDocs" class="px-3 pb-4 space-y-4">
                    <!-- How it works -->
                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                        <p class="text-[10px] text-amber-800 leading-relaxed">
                            <strong>{{ t('graphics.apiDocs.howItWorks') }}:</strong> {{ t('graphics.apiDocs.howItWorksDesc') }}
                        </p>
                    </div>

                    <!-- Template ID -->
                    <div class="p-3 bg-blue-50 rounded-lg">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[10px] font-medium text-blue-700 uppercase">Template ID</span>
                            <button
                                @click="copyToClipboard(templateId, 'templateId')"
                                class="text-[10px] text-blue-600 hover:text-blue-800"
                            >
                                {{ copiedSection === 'templateId' ? '✓' : t('graphics.apiDocs.copy') }}
                            </button>
                        </div>
                        <code class="block text-xs font-mono text-blue-900 break-all">{{ templateId }}</code>
                    </div>

                    <!-- Endpoint: Generate image -->
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[11px] font-medium text-gray-900">{{ t('graphics.apiDocs.generateEndpoint') }}</span>
                        </div>
                        <div class="p-2.5 bg-gray-800 rounded-lg">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-1.5 py-0.5 bg-blue-500 text-white text-[9px] font-bold rounded">POST</span>
                                <button
                                    @click="copyToClipboard(endpoints.generate, 'generate')"
                                    class="ml-auto text-[10px] text-gray-400 hover:text-white"
                                >
                                    {{ copiedSection === 'generate' ? '✓' : t('graphics.apiDocs.copy') }}
                                </button>
                            </div>
                            <code class="block text-[11px] font-mono break-all" style="color: #86efac;">{{ endpoints.generate }}</code>
                        </div>
                    </div>

                    <!-- Layers list -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-medium text-gray-900">{{ t('graphics.apiDocs.modifiableLayers') }}</span>
                            <span class="text-[10px] text-gray-500">({{ modifiableLayers.length }})</span>
                        </div>

                        <div v-if="modifiableLayers.length > 0" class="space-y-2">
                            <div
                                v-for="layer in modifiableLayers"
                                :key="layer.id"
                                class="p-2 bg-gray-50 rounded-lg border border-gray-200"
                            >
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span :class="[
                                        'px-1.5 py-0.5 text-[9px] font-bold rounded',
                                        layer.type === 'text' ? 'bg-purple-100 text-purple-700' :
                                        layer.type === 'image' ? 'bg-green-100 text-green-700' :
                                        'bg-orange-100 text-orange-700'
                                    ]">{{ layer.type.toUpperCase() }}</span>
                                    <span class="text-[10px] text-gray-700 font-medium truncate flex-1">{{ layer.name }}</span>
                                </div>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <span
                                        v-for="prop in layer.modifiableProps"
                                        :key="prop"
                                        class="px-1.5 py-0.5 bg-white border border-gray-200 text-[9px] text-gray-600 rounded"
                                    >{{ prop }}</span>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-[10px] text-gray-400 italic py-2">{{ t('graphics.apiDocs.noModifiableLayers') }}</p>
                    </div>

                    <!-- Request body -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-medium text-gray-900">{{ t('graphics.apiDocs.requestBody') }}</span>
                            <button
                                @click="copyToClipboard(JSON.stringify({ modifications: exampleModifications, format: 'png', quality: 100, scale: 1 }, null, 2), 'body')"
                                class="text-[10px] text-blue-600 hover:text-blue-800"
                            >
                                {{ copiedSection === 'body' ? '✓' : t('graphics.apiDocs.copy') }}
                            </button>
                        </div>
                        <pre class="p-2.5 bg-gray-800 rounded-lg text-[10px] font-mono overflow-x-auto max-h-64 overflow-y-auto"><code class="whitespace-pre" style="color: #86efac;">{{ JSON.stringify({ modifications: exampleModifications, format: 'png', quality: 100, scale: 1 }, null, 2) }}</code></pre>
                    </div>

                    <!-- Optional params note -->
                    <div class="p-2.5 bg-gray-50 rounded-lg border border-gray-200">
                        <span class="text-[10px] font-medium text-gray-700 block mb-1">{{ t('graphics.apiDocs.optionalParams') }}:</span>
                        <span class="text-[9px] text-gray-500">{{ t('graphics.apiDocs.optionalParamsDesc') }}</span>
                    </div>

                    <!-- Response -->
                    <div>
                        <span class="text-[11px] font-medium text-gray-900 mb-2 block">{{ t('graphics.apiDocs.response') }}</span>
                        <pre class="p-2.5 bg-gray-800 rounded-lg text-[10px] font-mono overflow-x-auto max-h-48 overflow-y-auto"><code class="whitespace-pre" style="color: #86efac;">{
  "success": true,
  "template": {
    "id": "{{ templateId }}",
    "width": {{ currentTemplate?.width || 1080 }},
    "height": {{ currentTemplate?.height || 1080 }},
    "background_color": "{{ currentTemplate?.background_color || '#FFFFFF' }}"
  },
  "layers": [...],
  "render_options": {
    "format": "png",
    "quality": 100,
    "scale": 1
  }
}</code></pre>
                    </div>

                    <!-- Auth header -->
                    <div class="p-2.5 bg-gray-100 rounded-lg">
                        <span class="text-[10px] font-medium text-gray-700 block mb-1">Header:</span>
                        <code class="text-[10px] font-mono text-gray-600">Authorization: Bearer YOUR_API_TOKEN</code>
                    </div>

                    <!-- Curl command -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-medium text-gray-900">{{ t('graphics.apiDocs.curlCommand') }}</span>
                            <button
                                @click="copyToClipboard(curlCommand, 'curl')"
                                class="text-[10px] text-blue-600 hover:text-blue-800"
                            >
                                {{ copiedSection === 'curl' ? '✓' : t('graphics.apiDocs.copy') }}
                            </button>
                        </div>
                        <pre class="p-2.5 bg-gray-800 rounded-lg text-[10px] font-mono overflow-x-auto max-h-48 overflow-y-auto"><code class="whitespace-pre" style="color: #86efac;">{{ curlCommand }}</code></pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- No selection message -->
        <div
            v-else-if="!selectedLayer"
            class="flex-1 flex items-center justify-center p-4 text-gray-400 text-xs text-center"
        >
            {{ t('graphics.layers.noLayers') }}
        </div>

        <!-- Layer Properties -->
        <div v-else class="flex-1 overflow-y-auto">
            <!-- Layer info header -->
            <div class="px-3 py-4 border-b border-gray-200">
                <div class="flex items-center gap-2.5">
                    <!-- Layer type icon -->
                    <div class="w-8 h-8 flex items-center justify-center bg-blue-50 text-blue-600 rounded-lg">
                        <!-- Text icon -->
                        <svg v-if="selectedLayer.type === 'text'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16m-7 6h7" />
                        </svg>
                        <!-- Rectangle icon -->
                        <svg v-else-if="selectedLayer.type === 'rectangle'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                        </svg>
                        <!-- Ellipse icon -->
                        <svg v-else-if="selectedLayer.type === 'ellipse'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="9" />
                        </svg>
                        <!-- Image icon -->
                        <svg v-else-if="selectedLayer.type === 'image'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <!-- Line icon -->
                        <svg v-else-if="selectedLayer.type === 'line'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 20L20 4" />
                        </svg>
                        <!-- Textbox icon -->
                        <svg v-else-if="selectedLayer.type === 'textbox'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="6" width="18" height="12" rx="2" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 12h10" />
                        </svg>
                    </div>
                    <input
                        :value="selectedLayer.name"
                        @input="updateLayer('name', $event.target.value)"
                        type="text"
                        class="flex-1 bg-transparent border-none text-gray-900 text-sm font-medium focus:outline-none focus:ring-0 p-0"
                        placeholder="Layer name"
                    />
                </div>
            </div>

            <!-- Semantic Tag section (admin only) -->
            <div v-if="authStore.isAdmin && semanticTagOptions.length > 0" class="px-3 py-3 border-b border-gray-200">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <span class="text-xs font-medium text-gray-900">{{ t('graphics.properties.semantic_tag') }}</span>
                </div>
                <select
                    :value="currentSemanticTag"
                    @change="updateSemanticTag($event.target.value)"
                    class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">{{ t('graphics.properties.no_tag') }}</option>
                    <option
                        v-for="tag in semanticTagOptions"
                        :key="tag.value"
                        :value="tag.value"
                    >
                        {{ tag.label }}
                    </option>
                </select>
                <p class="mt-1 text-[10px] text-gray-500">{{ t('graphics.properties.semantic_tag_help') }}</p>
            </div>

            <!-- Create Template from Group (admin only, group layers) -->
            <div v-if="authStore.isAdmin && isGroupLayer" class="px-3 py-3 border-b border-gray-200">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-xs font-medium text-gray-900">{{ t('graphics.layers.createTemplateFromGroup') }}</span>
                </div>
                <div class="flex flex-col gap-2">
                    <button
                        @click="createTemplateFromGroup(false)"
                        :disabled="creatingTemplate"
                        class="w-full px-3 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="creatingTemplate" class="flex items-center justify-center gap-2">
                            <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ t('common.loading') }}
                        </span>
                        <span v-else>{{ t('graphics.library.createFromGroup') }}</span>
                    </button>
                    <button
                        @click="createTemplateFromGroup(true)"
                        :disabled="creatingTemplate"
                        class="w-full px-3 py-2 text-xs font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ t('graphics.library.createFromGroup') }} + {{ t('graphics.library.addToLibrary') }}
                    </button>
                </div>
            </div>

            <!-- Layer order section -->
            <div class="px-3 py-3 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">{{ t('graphics.layers.order') }}</span>
                    <div class="flex items-center gap-1">
                        <button
                            @click="graphicsStore.sendToBack()"
                            class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded transition-colors"
                            :title="t('graphics.layers.sendToBack')"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                        </button>
                        <button
                            @click="graphicsStore.sendBackward()"
                            class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded transition-colors"
                            :title="t('graphics.layers.sendBackward')"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 13l-5 5m0 0l-5-5m5 5V6" />
                            </svg>
                        </button>
                        <button
                            @click="graphicsStore.bringForward()"
                            class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded transition-colors"
                            :title="t('graphics.layers.bringForward')"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                            </svg>
                        </button>
                        <button
                            @click="graphicsStore.bringToFront()"
                            class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded transition-colors"
                            :title="t('graphics.layers.bringToFront')"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Alignment section -->
            <div class="px-3 py-3 border-b border-gray-200">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <span class="text-xs font-medium text-gray-900">{{ t('graphics.properties.alignment') }}</span>
                </div>
                <div class="grid grid-cols-3 gap-1 mb-2">
                    <!-- Align Left -->
                    <button
                        @click="graphicsStore.alignLeft()"
                        class="flex items-center justify-center gap-1 px-2 py-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded transition-colors text-[10px]"
                        :title="t('graphics.properties.alignLeft')"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="4" y1="4" x2="4" y2="20" />
                            <rect x="8" y="6" width="12" height="4" rx="1" />
                            <rect x="8" y="14" width="8" height="4" rx="1" />
                        </svg>
                        <span>{{ t('graphics.properties.alignLeft').split(' ').pop() }}</span>
                    </button>
                    <!-- Align Center H -->
                    <button
                        @click="graphicsStore.alignCenterH()"
                        class="flex items-center justify-center gap-1 px-2 py-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded transition-colors text-[10px]"
                        :title="t('graphics.properties.alignCenterH')"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="4" x2="12" y2="20" />
                            <rect x="5" y="6" width="14" height="4" rx="1" />
                            <rect x="7" y="14" width="10" height="4" rx="1" />
                        </svg>
                        <span>{{ t('graphics.properties.alignCenterH').split(' ').pop() }}</span>
                    </button>
                    <!-- Align Right -->
                    <button
                        @click="graphicsStore.alignRight()"
                        class="flex items-center justify-center gap-1 px-2 py-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded transition-colors text-[10px]"
                        :title="t('graphics.properties.alignRight')"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="20" y1="4" x2="20" y2="20" />
                            <rect x="4" y="6" width="12" height="4" rx="1" />
                            <rect x="8" y="14" width="8" height="4" rx="1" />
                        </svg>
                        <span>{{ t('graphics.properties.alignRight').split(' ').pop() }}</span>
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-1">
                    <!-- Align Top -->
                    <button
                        @click="graphicsStore.alignTop()"
                        class="flex items-center justify-center gap-1 px-2 py-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded transition-colors text-[10px]"
                        :title="t('graphics.properties.alignTop')"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="4" y1="4" x2="20" y2="4" />
                            <rect x="6" y="8" width="4" height="12" rx="1" />
                            <rect x="14" y="8" width="4" height="8" rx="1" />
                        </svg>
                        <span>{{ t('graphics.properties.alignTop').split(' ').pop() }}</span>
                    </button>
                    <!-- Align Center V -->
                    <button
                        @click="graphicsStore.alignCenterV()"
                        class="flex items-center justify-center gap-1 px-2 py-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded transition-colors text-[10px]"
                        :title="t('graphics.properties.alignCenterV')"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="4" y1="12" x2="20" y2="12" />
                            <rect x="6" y="5" width="4" height="14" rx="1" />
                            <rect x="14" y="7" width="4" height="10" rx="1" />
                        </svg>
                        <span>{{ t('graphics.properties.alignCenterV').split(' ').pop() }}</span>
                    </button>
                    <!-- Align Bottom -->
                    <button
                        @click="graphicsStore.alignBottom()"
                        class="flex items-center justify-center gap-1 px-2 py-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded transition-colors text-[10px]"
                        :title="t('graphics.properties.alignBottom')"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="4" y1="20" x2="20" y2="20" />
                            <rect x="6" y="4" width="4" height="12" rx="1" />
                            <rect x="14" y="8" width="4" height="8" rx="1" />
                        </svg>
                        <span>{{ t('graphics.properties.alignBottom').split(' ').pop() }}</span>
                    </button>
                </div>
            </div>

            <!-- Opacity section -->
            <div class="px-3 py-4 border-b border-gray-200">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <span class="text-xs font-medium text-gray-900">
                        {{ t('graphics.properties.opacity') }}
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <input
                        type="range"
                        :value="(selectedLayer.opacity ?? 1) * 100"
                        @input="updateLayer('opacity', $event.target.value / 100)"
                        min="0"
                        max="100"
                        class="flex-1 h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600"
                    />
                    <div class="w-16">
                        <ScrubberInput
                            :model-value="Math.round((selectedLayer.opacity ?? 1) * 100)"
                            @update:model-value="updateLayer('opacity', $event / 100)"
                            suffix="%"
                            :min="0"
                            :max="100"
                            :sensitivity="1"
                            input-class="px-2 py-1.5 text-right"
                        />
                    </div>
                </div>
            </div>

            <!-- Shadow section -->
            <div class="px-3 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">
                            {{ t('graphics.properties.shadow') }}
                        </span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="selectedLayer.properties?.shadowEnabled"
                            @change="updateProperty('shadowEnabled', $event.target.checked)"
                            class="sr-only peer"
                        />
                        <div class="w-8 h-4 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all"></div>
                    </label>
                </div>

                <div v-if="selectedLayer.properties?.shadowEnabled" class="space-y-2">
                    <!-- Shadow color -->
                    <div class="flex items-center gap-2">
                        <input
                            :value="selectedLayer.properties?.shadowColor || '#000000'"
                            @input="updateProperty('shadowColor', $event.target.value)"
                            type="color"
                            class="w-8 h-8 rounded cursor-pointer border border-gray-300"
                            style="padding: 2px;"
                        />
                        <input
                            :value="selectedLayer.properties?.shadowColor || '#000000'"
                            @input="updateProperty('shadowColor', $event.target.value)"
                            type="text"
                            class="flex-1 px-2 py-1.5 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white uppercase transition-colors font-mono"
                        />
                    </div>

                    <!-- Shadow blur and opacity -->
                    <div class="grid grid-cols-2 gap-2">
                        <ScrubberInput
                            :model-value="selectedLayer.properties?.shadowBlur || 10"
                            @update:model-value="updateProperty('shadowBlur', $event)"
                            :label="t('graphics.properties.shadowBlur')"
                            suffix="px"
                            :min="0"
                            :max="100"
                            :sensitivity="0.5"
                        />
                        <ScrubberInput
                            :model-value="Math.round((selectedLayer.properties?.shadowOpacity ?? 0.5) * 100)"
                            @update:model-value="updateProperty('shadowOpacity', $event / 100)"
                            :label="t('graphics.properties.shadowOpacity')"
                            suffix="%"
                            :min="0"
                            :max="100"
                            :sensitivity="1"
                        />
                    </div>

                    <!-- Shadow offset -->
                    <div class="grid grid-cols-2 gap-2">
                        <ScrubberInput
                            :model-value="selectedLayer.properties?.shadowOffsetX || 5"
                            @update:model-value="updateProperty('shadowOffsetX', $event)"
                            :label="t('graphics.properties.shadowOffsetX')"
                            suffix="px"
                            :min="-100"
                            :max="100"
                            :sensitivity="0.5"
                        />
                        <ScrubberInput
                            :model-value="selectedLayer.properties?.shadowOffsetY || 5"
                            @update:model-value="updateProperty('shadowOffsetY', $event)"
                            :label="t('graphics.properties.shadowOffsetY')"
                            suffix="px"
                            :min="-100"
                            :max="100"
                            :sensitivity="0.5"
                        />
                    </div>
                </div>
            </div>

            <!-- Blur section (for text and rectangle) -->
            <div
                v-if="selectedLayer.type === 'text' || selectedLayer.type === 'rectangle'"
                class="px-3 py-4 border-b border-gray-200"
            >
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">
                            {{ t('graphics.properties.blur') }}
                        </span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="selectedLayer.properties?.blurEnabled"
                            @change="updateProperty('blurEnabled', $event.target.checked)"
                            class="sr-only peer"
                        />
                        <div class="w-8 h-4 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all"></div>
                    </label>
                </div>

                <div v-if="selectedLayer.properties?.blurEnabled" class="space-y-2">
                    <ScrubberInput
                        :model-value="selectedLayer.properties?.blurRadius || 10"
                        @update:model-value="updateProperty('blurRadius', $event)"
                        :label="t('graphics.properties.blurRadius')"
                        suffix="px"
                        :min="0"
                        :max="50"
                        :sensitivity="0.3"
                    />
                </div>
            </div>

            <!-- Transform section -->
            <div class="px-3 py-4 border-b border-gray-200">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-xs font-medium text-gray-900">
                        {{ t('graphics.properties.position') }}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <!-- X -->
                    <ScrubberInput
                        :model-value="Math.round(selectedLayer.x)"
                        @update:model-value="updateNumeric('x', $event)"
                        label="X"
                        :sensitivity="1"
                    />
                    <!-- Y -->
                    <ScrubberInput
                        :model-value="Math.round(selectedLayer.y)"
                        @update:model-value="updateNumeric('y', $event)"
                        label="Y"
                        :sensitivity="1"
                    />
                </div>

                <!-- Size (for non-text) -->
                <template v-if="selectedLayer.type !== 'text'">
                    <div class="flex items-center gap-2 mt-4 mb-3">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">
                            {{ t('graphics.properties.size') }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <!-- W -->
                        <ScrubberInput
                            :model-value="Math.round(selectedLayer.width)"
                            @update:model-value="updateNumeric('width', $event)"
                            label="W"
                            :min="1"
                            :sensitivity="1"
                        />
                        <!-- H -->
                        <ScrubberInput
                            :model-value="Math.round(selectedLayer.height)"
                            @update:model-value="updateNumeric('height', $event)"
                            label="H"
                            :min="1"
                            :sensitivity="1"
                        />
                    </div>
                </template>

                <!-- Rotation -->
                <div class="mt-4">
                    <ScrubberInput
                        :model-value="Math.round(selectedLayer.rotation || 0)"
                        @update:model-value="updateNumeric('rotation', $event)"
                        :label="t('graphics.properties.rotation')"
                        suffix="°"
                        :min="-360"
                        :max="360"
                        :sensitivity="1"
                    />
                </div>
            </div>

            <!-- Text properties -->
            <template v-if="selectedLayer.type === 'text'">
                <!-- Text content -->
                <div class="px-3 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">
                            {{ t('graphics.layerTypes.text') }}
                        </span>
                    </div>
                    <textarea
                        v-model="localText"
                        @input="updateText"
                        @focus="onTextFocus"
                        @blur="onTextBlur"
                        rows="3"
                        class="w-full px-2.5 py-2 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white resize-none transition-colors"
                        placeholder="Enter text..."
                    />
                </div>

                <!-- Typography section -->
                <div class="px-3 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h8m-8 6h16" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">
                            {{ t('graphics.properties.typography') }}
                        </span>
                    </div>

                    <!-- Font family -->
                    <div class="mb-2">
                        <FontPicker
                            :model-value="selectedLayer.properties?.fontFamily || 'Arial'"
                            @update:model-value="updateProperty('fontFamily', $event)"
                        />
                    </div>

                    <!-- Text color -->
                    <div class="mb-2">
                        <label class="block text-[10px] text-gray-500 mb-1">{{ t('graphics.properties.textColor') }}</label>
                        <div class="flex items-center gap-2">
                            <input
                                :value="selectedLayer.properties?.fill || '#000000'"
                                @input="updateProperty('fill', $event.target.value)"
                                type="color"
                                class="w-8 h-8 rounded cursor-pointer border border-gray-300"
                                style="padding: 2px;"
                            />
                            <input
                                :value="selectedLayer.properties?.fill || '#000000'"
                                @input="updateProperty('fill', $event.target.value)"
                                type="text"
                                class="flex-1 px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white uppercase transition-colors font-mono"
                            />
                        </div>
                    </div>

                    <!-- Font size and weight -->
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <ScrubberInput
                            :model-value="selectedLayer.properties?.fontSize || 24"
                            @update:model-value="updateProperty('fontSize', $event)"
                            suffix="px"
                            :min="1"
                            :max="500"
                            :sensitivity="0.5"
                        />
                        <select
                            :value="selectedLayer.properties?.fontWeight || 'normal'"
                            @change="updateProperty('fontWeight', $event.target.value)"
                            class="w-full bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white px-2.5 py-2 transition-colors"
                        >
                            <option v-for="w in fontWeights" :key="w.value" :value="w.value">
                                {{ w.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Text align -->
                    <div class="flex bg-gray-50 border border-gray-200 rounded overflow-hidden mb-3">
                        <button
                            v-for="align in textAligns"
                            :key="align.value"
                            @click="updateProperty('align', align.value)"
                            :class="[
                                'flex-1 py-2 transition-colors flex items-center justify-center border-r border-gray-200 last:border-r-0',
                                (selectedLayer.properties?.align || 'left') === align.value
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="align.icon" />
                            </svg>
                        </button>
                    </div>

                    <!-- Text direction (horizontal/vertical) -->
                    <div class="mb-3">
                        <label class="block text-[10px] text-gray-500 mb-1">{{ t('graphics.properties.textDirection') }}</label>
                        <div class="flex bg-gray-50 border border-gray-200 rounded overflow-hidden">
                            <button
                                v-for="direction in textDirections"
                                :key="direction.value"
                                @click="updateProperty('textDirection', direction.value)"
                                :class="[
                                    'flex-1 py-2 transition-colors flex items-center justify-center gap-1.5 border-r border-gray-200 last:border-r-0',
                                    (selectedLayer.properties?.textDirection || 'horizontal') === direction.value
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                ]"
                                :title="t('graphics.properties.' + direction.value)"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="direction.icon" />
                                </svg>
                                <span class="text-xs">{{ t('graphics.properties.' + direction.value) }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Fixed width toggle -->
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-xs text-gray-700">{{ t('graphics.properties.fixedWidth') }}</label>
                        <button
                            @click="updateProperty('fixedWidth', !selectedLayer.properties?.fixedWidth)"
                            :class="[
                                'relative w-10 h-5 rounded-full transition-colors',
                                selectedLayer.properties?.fixedWidth
                                    ? 'bg-blue-600'
                                    : 'bg-gray-300'
                            ]"
                        >
                            <span
                                :class="[
                                    'absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform',
                                    selectedLayer.properties?.fixedWidth ? 'translate-x-5' : ''
                                ]"
                            />
                        </button>
                    </div>

                    <!-- Line height and Letter spacing -->
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <!-- Line height -->
                        <ScrubberInput
                            :model-value="selectedLayer.properties?.lineHeight || 1.2"
                            @update:model-value="updateProperty('lineHeight', $event)"
                            :label="t('graphics.properties.lineHeight')"
                            :step="0.1"
                            :min="0.5"
                            :max="5"
                            :decimals="1"
                            :sensitivity="0.01"
                        />
                        <!-- Letter spacing -->
                        <ScrubberInput
                            :model-value="selectedLayer.properties?.letterSpacing || 0"
                            @update:model-value="updateProperty('letterSpacing', $event)"
                            :label="t('graphics.properties.letterSpacing')"
                            suffix="px"
                            :step="0.5"
                            :min="-10"
                            :max="50"
                            :decimals="1"
                            :sensitivity="0.1"
                        />
                    </div>

                    <!-- Text decoration -->
                    <div class="flex items-center gap-1 mb-3">
                        <button
                            @click="updateProperty('fontStyle', selectedLayer.properties?.fontStyle === 'italic' ? 'normal' : 'italic')"
                            :class="[
                                'flex-1 py-1.5 rounded text-sm font-medium transition-colors flex items-center justify-center',
                                selectedLayer.properties?.fontStyle === 'italic'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            ]"
                            :title="t('graphics.properties.italic')"
                        >
                            <span class="italic">I</span>
                        </button>
                        <button
                            @click="updateProperty('textDecoration', toggleTextDecoration('underline'))"
                            :class="[
                                'flex-1 py-1.5 rounded text-sm font-medium transition-colors flex items-center justify-center',
                                (selectedLayer.properties?.textDecoration || '').includes('underline')
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            ]"
                            :title="t('graphics.properties.underline')"
                        >
                            <span class="underline">U</span>
                        </button>
                        <button
                            @click="updateProperty('textDecoration', toggleTextDecoration('line-through'))"
                            :class="[
                                'flex-1 py-1.5 rounded text-sm font-medium transition-colors flex items-center justify-center',
                                (selectedLayer.properties?.textDecoration || '').includes('line-through')
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            ]"
                            :title="t('graphics.properties.strikethrough')"
                        >
                            <span class="line-through">S</span>
                        </button>
                    </div>

                    <!-- Text transform -->
                    <div>
                        <label class="block text-[10px] text-gray-500 mb-1">{{ t('graphics.properties.textTransform') }}</label>
                        <div class="flex bg-gray-50 border border-gray-200 rounded overflow-hidden">
                            <button
                                v-for="transform in textTransforms"
                                :key="transform.value"
                                @click="updateProperty('textTransform', transform.value)"
                                :class="[
                                    'flex-1 py-1.5 text-[10px] font-medium transition-colors border-r border-gray-200 last:border-r-0',
                                    (selectedLayer.properties?.textTransform || 'none') === transform.value
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                ]"
                            >
                                {{ transform.label }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Textbox properties -->
            <template v-if="selectedLayer.type === 'textbox'">
                <!-- Text content -->
                <div class="px-3 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">
                            {{ t('graphics.layerTypes.textbox') }}
                        </span>
                    </div>
                    <textarea
                        v-model="localText"
                        @input="updateText"
                        @focus="onTextFocus"
                        @blur="onTextBlur"
                        rows="3"
                        class="w-full px-2.5 py-2 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white resize-none transition-colors"
                        :placeholder="t('graphics.properties.enterText')"
                    />
                </div>

                <!-- Typography section -->
                <div class="px-3 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h8m-8 6h16" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">
                            {{ t('graphics.properties.typography') }}
                        </span>
                    </div>

                    <!-- Font family -->
                    <div class="mb-2">
                        <FontPicker
                            :model-value="selectedLayer.properties?.fontFamily || 'Arial'"
                            @update:model-value="updateProperty('fontFamily', $event)"
                        />
                    </div>

                    <!-- Text color -->
                    <div class="mb-2">
                        <label class="block text-[10px] text-gray-500 mb-1">{{ t('graphics.properties.textColor') }}</label>
                        <div class="flex items-center gap-2">
                            <input
                                :value="selectedLayer.properties?.textColor || '#FFFFFF'"
                                @input="updateProperty('textColor', $event.target.value)"
                                type="color"
                                class="w-8 h-8 rounded cursor-pointer border border-gray-300"
                                style="padding: 2px;"
                            />
                            <input
                                :value="selectedLayer.properties?.textColor || '#FFFFFF'"
                                @input="updateProperty('textColor', $event.target.value)"
                                type="text"
                                class="flex-1 px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white uppercase transition-colors font-mono"
                            />
                        </div>
                    </div>

                    <!-- Font size and weight -->
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <ScrubberInput
                            :model-value="selectedLayer.properties?.fontSize || 16"
                            @update:model-value="updateProperty('fontSize', $event)"
                            suffix="px"
                            :min="1"
                            :max="200"
                            :sensitivity="0.5"
                        />
                        <select
                            :value="selectedLayer.properties?.fontWeight || '600'"
                            @change="updateProperty('fontWeight', $event.target.value)"
                            class="w-full bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white px-2.5 py-2 transition-colors"
                        >
                            <option v-for="w in fontWeights" :key="w.value" :value="w.value">
                                {{ w.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Text align -->
                    <div class="flex bg-gray-50 border border-gray-200 rounded overflow-hidden mb-3">
                        <button
                            v-for="align in textAligns"
                            :key="align.value"
                            @click="updateProperty('align', align.value)"
                            :class="[
                                'flex-1 py-2 transition-colors flex items-center justify-center border-r border-gray-200 last:border-r-0',
                                (selectedLayer.properties?.align || 'center') === align.value
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="align.icon" />
                            </svg>
                        </button>
                    </div>

                    <!-- Line height -->
                    <div class="mb-3">
                        <ScrubberInput
                            :model-value="selectedLayer.properties?.lineHeight || 1.2"
                            @update:model-value="updateProperty('lineHeight', $event)"
                            :label="t('graphics.properties.lineHeight')"
                            :step="0.1"
                            :min="0.5"
                            :max="5"
                            :decimals="1"
                            :sensitivity="0.01"
                        />
                    </div>
                </div>

                <!-- Background section -->
                <div class="px-3 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">
                            {{ t('graphics.properties.background') }}
                        </span>
                    </div>

                    <!-- Background color -->
                    <div class="flex items-center gap-2 mb-3">
                        <input
                            :value="selectedLayer.properties?.fill || '#3B82F6'"
                            @input="updateProperty('fill', $event.target.value)"
                            type="color"
                            class="w-9 h-9 rounded cursor-pointer border border-gray-300"
                            style="padding: 2px;"
                        />
                        <input
                            :value="selectedLayer.properties?.fill || '#3B82F6'"
                            @input="updateProperty('fill', $event.target.value)"
                            type="text"
                            class="flex-1 px-2.5 py-2 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white uppercase transition-colors font-mono"
                        />
                    </div>

                    <!-- Padding -->
                    <div class="mb-3">
                        <ScrubberInput
                            :model-value="selectedLayer.properties?.padding || 16"
                            @update:model-value="updateProperty('padding', $event)"
                            :label="t('graphics.properties.padding')"
                            suffix="px"
                            :min="0"
                            :max="100"
                            :sensitivity="0.5"
                        />
                    </div>

                    <!-- Corner radius -->
                    <ScrubberInput
                        :model-value="selectedLayer.properties?.cornerRadius || 8"
                        @update:model-value="updateProperty('cornerRadius', $event)"
                        :label="t('graphics.properties.cornerRadius')"
                        suffix="px"
                        :min="0"
                        :max="100"
                        :sensitivity="0.5"
                    />
                </div>
            </template>

            <!-- Rectangle/Ellipse properties -->
            <template v-if="selectedLayer.type === 'rectangle' || selectedLayer.type === 'ellipse'">
                <!-- Fill -->
                <div class="px-3 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                            </svg>
                            <span class="text-xs font-medium text-gray-900">
                                {{ t('graphics.properties.fill') }}
                            </span>
                        </div>
                    </div>

                    <!-- Fill type toggle -->
                    <div class="flex bg-gray-100 rounded-md p-0.5 mb-3">
                        <button
                            @click="updateProperty('fillType', 'color')"
                            :class="[
                                'flex-1 px-2 py-1 text-[10px] font-medium rounded transition-colors',
                                (!selectedLayer.properties?.fillType || selectedLayer.properties?.fillType === 'color')
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            {{ t('graphics.properties.fillColor') }}
                        </button>
                        <button
                            @click="updateProperty('fillType', 'gradient')"
                            :class="[
                                'flex-1 px-2 py-1 text-[10px] font-medium rounded transition-colors',
                                selectedLayer.properties?.fillType === 'gradient'
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            {{ t('graphics.properties.fillGradient') }}
                        </button>
                        <button
                            @click="updateProperty('fillType', 'image')"
                            :class="[
                                'flex-1 px-2 py-1 text-[10px] font-medium rounded transition-colors',
                                selectedLayer.properties?.fillType === 'image'
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            {{ t('graphics.properties.fillImage') }}
                        </button>
                    </div>

                    <!-- Color fill -->
                    <div v-if="!selectedLayer.properties?.fillType || selectedLayer.properties?.fillType === 'color'" class="flex items-center gap-2">
                        <input
                            :value="selectedLayer.properties?.fill || '#CCCCCC'"
                            @input="updateProperty('fill', $event.target.value)"
                            type="color"
                            class="w-9 h-9 rounded cursor-pointer border border-gray-300"
                            style="padding: 2px;"
                        />
                        <input
                            :value="selectedLayer.properties?.fill || '#CCCCCC'"
                            @input="updateProperty('fill', $event.target.value)"
                            type="text"
                            class="flex-1 px-2.5 py-2 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white uppercase transition-colors font-mono"
                        />
                    </div>

                    <!-- Gradient fill -->
                    <div v-else-if="selectedLayer.properties?.fillType === 'gradient'" class="space-y-3">
                        <!-- Gradient type -->
                        <div class="flex bg-gray-50 border border-gray-200 rounded overflow-hidden">
                            <button
                                @click="updateProperty('gradientType', 'linear')"
                                :class="[
                                    'flex-1 py-1.5 text-[10px] font-medium transition-colors',
                                    (!selectedLayer.properties?.gradientType || selectedLayer.properties?.gradientType === 'linear')
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                ]"
                            >
                                {{ t('graphics.properties.gradientLinear') }}
                            </button>
                            <button
                                @click="updateProperty('gradientType', 'radial')"
                                :class="[
                                    'flex-1 py-1.5 text-[10px] font-medium transition-colors',
                                    selectedLayer.properties?.gradientType === 'radial'
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                ]"
                            >
                                {{ t('graphics.properties.gradientRadial') }}
                            </button>
                        </div>

                        <!-- Gradient preview -->
                        <div
                            class="h-8 rounded border border-gray-200"
                            :style="{
                                backgroundImage: 'linear-gradient(45deg, #ccc 25%, transparent 25%), linear-gradient(-45deg, #ccc 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #ccc 75%), linear-gradient(-45deg, transparent 75%, #ccc 75%)',
                                backgroundSize: '8px 8px',
                                backgroundPosition: '0 0, 0 4px, 4px -4px, -4px 0px',
                                position: 'relative'
                            }"
                        >
                            <div
                                class="absolute inset-0 rounded"
                                :style="{
                                    background: selectedLayer.properties?.gradientType === 'radial'
                                        ? `radial-gradient(circle, ${hexToRgba(selectedLayer.properties?.gradientStartColor || '#3B82F6', selectedLayer.properties?.gradientStartOpacity ?? 1)}, ${hexToRgba(selectedLayer.properties?.gradientEndColor || '#8B5CF6', selectedLayer.properties?.gradientEndOpacity ?? 1)})`
                                        : `linear-gradient(${selectedLayer.properties?.gradientAngle || 0}deg, ${hexToRgba(selectedLayer.properties?.gradientStartColor || '#3B82F6', selectedLayer.properties?.gradientStartOpacity ?? 1)}, ${hexToRgba(selectedLayer.properties?.gradientEndColor || '#8B5CF6', selectedLayer.properties?.gradientEndOpacity ?? 1)})`
                                }"
                            ></div>
                        </div>

                        <!-- Start color -->
                        <div class="flex items-center gap-2">
                            <input
                                :value="selectedLayer.properties?.gradientStartColor || '#3B82F6'"
                                @input="updateProperty('gradientStartColor', $event.target.value)"
                                type="color"
                                class="w-8 h-8 rounded cursor-pointer border border-gray-300"
                                style="padding: 2px;"
                            />
                            <input
                                :value="selectedLayer.properties?.gradientStartColor || '#3B82F6'"
                                @input="updateProperty('gradientStartColor', $event.target.value)"
                                type="text"
                                class="flex-1 px-2 py-1.5 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white uppercase transition-colors font-mono"
                            />
                        </div>

                        <!-- Start opacity -->
                        <div>
                            <label class="block text-[10px] text-gray-500 mb-1">{{ t('graphics.properties.gradientStartOpacity') }}</label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="range"
                                    :value="(selectedLayer.properties?.gradientStartOpacity ?? 1) * 100"
                                    @input="updateProperty('gradientStartOpacity', parseInt($event.target.value) / 100)"
                                    min="0"
                                    max="100"
                                    class="flex-1 h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600"
                                />
                                <div class="w-14">
                                    <ScrubberInput
                                        :model-value="Math.round((selectedLayer.properties?.gradientStartOpacity ?? 1) * 100)"
                                        @update:model-value="updateProperty('gradientStartOpacity', $event / 100)"
                                        suffix="%"
                                        :min="0"
                                        :max="100"
                                        :sensitivity="1"
                                        input-class="px-2 py-1 text-center"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- End color -->
                        <div class="flex items-center gap-2">
                            <input
                                :value="selectedLayer.properties?.gradientEndColor || '#8B5CF6'"
                                @input="updateProperty('gradientEndColor', $event.target.value)"
                                type="color"
                                class="w-8 h-8 rounded cursor-pointer border border-gray-300"
                                style="padding: 2px;"
                            />
                            <input
                                :value="selectedLayer.properties?.gradientEndColor || '#8B5CF6'"
                                @input="updateProperty('gradientEndColor', $event.target.value)"
                                type="text"
                                class="flex-1 px-2 py-1.5 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white uppercase transition-colors font-mono"
                            />
                        </div>

                        <!-- End opacity -->
                        <div>
                            <label class="block text-[10px] text-gray-500 mb-1">{{ t('graphics.properties.gradientEndOpacity') }}</label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="range"
                                    :value="(selectedLayer.properties?.gradientEndOpacity ?? 1) * 100"
                                    @input="updateProperty('gradientEndOpacity', parseInt($event.target.value) / 100)"
                                    min="0"
                                    max="100"
                                    class="flex-1 h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600"
                                />
                                <div class="w-14">
                                    <ScrubberInput
                                        :model-value="Math.round((selectedLayer.properties?.gradientEndOpacity ?? 1) * 100)"
                                        @update:model-value="updateProperty('gradientEndOpacity', $event / 100)"
                                        suffix="%"
                                        :min="0"
                                        :max="100"
                                        :sensitivity="1"
                                        input-class="px-2 py-1 text-center"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Angle (for linear gradient) -->
                        <div v-if="!selectedLayer.properties?.gradientType || selectedLayer.properties?.gradientType === 'linear'">
                            <label class="block text-[10px] text-gray-500 mb-1">{{ t('graphics.properties.gradientAngle') }}</label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="range"
                                    :value="selectedLayer.properties?.gradientAngle || 0"
                                    @input="updateProperty('gradientAngle', parseInt($event.target.value))"
                                    min="0"
                                    max="360"
                                    class="flex-1 h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600"
                                />
                                <div class="w-14">
                                    <ScrubberInput
                                        :model-value="selectedLayer.properties?.gradientAngle || 0"
                                        @update:model-value="updateProperty('gradientAngle', $event)"
                                        suffix="°"
                                        :min="0"
                                        :max="360"
                                        :sensitivity="1"
                                        input-class="px-2 py-1 text-center"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Image fill -->
                    <div v-else class="space-y-2">
                        <!-- Current fill image preview -->
                        <div v-if="selectedLayer.properties?.fillImage" class="relative rounded-lg overflow-hidden border border-gray-200">
                            <img
                                :src="selectedLayer.properties.fillImage"
                                alt="Fill"
                                class="w-full h-20 object-cover"
                            />
                            <button
                                @click="updateProperty('fillImage', null)"
                                class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors"
                            >
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Upload button -->
                        <label class="flex items-center justify-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 border-dashed rounded-lg cursor-pointer hover:bg-gray-100 transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-xs text-gray-600">{{ t('graphics.properties.uploadFillImage') }}</span>
                            <input
                                type="file"
                                accept="image/*"
                                @change="handleFillImageUpload"
                                class="hidden"
                            />
                        </label>

                        <!-- Fit mode -->
                        <div v-if="selectedLayer.properties?.fillImage">
                            <label class="block text-[10px] text-gray-500 mb-1">{{ t('graphics.properties.fillFit') }}</label>
                            <select
                                :value="selectedLayer.properties?.fillFit || 'cover'"
                                @change="updateProperty('fillFit', $event.target.value)"
                                class="w-full bg-gray-50 border border-gray-200 rounded px-2 py-1.5 text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white transition-colors"
                            >
                                <option value="cover">{{ t('graphics.properties.fitCover') }}</option>
                                <option value="contain">{{ t('graphics.properties.fitContain') }}</option>
                                <option value="tile">{{ t('graphics.properties.fitTile') }}</option>
                                <option value="stretch">{{ t('graphics.properties.fitStretch') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Stroke -->
                <div class="px-3 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">
                            {{ t('graphics.properties.stroke') }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <input
                            :value="selectedLayer.properties?.stroke || '#000000'"
                            @input="updateProperty('stroke', $event.target.value)"
                            type="color"
                            class="w-9 h-9 rounded cursor-pointer border border-gray-300"
                            style="padding: 2px;"
                        />
                        <input
                            :value="selectedLayer.properties?.stroke || '#000000'"
                            @input="updateProperty('stroke', $event.target.value)"
                            type="text"
                            class="flex-1 px-2.5 py-2 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white uppercase transition-colors font-mono"
                        />
                    </div>
                    <!-- Stroke width -->
                    <ScrubberInput
                        :model-value="selectedLayer.properties?.strokeWidth || 0"
                        @update:model-value="updateProperty('strokeWidth', $event)"
                        suffix="px"
                        :min="0"
                        :max="50"
                        :sensitivity="0.2"
                    />
                </div>

                <!-- Corner radius (rectangles only) -->
                <div v-if="selectedLayer.type === 'rectangle'" class="px-3 py-4 border-b border-gray-200">
                    <ScrubberInput
                        :model-value="selectedLayer.properties?.cornerRadius || 0"
                        @update:model-value="updateProperty('cornerRadius', $event)"
                        :label="t('graphics.properties.cornerRadius')"
                        suffix="px"
                        :min="0"
                        :max="200"
                        :sensitivity="0.5"
                    />
                </div>
            </template>

            <!-- Image layer properties -->
            <template v-if="selectedLayer.type === 'image'">
                <div class="px-3 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">
                            {{ t('graphics.properties.image') }}
                        </span>
                    </div>

                    <!-- Current image preview -->
                    <div v-if="selectedLayer.properties?.src" class="relative rounded-lg overflow-hidden border border-gray-200 mb-3">
                        <img
                            :src="selectedLayer.properties.src"
                            alt="Layer image"
                            class="w-full h-24 object-contain bg-gray-50"
                        />
                        <!-- Mask indicator -->
                        <div
                            v-if="selectedLayer.properties?.maskSrc || selectedLayer.properties?.clipPath"
                            class="absolute top-1 right-1 bg-black/70 text-white text-[9px] px-1.5 py-0.5 rounded flex items-center gap-1"
                            :title="t('graphics.properties.maskDescription')"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            {{ t('graphics.properties.masked') }}
                        </div>
                    </div>

                    <!-- Replace image button -->
                    <label class="flex items-center justify-center gap-2 px-3 py-2.5 border border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span class="text-xs text-gray-600">{{ t('graphics.properties.replaceImage') }}</span>
                        <input
                            type="file"
                            accept="image/*"
                            @change="handleImageReplace"
                            class="hidden"
                        />
                    </label>

                    <!-- Image fit mode -->
                    <div class="mt-3">
                        <label class="block text-[10px] text-gray-500 mb-1">{{ t('graphics.properties.imageFit') }}</label>
                        <div class="flex bg-gray-50 border border-gray-200 rounded overflow-hidden">
                            <button
                                @click="updateImageFit('cover')"
                                :class="[
                                    'flex-1 py-1.5 text-[10px] font-medium transition-colors border-r border-gray-200',
                                    (selectedLayer.properties?.fit || 'cover') === 'cover'
                                        ? 'bg-white text-gray-900 shadow-sm'
                                        : 'text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                {{ t('graphics.properties.fitCover') }}
                            </button>
                            <button
                                @click="updateImageFit('contain')"
                                :class="[
                                    'flex-1 py-1.5 text-[10px] font-medium transition-colors border-r border-gray-200',
                                    selectedLayer.properties?.fit === 'contain'
                                        ? 'bg-white text-gray-900 shadow-sm'
                                        : 'text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                {{ t('graphics.properties.fitContain') }}
                            </button>
                            <button
                                @click="updateImageFit('fill')"
                                :class="[
                                    'flex-1 py-1.5 text-[10px] font-medium transition-colors',
                                    selectedLayer.properties?.fit === 'fill'
                                        ? 'bg-white text-gray-900 shadow-sm'
                                        : 'text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                {{ t('graphics.properties.fitFill') }}
                            </button>
                        </div>
                    </div>

                    <!-- Mask indicator -->
                    <div v-if="selectedLayer.properties?.clipPath" class="mt-3 p-2 bg-purple-50 border border-purple-200 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                </svg>
                                <span class="text-xs font-medium text-purple-700">{{ t('graphics.properties.masked') }}</span>
                            </div>
                            <button
                                @click="updateProperty('clipPath', null)"
                                class="text-[10px] text-purple-600 hover:text-purple-800 font-medium"
                                :title="t('graphics.properties.removeMask')"
                            >
                                {{ t('graphics.properties.removeMask') }}
                            </button>
                        </div>
                        <p class="text-[10px] text-purple-600 mt-1">
                            {{ t('graphics.properties.maskDescription') }}
                        </p>
                    </div>
                </div>
            </template>

            <!-- Line properties -->
            <template v-if="selectedLayer.type === 'line'">
                <!-- Stroke -->
                <div class="px-3 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">
                            {{ t('graphics.properties.stroke') }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 mb-3">
                        <input
                            :value="selectedLayer.properties?.stroke || '#000000'"
                            @input="updateProperty('stroke', $event.target.value)"
                            type="color"
                            class="w-9 h-9 rounded cursor-pointer border border-gray-300"
                            style="padding: 2px;"
                        />
                        <input
                            :value="selectedLayer.properties?.stroke || '#000000'"
                            @input="updateProperty('stroke', $event.target.value)"
                            type="text"
                            class="flex-1 px-2.5 py-2 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white uppercase transition-colors font-mono"
                        />
                    </div>
                    <!-- Stroke width -->
                    <ScrubberInput
                        :model-value="selectedLayer.properties?.strokeWidth || 2"
                        @update:model-value="updateProperty('strokeWidth', $event)"
                        :label="t('graphics.properties.strokeWidth')"
                        suffix="px"
                        :min="1"
                        :max="50"
                        :sensitivity="0.2"
                    />
                </div>

                <!-- Line style -->
                <div class="px-3 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <span class="text-xs font-medium text-gray-900">
                            {{ t('graphics.properties.lineStyle') }}
                        </span>
                    </div>

                    <!-- Line cap -->
                    <div class="mb-3">
                        <label class="block text-[10px] text-gray-500 mb-1">{{ t('graphics.properties.lineCap') }}</label>
                        <div class="flex bg-gray-50 border border-gray-200 rounded overflow-hidden">
                            <button
                                @click="updateProperty('lineCap', 'butt')"
                                :class="[
                                    'flex-1 py-1.5 text-[10px] font-medium transition-colors border-r border-gray-200',
                                    (selectedLayer.properties?.lineCap || 'round') === 'butt'
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                ]"
                            >
                                Butt
                            </button>
                            <button
                                @click="updateProperty('lineCap', 'round')"
                                :class="[
                                    'flex-1 py-1.5 text-[10px] font-medium transition-colors border-r border-gray-200',
                                    (selectedLayer.properties?.lineCap || 'round') === 'round'
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                ]"
                            >
                                Round
                            </button>
                            <button
                                @click="updateProperty('lineCap', 'square')"
                                :class="[
                                    'flex-1 py-1.5 text-[10px] font-medium transition-colors',
                                    selectedLayer.properties?.lineCap === 'square'
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                ]"
                            >
                                Square
                            </button>
                        </div>
                    </div>

                    <!-- Dash pattern -->
                    <div>
                        <label class="block text-[10px] text-gray-500 mb-1">{{ t('graphics.properties.dashPattern') }}</label>
                        <div class="flex bg-gray-50 border border-gray-200 rounded overflow-hidden">
                            <button
                                @click="updateProperty('dash', [])"
                                :class="[
                                    'flex-1 py-1.5 text-[10px] font-medium transition-colors border-r border-gray-200',
                                    !selectedLayer.properties?.dash || selectedLayer.properties?.dash?.length === 0
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                ]"
                            >
                                Solid
                            </button>
                            <button
                                @click="updateProperty('dash', [10, 5])"
                                :class="[
                                    'flex-1 py-1.5 text-[10px] font-medium transition-colors border-r border-gray-200',
                                    JSON.stringify(selectedLayer.properties?.dash) === JSON.stringify([10, 5])
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                ]"
                            >
                                Dashed
                            </button>
                            <button
                                @click="updateProperty('dash', [2, 4])"
                                :class="[
                                    'flex-1 py-1.5 text-[10px] font-medium transition-colors',
                                    JSON.stringify(selectedLayer.properties?.dash) === JSON.stringify([2, 4])
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                ]"
                            >
                                Dotted
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
