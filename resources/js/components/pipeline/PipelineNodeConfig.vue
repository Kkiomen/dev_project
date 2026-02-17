<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePipelinesStore } from '@/stores/pipelines';
import { useManagerStore } from '@/stores/manager';

const { t } = useI18n();
const store = usePipelinesStore();
const managerStore = useManagerStore();

const node = computed(() => store.selectedNode);
const nodeType = computed(() => node.value?.type || '');
const config = computed(() => node.value?.data?.config || {});

const updateConfig = (key, value) => {
    if (!node.value) return;
    const newConfig = { ...config.value, [key]: value };
    store.updateNodeData(node.value.id, { config: newConfig });
};

const updateLabel = (value) => {
    if (!node.value) return;
    store.updateNodeData(node.value.id, { label: value });
};

const deleteNode = () => {
    if (!node.value) return;
    store.removeNode(node.value.id);
};

const close = () => {
    store.setSelectedNode(null);
};

const imageSourceOptions = ['upload', 'gallery', 'url'];

// WaveSpeed.ai supported text-to-image models
const aiModelOptions = [
    { value: 'google/nano-banana/text-to-image', label: 'Nano Banana' },
    { value: 'google/nano-banana-pro/text-to-image', label: 'Nano Banana Pro' },
    { value: 'openai/gpt-image-1.5/text-to-image', label: 'GPT Image 1.5' },
    { value: 'openai/gpt-image-1/text-to-image', label: 'GPT Image 1' },
    { value: 'openai/dall-e-3/text-to-image', label: 'DALL-E 3' },
    { value: 'bytedance/seedream-v4.5/text-to-image', label: 'Seedream 4.5' },
    { value: 'bytedance/seedream-v3.1/text-to-image', label: 'Seedream 3.1' },
    { value: 'bytedance/dreamina-v3.0/text-to-image', label: 'Dreamina 3.0' },
    { value: 'alibaba/wan-2.6/text-to-image', label: 'Wan 2.6' },
    { value: 'alibaba/wan-2.5/text-to-image', label: 'Wan 2.5' },
    { value: 'wavespeed-ai/qwen-image/text-to-image', label: 'Qwen Image' },
    { value: 'x-ai/grok-imagine-image/text-to-image', label: 'Grok Imagine' },
    { value: 'kwaivgi/kling-image-o3/text-to-image', label: 'Kling O3' },
];

const aspectRatioDimensions = {
    '1:1': { width: 1024, height: 1024 },
    '16:9': { width: 1344, height: 768 },
    '9:16': { width: 768, height: 1344 },
    '4:3': { width: 1152, height: 896 },
    '3:4': { width: 896, height: 1152 },
};

const setAspectRatio = (ratio) => {
    if (!node.value) return;
    const dims = aspectRatioDimensions[ratio] || {};
    const newConfig = {
        ...config.value,
        aspect_ratio: ratio,
        width: dims.width || config.value.width || 1024,
        height: dims.height || config.value.height || 1024,
    };
    store.updateNodeData(node.value.id, { config: newConfig });
};

const configParameters = computed(() => config.value.parameters || {});
const parameterEntries = computed(() => Object.entries(configParameters.value));

const addParameter = () => {
    const params = { ...configParameters.value };
    const key = `param_${Object.keys(params).length + 1}`;
    params[key] = '';
    updateConfig('parameters', params);
};

const updateParameterKey = (oldKey, newKey) => {
    if (oldKey === newKey) return;
    const params = { ...configParameters.value };
    const value = params[oldKey];
    delete params[oldKey];
    params[newKey] = value;
    updateConfig('parameters', params);
};

const updateParameterValue = (key, value) => {
    const params = { ...configParameters.value };
    params[key] = value;
    updateConfig('parameters', params);
};

const removeParameter = (key) => {
    const params = { ...configParameters.value };
    delete params[key];
    updateConfig('parameters', params);
};

const templates = computed(() => managerStore.designTemplates || []);
const loadTemplates = () => {
    if (templates.value.length === 0) {
        managerStore.fetchDesignTemplates();
    }
};

watch(nodeType, (type) => {
    if (type === 'template') {
        loadTemplates();
    }
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-x-4"
        enter-to-class="opacity-100 translate-x-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-x-0"
        leave-to-class="opacity-0 translate-x-4"
    >
        <div
            v-if="node && nodeType !== 'ai_image_generator'"
            class="absolute right-4 top-4 z-20 w-72 bg-white/95 backdrop-blur-sm border border-gray-200 rounded-xl shadow-lg overflow-hidden"
        >
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-gray-100 text-gray-600">
                        {{ t(`pipeline.nodeTypes.${nodeType}`) }}
                    </span>
                </div>
                <button
                    @click="close"
                    class="p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="p-4 max-h-[60vh] overflow-y-auto space-y-4">
                <!-- Label -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ t('pipeline.config.nodeLabel') }}</label>
                    <input
                        :value="node.data?.label || ''"
                        @input="updateLabel($event.target.value)"
                        type="text"
                        :placeholder="t('pipeline.config.labelPlaceholder')"
                        class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                    />
                </div>

                <hr class="border-gray-100" />

                <!-- === IMAGE INPUT CONFIG === -->
                <template v-if="nodeType === 'image_input'">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-2">{{ t('pipeline.config.imageSource') }}</label>
                        <div class="flex gap-1">
                            <button
                                v-for="opt in imageSourceOptions"
                                :key="opt"
                                @click="updateConfig('source', opt)"
                                :class="[
                                    'flex-1 px-2 py-1.5 text-[10px] font-medium rounded-lg border transition',
                                    config.source === opt
                                        ? 'bg-indigo-50 border-indigo-200 text-indigo-600'
                                        : 'bg-gray-50 border-gray-200 text-gray-500 hover:border-gray-300',
                                ]"
                            >
                                {{ t(`pipeline.config.${opt}`) }}
                            </button>
                        </div>
                    </div>

                    <div v-if="config.source === 'url'">
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ t('pipeline.config.imageUrl') }}</label>
                        <input
                            :value="config.image_url || ''"
                            @input="updateConfig('image_url', $event.target.value)"
                            type="url"
                            :placeholder="t('pipeline.config.imageUrlPlaceholder')"
                            class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                        />
                    </div>
                </template>

                <!-- === TEXT INPUT CONFIG === -->
                <template v-if="nodeType === 'text_input'">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ t('pipeline.config.text') }}</label>
                        <textarea
                            :value="config.text || ''"
                            @input="updateConfig('text', $event.target.value)"
                            :placeholder="t('pipeline.config.textPlaceholder')"
                            rows="5"
                            class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition resize-none"
                        />
                    </div>

                    <hr class="border-gray-100" />

                    <!-- Parameters -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-2">{{ t('pipeline.config.parameters') }}</label>
                        <div class="space-y-2">
                            <div
                                v-for="[key, value] in parameterEntries"
                                :key="key"
                                class="flex items-center gap-1.5"
                            >
                                <input
                                    :value="key"
                                    @change="updateParameterKey(key, $event.target.value)"
                                    :placeholder="t('pipeline.config.parameterKey')"
                                    class="w-1/3 px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-[11px] text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                                />
                                <input
                                    :value="value"
                                    @input="updateParameterValue(key, $event.target.value)"
                                    :placeholder="t('pipeline.config.parameterValue')"
                                    class="flex-1 px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-[11px] text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                                />
                                <button
                                    @click="removeParameter(key)"
                                    class="p-1 rounded text-gray-300 hover:text-red-500 transition shrink-0"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <button
                            @click="addParameter"
                            class="mt-2 w-full px-3 py-1.5 text-[11px] font-medium text-indigo-600 bg-indigo-50 border border-indigo-100 rounded-lg hover:bg-indigo-100 transition"
                        >
                            {{ t('pipeline.config.addParameter') }}
                        </button>
                    </div>
                </template>

                <!-- === TEMPLATE CONFIG === -->
                <template v-if="nodeType === 'template'">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-2">{{ t('pipeline.config.selectTemplate') }}</label>
                        <div v-if="managerStore.designTemplatesLoading" class="text-xs text-gray-400 text-center py-3">...</div>
                        <div v-else-if="templates.length === 0" class="text-xs text-gray-400 text-center py-3">
                            {{ t('pipeline.config.noTemplates') }}
                        </div>
                        <div v-else class="space-y-1 max-h-48 overflow-y-auto">
                            <button
                                v-for="tpl in templates"
                                :key="tpl.id"
                                @click="updateConfig('template_id', tpl.id); updateConfig('template_name', tpl.name); updateConfig('template_thumbnail_url', tpl.thumbnail_url || '')"
                                :class="[
                                    'w-full flex items-center gap-2 p-2 rounded-lg border text-left transition',
                                    config.template_id === tpl.id
                                        ? 'bg-indigo-50 border-indigo-200 text-indigo-700'
                                        : 'bg-gray-50 border-gray-200 text-gray-600 hover:border-gray-300',
                                ]"
                            >
                                <div v-if="tpl.thumbnail_url" class="w-8 h-8 rounded-md bg-gray-100 overflow-hidden shrink-0">
                                    <img :src="tpl.thumbnail_url" class="w-full h-full object-cover" />
                                </div>
                                <div v-else class="w-8 h-8 rounded-md bg-gray-100 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-medium truncate">{{ tpl.name }}</div>
                                    <div class="text-[10px] text-gray-400">{{ tpl.dimensions }}</div>
                                </div>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- === AI IMAGE GENERATOR CONFIG === -->
                <template v-if="nodeType === 'ai_image_generator'">
                    <!-- Prompt -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ t('pipeline.config.prompt') }}</label>
                        <textarea
                            :value="config.prompt || ''"
                            @input="updateConfig('prompt', $event.target.value)"
                            :placeholder="t('pipeline.config.promptPlaceholder')"
                            rows="5"
                            class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition resize-none"
                        />
                    </div>

                    <!-- Model -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ t('pipeline.aiGenerator.model') }}</label>
                        <select
                            :value="config.model || ''"
                            @change="updateConfig('model', $event.target.value)"
                            class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition cursor-pointer"
                        >
                            <option value="" disabled>{{ t('pipeline.aiGenerator.modelPlaceholder') }}</option>
                            <option v-for="model in aiModelOptions" :key="model.value" :value="model.value">
                                {{ model.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Dimensions (width × height) -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ t('pipeline.aiGenerator.dimensions') }}</label>
                        <div class="flex items-center gap-2">
                            <input
                                :value="config.width || 1024"
                                @input="updateConfig('width', +$event.target.value)"
                                type="number"
                                min="256"
                                max="4096"
                                step="64"
                                class="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                            />
                            <span class="text-gray-400 text-xs">×</span>
                            <input
                                :value="config.height || 1024"
                                @input="updateConfig('height', +$event.target.value)"
                                type="number"
                                min="256"
                                max="4096"
                                step="64"
                                class="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                            />
                        </div>
                    </div>

                    <!-- Aspect Ratio presets -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-2">{{ t('pipeline.aiGenerator.aspectRatio') }}</label>
                        <div class="flex gap-1">
                            <button
                                v-for="ratio in ['1:1', '16:9', '9:16', '4:3', '3:4']"
                                :key="ratio"
                                @click="setAspectRatio(ratio)"
                                :class="[
                                    'flex-1 px-2 py-1.5 text-[10px] font-medium rounded-lg border transition cursor-pointer',
                                    (config.aspect_ratio || '1:1') === ratio
                                        ? 'bg-indigo-50 border-indigo-200 text-indigo-600'
                                        : 'bg-gray-50 border-gray-200 text-gray-500 hover:border-gray-300',
                                ]"
                            >
                                {{ ratio }}
                            </button>
                        </div>
                    </div>

                    <!-- Batch Size -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ t('pipeline.aiGenerator.batchSize') }}</label>
                        <input
                            :value="config.batch_size || 1"
                            @input="updateConfig('batch_size', +$event.target.value)"
                            type="number"
                            min="1"
                            max="10"
                            class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                        />
                    </div>
                </template>

                <!-- === IMAGE ANALYSIS CONFIG === -->
                <template v-if="nodeType === 'image_analysis'">
                    <p class="text-xs text-gray-400">{{ t('pipeline.nodeDescriptions.image_analysis') }}</p>
                </template>

                <!-- === TEMPLATE RENDER CONFIG === -->
                <template v-if="nodeType === 'template_render'">
                    <p class="text-xs text-gray-400">{{ t('pipeline.nodeDescriptions.template_render') }}</p>
                </template>

                <!-- === OUTPUT CONFIG === -->
                <template v-if="nodeType === 'output'">
                    <p class="text-xs text-gray-400">{{ t('pipeline.nodeDescriptions.output') }}</p>
                </template>

                <!-- Delete Node -->
                <hr class="border-gray-100" />
                <button
                    @click="deleteNode"
                    class="w-full px-3 py-2 text-xs font-medium text-red-500 bg-red-50 border border-red-100 rounded-lg hover:bg-red-100 transition"
                >
                    {{ t('pipeline.config.deleteNode') }}
                </button>
            </div>
        </div>
    </Transition>
</template>
