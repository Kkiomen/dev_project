<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useGraphicsStore } from '@/stores/graphics';

const { t } = useI18n();

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    template: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close']);

const graphicsStore = useGraphicsStore();
const copiedSection = ref(null);

// Get modifiable layers (text and image layers)
const modifiableLayers = computed(() => {
    return graphicsStore.layers.filter(layer =>
        layer.type === 'text' || layer.type === 'image'
    ).map(layer => ({
        id: layer.public_id || layer.id,
        key: layer.layer_key || layer.name?.toLowerCase().replace(/\s+/g, '_') || `layer_${layer.id}`,
        name: layer.name,
        type: layer.type,
        currentValue: layer.type === 'text'
            ? layer.properties?.text
            : layer.properties?.src,
    }));
});

// Generate example modifications object
const exampleModifications = computed(() => {
    const mods = {};
    modifiableLayers.value.forEach(layer => {
        if (layer.type === 'text') {
            mods[layer.key] = {
                text: layer.currentValue || 'Your custom text here',
            };
        } else if (layer.type === 'image') {
            mods[layer.key] = {
                src: 'https://example.com/your-image.jpg',
            };
        }
    });
    return mods;
});

// API endpoint URL
const apiEndpoint = computed(() => {
    const baseUrl = window.location.origin;
    return `${baseUrl}/api/v1/templates/${props.template.public_id}`;
});

// Full request body example
const requestBodyExample = computed(() => {
    return JSON.stringify({
        modifications: exampleModifications.value,
    }, null, 2);
});

// cURL example
const curlExample = computed(() => {
    return `curl -X GET "${apiEndpoint.value}/layers" \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json"`;
});

// JavaScript fetch example
const fetchExample = computed(() => {
    return `// 1. Get template with layers
const response = await fetch('${apiEndpoint.value}/layers', {
  headers: {
    'Authorization': 'Bearer YOUR_API_TOKEN',
    'Content-Type': 'application/json',
  },
});
const { data: layers } = await response.json();

// 2. Modify layers and render in browser using Konva.js
// The modifications object structure:
const modifications = ${JSON.stringify(exampleModifications.value, null, 2)};

// 3. After rendering, upload the generated image
const formData = new FormData();
formData.append('image', blob, 'generated.png');
formData.append('modifications', JSON.stringify(modifications));

await fetch('${apiEndpoint.value}/images', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_API_TOKEN',
  },
  body: formData,
});`;
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

const close = () => {
    emit('close');
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
        >
            <!-- Backdrop -->
            <div
                class="absolute inset-0 bg-black/50"
                @click="close"
            />

            <!-- Modal -->
            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ t('graphics.apiDocs.title') }}</h2>
                            <p class="text-sm text-gray-500">{{ template.name }}</p>
                        </div>
                    </div>
                    <button
                        @click="close"
                        class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Template Info -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-900 mb-2">{{ t('graphics.apiDocs.templateInfo') }}</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500">Template ID:</span>
                                <code class="ml-2 px-2 py-0.5 bg-gray-200 rounded text-gray-800 font-mono text-xs">{{ template.public_id }}</code>
                            </div>
                            <div>
                                <span class="text-gray-500">{{ t('graphics.apiDocs.dimensions') }}:</span>
                                <span class="ml-2 text-gray-800">{{ template.width }} x {{ template.height }}px</span>
                            </div>
                        </div>
                    </div>

                    <!-- Modifiable Layers -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 mb-3">{{ t('graphics.apiDocs.modifiableLayers') }}</h3>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ t('graphics.apiDocs.layerKey') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ t('graphics.apiDocs.layerName') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ t('graphics.apiDocs.layerType') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ t('graphics.apiDocs.currentValue') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="layer in modifiableLayers" :key="layer.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-2">
                                            <code class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded font-mono text-xs">{{ layer.key }}</code>
                                        </td>
                                        <td class="px-4 py-2 text-gray-900">{{ layer.name }}</td>
                                        <td class="px-4 py-2">
                                            <span :class="[
                                                'px-2 py-0.5 rounded text-xs font-medium',
                                                layer.type === 'text' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'
                                            ]">
                                                {{ layer.type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-500 text-xs truncate max-w-[200px]">
                                            {{ layer.currentValue || '-' }}
                                        </td>
                                    </tr>
                                    <tr v-if="modifiableLayers.length === 0">
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                            {{ t('graphics.apiDocs.noModifiableLayers') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- API Endpoints -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 mb-3">{{ t('graphics.apiDocs.endpoints') }}</h3>
                        <div class="space-y-3">
                            <!-- Get Template Layers -->
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs font-bold">GET</span>
                                    <code class="text-sm font-mono text-gray-800">{{ apiEndpoint }}/layers</code>
                                </div>
                                <p class="text-sm text-gray-500">{{ t('graphics.apiDocs.getLayersDesc') }}</p>
                            </div>

                            <!-- Upload Generated Image -->
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded text-xs font-bold">POST</span>
                                    <code class="text-sm font-mono text-gray-800">{{ apiEndpoint }}/images</code>
                                </div>
                                <p class="text-sm text-gray-500">{{ t('graphics.apiDocs.postImageDesc') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Modifications Structure -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-medium text-gray-900">{{ t('graphics.apiDocs.modificationsStructure') }}</h3>
                            <button
                                @click="copyToClipboard(JSON.stringify(exampleModifications, null, 2), 'mods')"
                                class="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                {{ copiedSection === 'mods' ? t('graphics.apiDocs.copied') : t('graphics.apiDocs.copy') }}
                            </button>
                        </div>
                        <pre class="bg-gray-900 text-gray-100 rounded-lg p-4 text-sm font-mono overflow-x-auto"><code>{{ JSON.stringify(exampleModifications, null, 2) }}</code></pre>
                    </div>

                    <!-- Code Examples -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 mb-3">{{ t('graphics.apiDocs.codeExamples') }}</h3>

                        <!-- cURL -->
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-medium text-gray-500 uppercase">cURL</span>
                                <button
                                    @click="copyToClipboard(curlExample, 'curl')"
                                    class="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    {{ copiedSection === 'curl' ? t('graphics.apiDocs.copied') : t('graphics.apiDocs.copy') }}
                                </button>
                            </div>
                            <pre class="bg-gray-900 text-gray-100 rounded-lg p-4 text-sm font-mono overflow-x-auto"><code>{{ curlExample }}</code></pre>
                        </div>

                        <!-- JavaScript -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-medium text-gray-500 uppercase">JavaScript</span>
                                <button
                                    @click="copyToClipboard(fetchExample, 'js')"
                                    class="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    {{ copiedSection === 'js' ? t('graphics.apiDocs.copied') : t('graphics.apiDocs.copy') }}
                                </button>
                            </div>
                            <pre class="bg-gray-900 text-gray-100 rounded-lg p-4 text-sm font-mono overflow-x-auto whitespace-pre-wrap"><code>{{ fetchExample }}</code></pre>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <p class="text-xs text-gray-500">
                        {{ t('graphics.apiDocs.note') }}
                    </p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
