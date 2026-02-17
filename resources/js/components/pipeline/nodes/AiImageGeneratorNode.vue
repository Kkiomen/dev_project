<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { Handle, Position } from '@vue-flow/core';
import { useI18n } from 'vue-i18n';
import BaseNode from './BaseNode.vue';
import Modal from '@/components/common/Modal.vue';
import { usePipelinesStore } from '@/stores/pipelines';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const store = usePipelinesStore();
const toast = useToast();
const showModal = ref(false);
const isGenerating = ref(false);

// --- Inline editing state ---
const isEditingPrompt = ref(false);
const promptDraft = ref('');
const activeDropdown = ref(null); // null | 'model' | 'batch' | 'aspect'
const promptTextareaRef = ref(null);
const dropdownRef = ref(null);

// --- Config data (mirrored from PipelineNodeConfig) ---
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

const batchSizeOptions = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

// --- Computed ---
const config = computed(() => props.data?.config || {});
const previewImage = computed(() => {
    // Ephemeral preview takes priority (just generated), then persisted config
    if (store.nodePreviewData?.[props.id]) return store.nodePreviewData[props.id];
    if (config.value.generated_image) return `/storage/${config.value.generated_image}`;
    return null;
});
const promptPreview = computed(() => config.value.prompt || '');
const aiModelLabels = {
    'google/nano-banana/text-to-image': 'Nano Banana',
    'google/nano-banana-pro/text-to-image': 'Nano Banana Pro',
    'openai/gpt-image-1.5/text-to-image': 'GPT Image 1.5',
    'openai/gpt-image-1/text-to-image': 'GPT Image 1',
    'openai/dall-e-3/text-to-image': 'DALL-E 3',
    'bytedance/seedream-v4.5/text-to-image': 'Seedream 4.5',
    'bytedance/seedream-v3.1/text-to-image': 'Seedream 3.1',
    'bytedance/dreamina-v3.0/text-to-image': 'Dreamina 3.0',
    'alibaba/wan-2.6/text-to-image': 'Wan 2.6',
    'alibaba/wan-2.5/text-to-image': 'Wan 2.5',
    'wavespeed-ai/qwen-image/text-to-image': 'Qwen Image',
    'x-ai/grok-imagine-image/text-to-image': 'Grok Imagine',
    'kwaivgi/kling-image-o3/text-to-image': 'Kling O3',
};
const modelName = computed(() => {
    const model = config.value.model;
    if (!model) return t('pipeline.aiGenerator.defaultModel');
    return aiModelLabels[model] || model;
});
const batchSize = computed(() => config.value.batch_size || 1);
const imageWidth = computed(() => config.value.width || 1024);
const imageHeight = computed(() => config.value.height || 1024);
const aspectRatio = computed(() => config.value.aspect_ratio || '1:1');
const generationCount = computed(() => {
    const results = store.currentRun?.node_results?.[props.id];
    return results?.count || config.value.generation_count || 1;
});

const instanceNumber = computed(() => {
    const sameType = store.nodes
        .filter(n => n.type === 'ai_image_generator')
        .sort((a, b) => a.id.localeCompare(b.id));
    return sameType.findIndex(n => n.id === props.id) + 1;
});

const instanceLabel = computed(() =>
    t('pipeline.aiGenerator.instanceLabel', { number: instanceNumber.value })
);

// --- Config update helpers ---
const updateConfig = (key, value) => {
    const newConfig = { ...config.value, [key]: value };
    store.updateNodeData(props.id, { config: newConfig });
};

const setAspectRatio = (ratio) => {
    const dims = aspectRatioDimensions[ratio] || {};
    const newConfig = {
        ...config.value,
        aspect_ratio: ratio,
        width: dims.width || config.value.width || 1024,
        height: dims.height || config.value.height || 1024,
    };
    store.updateNodeData(props.id, { config: newConfig });
    activeDropdown.value = null;
};

const selectModel = (value) => {
    updateConfig('model', value);
    activeDropdown.value = null;
};

const selectBatchSize = (size) => {
    updateConfig('batch_size', size);
    activeDropdown.value = null;
};

// --- Inline prompt editing ---
const startEditingPrompt = () => {
    promptDraft.value = config.value.prompt || '';
    isEditingPrompt.value = true;
    nextTick(() => {
        promptTextareaRef.value?.focus();
    });
};

const savePrompt = () => {
    updateConfig('prompt', promptDraft.value);
    isEditingPrompt.value = false;
};

const cancelPromptEdit = () => {
    isEditingPrompt.value = false;
};

const handlePromptKeydown = (event) => {
    if (event.key === 'Escape') {
        cancelPromptEdit();
    } else if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        savePrompt();
    }
};

// Check if this node has an incoming text connection
const hasTextConnection = computed(() =>
    store.edges.some(e => e.target === props.id && e.targetHandle === 'text')
);

// Check if this node has an incoming image connection (enables img2img mode)
const hasImageConnection = computed(() =>
    store.edges.some(e => e.target === props.id && e.targetHandle === 'image')
);

const currentStrength = computed(() => config.value.strength ?? 0.65);

const strengthPresets = [
    { value: 0.3, labelKey: 'pipeline.aiGenerator.strengthSubtle' },
    { value: 0.5, labelKey: 'pipeline.aiGenerator.strengthBalanced' },
    { value: 0.65, labelKey: 'pipeline.aiGenerator.strengthDefault' },
    { value: 0.8, labelKey: 'pipeline.aiGenerator.strengthStrong' },
    { value: 1.0, labelKey: 'pipeline.aiGenerator.strengthFull' },
];

const selectStrength = (value) => {
    updateConfig('strength', value);
    activeDropdown.value = null;
};

// --- Node execution ---
const executeNode = async () => {
    const pipelineId = store.currentPipeline?.id;
    if (!pipelineId) return;

    const prompt = config.value.prompt;
    // Allow execution without local prompt if text comes from a connected node
    if (!prompt && !hasTextConnection.value) {
        toast.error(t('pipeline.aiGenerator.promptRequired'));
        return;
    }

    isGenerating.value = true;
    try {
        // Save canvas first so backend has latest config
        if (store.isDirty) {
            await store.saveCanvas(pipelineId);
        }

        // Pass local prompt as manual input only if set; otherwise backend resolves from connections
        const inputs = prompt ? { text: prompt } : {};
        const result = await store.previewNode(pipelineId, props.id, inputs);

        if (result?.image) {
            store.updateNodePreviewData(props.id, `/storage/${result.image}`);
            // Persist to node config so it survives F5 refresh
            updateConfig('generated_image', result.image);
            await store.saveCanvas(pipelineId);
            toast.success(t('pipeline.aiGenerator.generated'));
        }
    } catch (error) {
        const msg = error.response?.data?.message || t('pipeline.aiGenerator.generateError');
        toast.error(msg);
    } finally {
        isGenerating.value = false;
    }
};

// --- Dropdown toggle ---
const toggleDropdown = (name) => {
    activeDropdown.value = activeDropdown.value === name ? null : name;
};

// --- Click outside to close dropdown ---
const handleClickOutside = (event) => {
    if (activeDropdown.value && dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        activeDropdown.value = null;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});

// --- Toolbar actions ---
const handleToolbarAction = (key) => {
    switch (key) {
        case 'edit':
            startEditingPrompt();
            break;
        case 'download':
            if (previewImage.value) {
                const a = document.createElement('a');
                a.href = previewImage.value;
                a.download = `ai-gen-${props.id}.png`;
                a.click();
            }
            break;
        case 'delete':
            store.removeNode(props.id);
            break;
        case 'execute':
        case 'regenerate':
            executeNode();
            break;
    }
};
</script>

<template>
    <div class="relative group">
    <!-- Input handles (functional VueFlow targets) -->
    <Handle type="target" :position="Position.Left" id="text" style="top: 25%" />
    <Handle type="target" :position="Position.Left" id="image" style="top: 50%" />
    <Handle type="target" :position="Position.Left" id="template" style="top: 75%" />

    <BaseNode
        :id="id"
        :data="{ ...data, label: instanceLabel }"
        node-type="ai_image_generator"
        accent-dot="bg-pink-500"
        :generating="isGenerating"
        icon-path="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"
        @toolbar-action="handleToolbarAction"
    >
        <!-- img2img mode indicator -->
        <div v-if="hasImageConnection" class="flex items-center gap-1.5 mb-1.5">
            <span class="inline-flex items-center gap-1 bg-purple-100 text-purple-600 text-[10px] font-medium px-1.5 py-0.5 rounded-full">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                </svg>
                {{ t('pipeline.aiGenerator.img2imgMode') }}
            </span>
        </div>

        <!-- Image preview with metadata overlay -->
        <div v-if="previewImage" class="group/image relative aspect-square rounded-lg overflow-hidden bg-gray-50">
            <img :src="previewImage" :alt="t('pipeline.nodeTypes.ai_image_generator')" class="w-full h-full object-cover" />

            <!-- Dark top overlay bar -->
            <div class="absolute top-0 inset-x-0 flex items-center justify-between px-2 py-1.5 bg-gradient-to-b from-black/50 to-transparent">
                <!-- Left: grid icon + generation count -->
                <div class="flex items-center gap-1.5 text-white/90 text-[11px]">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />
                    </svg>
                    <span class="font-medium">{{ generationCount }}</span>
                </div>

                <!-- Right: dimensions + expand -->
                <div class="flex items-center gap-2">
                    <span class="text-white/80 text-[10px] font-medium">{{ imageWidth }} &times; {{ imageHeight }}</span>
                    <button
                        @click.stop="showModal = true"
                        class="w-5 h-5 flex items-center justify-center rounded-full bg-black/30 ring-1 ring-white/20 text-white hover:bg-white/30 transition-colors cursor-pointer"
                        :title="t('pipeline.output.expand')"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Prompt overlay at bottom of image -->
            <div v-if="promptPreview" class="absolute bottom-0 inset-x-0 px-2 py-1.5 bg-gradient-to-t from-black/50 to-transparent">
                <p class="text-[11px] text-white/90 leading-relaxed line-clamp-1">{{ promptPreview }}</p>
            </div>

            <!-- Full-resolution modal -->
            <Modal :show="showModal" max-width="4xl" @close="showModal = false">
                <img :src="previewImage" :alt="t('pipeline.nodeTypes.ai_image_generator')" class="w-full h-auto rounded-lg" />
            </Modal>
        </div>

        <!-- Empty state / generating state -->
        <div v-else class="relative aspect-square rounded-lg overflow-hidden border-2 border-dashed border-gray-200 bg-gray-50/50">
            <!-- Generating overlay -->
            <div v-if="isGenerating" class="flex flex-col items-center justify-center gap-2 w-full h-full">
                <svg class="w-6 h-6 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                <span class="text-[10px] text-blue-500 font-medium">{{ t('pipeline.aiGenerator.generating') }}</span>
                <p v-if="promptPreview" class="text-[10px] text-gray-400 leading-relaxed line-clamp-2 px-3 text-center">{{ promptPreview }}</p>
            </div>

            <!-- Inline textarea (editing) -->
            <div v-else-if="isEditingPrompt" class="absolute inset-0 flex flex-col p-2 bg-white z-10">
                <textarea
                    ref="promptTextareaRef"
                    v-model="promptDraft"
                    :placeholder="t('pipeline.config.promptPlaceholder')"
                    @blur="savePrompt"
                    @keydown="handlePromptKeydown"
                    class="flex-1 w-full text-[11px] text-gray-700 placeholder-gray-400 bg-transparent border-none outline-none resize-none leading-relaxed"
                />
                <div class="flex items-center justify-end gap-1 pt-1 border-t border-gray-100">
                    <span class="text-[9px] text-gray-400 mr-auto">Ctrl+Enter</span>
                    <button
                        @mousedown.prevent="cancelPromptEdit"
                        class="px-1.5 py-0.5 text-[9px] text-gray-400 hover:text-gray-600 rounded transition"
                    >Esc</button>
                </div>
            </div>

            <!-- Placeholder (not editing) -->
            <div
                v-else
                class="flex flex-col items-center justify-center gap-1.5 w-full h-full cursor-pointer hover:bg-gray-100/50 transition-colors"
                @click.stop="startEditingPrompt"
            >
                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                </svg>
                <span class="text-[10px] text-gray-400">{{ t('pipeline.aiGenerator.clickToEditPrompt') }}</span>

                <!-- Prompt below placeholder if set -->
                <p v-if="promptPreview" class="text-[11px] text-gray-500 leading-relaxed line-clamp-2 px-3 text-center mt-1">{{ promptPreview }}</p>
            </div>
        </div>

        <!-- Prompt text below image (shown when image exists) -->
        <p v-if="previewImage && promptPreview" class="text-[11px] text-gray-500 leading-relaxed line-clamp-2 px-0.5 pt-1.5">{{ promptPreview }}</p>

        <!-- Bottom info bar with inline dropdowns — hidden by default, visible on hover -->
        <div ref="dropdownRef" :class="[
            'flex items-center gap-1.5 pt-2 flex-wrap transition-opacity duration-150',
            activeDropdown ? 'opacity-100' : 'opacity-0 group-hover:opacity-100',
        ]">
            <!-- Batch size chip + dropdown -->
            <div class="relative">
                <span
                    class="inline-flex items-center gap-0.5 bg-gray-100 text-gray-500 text-[10px] font-medium px-1.5 py-0.5 rounded-full cursor-pointer hover:bg-gray-200 transition-colors"
                    @click.stop="toggleDropdown('batch')"
                >
                    &times;{{ batchSize }}
                    <svg class="w-2.5 h-2.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </span>
                <Transition
                    enter-active-class="transition duration-100 ease-out"
                    enter-from-class="opacity-0 translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-1"
                >
                    <div
                        v-if="activeDropdown === 'batch'"
                        class="absolute bottom-full mb-1 left-0 z-30 w-28 max-h-48 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg py-1"
                    >
                        <button
                            v-for="size in batchSizeOptions"
                            :key="size"
                            @mousedown.prevent="selectBatchSize(size)"
                            :class="[
                                'w-full px-2.5 py-1 text-left text-[11px] transition',
                                batchSize === size
                                    ? 'bg-blue-50 text-blue-600 font-medium'
                                    : 'text-gray-600 hover:bg-gray-50',
                            ]"
                        >
                            &times;{{ size }}
                        </button>
                    </div>
                </Transition>
            </div>

            <!-- Model chip + dropdown -->
            <div class="relative">
                <span
                    class="inline-flex items-center gap-0.5 bg-gray-100 text-gray-500 text-[10px] px-1.5 py-0.5 rounded-full max-w-[140px] cursor-pointer hover:bg-gray-200 transition-colors"
                    @click.stop="toggleDropdown('model')"
                >
                    <span class="truncate">{{ modelName }}</span>
                    <svg class="w-2.5 h-2.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </span>
                <Transition
                    enter-active-class="transition duration-100 ease-out"
                    enter-from-class="opacity-0 translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-1"
                >
                    <div
                        v-if="activeDropdown === 'model'"
                        class="absolute bottom-full mb-1 left-0 z-30 w-44 max-h-56 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg py-1"
                    >
                        <button
                            v-for="model in aiModelOptions"
                            :key="model.value"
                            @mousedown.prevent="selectModel(model.value)"
                            :class="[
                                'w-full px-2.5 py-1.5 text-left text-[11px] transition',
                                config.model === model.value
                                    ? 'bg-blue-50 text-blue-600 font-medium'
                                    : 'text-gray-600 hover:bg-gray-50',
                            ]"
                        >
                            {{ model.label }}
                        </button>
                    </div>
                </Transition>
            </div>

            <!-- Strength chip + dropdown (only visible in img2img mode) -->
            <div v-if="hasImageConnection" class="relative">
                <span
                    class="inline-flex items-center gap-0.5 bg-purple-100 text-purple-600 text-[10px] font-medium px-1.5 py-0.5 rounded-full cursor-pointer hover:bg-purple-200 transition-colors"
                    @click.stop="toggleDropdown('strength')"
                >
                    {{ t('pipeline.aiGenerator.strength') }} {{ currentStrength }}
                    <svg class="w-2.5 h-2.5 shrink-0 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </span>
                <Transition
                    enter-active-class="transition duration-100 ease-out"
                    enter-from-class="opacity-0 translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-1"
                >
                    <div
                        v-if="activeDropdown === 'strength'"
                        class="absolute bottom-full mb-1 left-0 z-30 w-36 bg-white border border-gray-200 rounded-lg shadow-lg py-1"
                    >
                        <button
                            v-for="preset in strengthPresets"
                            :key="preset.value"
                            @mousedown.prevent="selectStrength(preset.value)"
                            :class="[
                                'w-full px-2.5 py-1.5 text-left text-[11px] flex items-center justify-between transition',
                                currentStrength === preset.value
                                    ? 'bg-purple-50 text-purple-600 font-medium'
                                    : 'text-gray-600 hover:bg-gray-50',
                            ]"
                        >
                            <span>{{ t(preset.labelKey) }}</span>
                            <span class="text-[9px] text-gray-400">{{ preset.value }}</span>
                        </button>
                    </div>
                </Transition>
            </div>

            <!-- Aspect ratio chip + dropdown -->
            <div class="relative ml-auto">
                <span
                    class="inline-flex items-center gap-0.5 text-[10px] text-gray-400 cursor-pointer hover:bg-gray-200 bg-gray-100 px-1.5 py-0.5 rounded-full transition-colors"
                    @click.stop="toggleDropdown('aspect')"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5M20.25 16.5V18A2.25 2.25 0 0 1 18 20.25h-1.5M3.75 16.5V18A2.25 2.25 0 0 0 6 20.25h1.5" />
                    </svg>
                    {{ aspectRatio }}
                    <svg class="w-2.5 h-2.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </span>
                <Transition
                    enter-active-class="transition duration-100 ease-out"
                    enter-from-class="opacity-0 translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-1"
                >
                    <div
                        v-if="activeDropdown === 'aspect'"
                        class="absolute bottom-full mb-1 right-0 z-30 w-40 bg-white border border-gray-200 rounded-lg shadow-lg py-1"
                    >
                        <button
                            v-for="(dims, ratio) in aspectRatioDimensions"
                            :key="ratio"
                            @mousedown.prevent="setAspectRatio(ratio)"
                            :class="[
                                'w-full px-2.5 py-1.5 text-left text-[11px] flex items-center justify-between transition',
                                aspectRatio === ratio
                                    ? 'bg-blue-50 text-blue-600 font-medium'
                                    : 'text-gray-600 hover:bg-gray-50',
                            ]"
                        >
                            <span>{{ ratio }}</span>
                            <span class="text-[9px] text-gray-400">{{ dims.width }}&times;{{ dims.height }}</span>
                        </button>
                    </div>
                </Transition>
            </div>

            <!-- Regenerate button -->
            <button
                @click.stop="handleToolbarAction('regenerate')"
                class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition cursor-pointer"
                :title="t('pipeline.nodeToolbar.regenerate')"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182M21.015 4.356v4.992" />
                </svg>
            </button>
        </div>
    </BaseNode>

    <!-- Output handle (functional VueFlow source) -->
    <Handle type="source" :position="Position.Right" id="image" />
    </div>
</template>
