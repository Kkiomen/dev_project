<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePipelinesStore } from '@/stores/pipelines';

const props = defineProps({
    interactionMode: { type: String, default: 'select' },
});

const emit = defineEmits(['mode-change']);

const { t } = useI18n();
const store = usePipelinesStore();
const hoveredType = ref(null);
const hoveredTool = ref(null);

const grouped = computed(() => store.nodeTypesByCategory);

const onDragStart = (event, nodeType) => {
    event.dataTransfer.setData('application/pipeline-node-type', nodeType.type);
    event.dataTransfer.setData('application/pipeline-node-label', nodeType.label);
    event.dataTransfer.effectAllowed = 'move';
};

const getNodeIcon = (type) => {
    const icons = {
        image_input: 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z',
        text_input: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
        template: 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z',
        ai_image_generator: 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z',
        image_analysis: 'M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
        template_render: 'M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42',
        output: 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3',
    };
    return icons[type] || icons.output;
};

const getNodeColor = (type) => {
    const colors = {
        image_input: 'text-blue-500 hover:bg-blue-50',
        text_input: 'text-green-500 hover:bg-green-50',
        template: 'text-purple-500 hover:bg-purple-50',
        ai_image_generator: 'text-pink-500 hover:bg-pink-50',
        image_analysis: 'text-orange-500 hover:bg-orange-50',
        template_render: 'text-indigo-500 hover:bg-indigo-50',
        output: 'text-gray-500 hover:bg-gray-100',
    };
    return colors[type] || colors.output;
};
</script>

<template>
    <div class="absolute left-4 top-1/2 -translate-y-1/2 z-10 flex flex-col bg-white/90 backdrop-blur-sm border border-gray-200 rounded-xl shadow-sm p-1.5">
        <!-- Select tool -->
        <div
            class="relative"
            @mouseenter="hoveredTool = 'select'"
            @mouseleave="hoveredTool = null"
        >
            <button
                @click="emit('mode-change', 'select')"
                :class="[
                    'w-9 h-9 flex items-center justify-center rounded-lg transition-colors',
                    interactionMode === 'select'
                        ? 'bg-indigo-50 text-indigo-600'
                        : 'text-gray-400 hover:bg-gray-100 hover:text-gray-600',
                ]"
            >
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672L13.684 16.6m0 0l-2.51 2.225.569-9.47 5.227 7.917-3.286-.672zM12 2.25V4.5m5.834.166l-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243l-1.59-1.59" />
                </svg>
            </button>
            <Transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="opacity-0 translate-x-1"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition duration-75 ease-in"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-1"
            >
                <div
                    v-if="hoveredTool === 'select'"
                    class="absolute left-full top-1/2 -translate-y-1/2 ml-2 px-2.5 py-1 bg-gray-900 text-white text-[11px] font-medium rounded-lg whitespace-nowrap shadow-lg pointer-events-none"
                >
                    {{ t('pipeline.tools.select') }}
                    <div class="absolute right-full top-1/2 -translate-y-1/2 w-0 h-0 border-t-4 border-b-4 border-r-4 border-transparent border-r-gray-900" />
                </div>
            </Transition>
        </div>

        <!-- Pan tool -->
        <div
            class="relative"
            @mouseenter="hoveredTool = 'pan'"
            @mouseleave="hoveredTool = null"
        >
            <button
                @click="emit('mode-change', 'pan')"
                :class="[
                    'w-9 h-9 flex items-center justify-center rounded-lg transition-colors',
                    interactionMode === 'pan'
                        ? 'bg-indigo-50 text-indigo-600'
                        : 'text-gray-400 hover:bg-gray-100 hover:text-gray-600',
                ]"
            >
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.05 4.575a1.575 1.575 0 10-3.15 0v3.15M10.05 4.575a1.575 1.575 0 113.15 0v5.85M10.05 4.575v3.15M7.9 7.725a1.575 1.575 0 10-3.15 0v5.85a6.3 6.3 0 006.3 6.3h1.05a6.3 6.3 0 006.3-6.3v-3.15a1.575 1.575 0 00-3.15 0M13.2 4.575v5.85M7.9 13.575v-3.15" />
                </svg>
            </button>
            <Transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="opacity-0 translate-x-1"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition duration-75 ease-in"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-1"
            >
                <div
                    v-if="hoveredTool === 'pan'"
                    class="absolute left-full top-1/2 -translate-y-1/2 ml-2 px-2.5 py-1 bg-gray-900 text-white text-[11px] font-medium rounded-lg whitespace-nowrap shadow-lg pointer-events-none"
                >
                    {{ t('pipeline.tools.pan') }}
                    <div class="absolute right-full top-1/2 -translate-y-1/2 w-0 h-0 border-t-4 border-b-4 border-r-4 border-transparent border-r-gray-900" />
                </div>
            </Transition>
        </div>

        <!-- Separator -->
        <div class="mx-1.5 my-1 border-t border-gray-200" />

        <!-- Inputs -->
        <div
            v-for="nodeType in grouped.inputs"
            :key="nodeType.type"
            class="relative"
            @mouseenter="hoveredType = nodeType.type"
            @mouseleave="hoveredType = null"
        >
            <button
                draggable="true"
                @dragstart="(e) => onDragStart(e, nodeType)"
                :class="[
                    'w-9 h-9 flex items-center justify-center rounded-lg cursor-grab active:cursor-grabbing transition-colors',
                    getNodeColor(nodeType.type),
                ]"
            >
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="getNodeIcon(nodeType.type)" />
                </svg>
            </button>

            <!-- Tooltip -->
            <Transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="opacity-0 translate-x-1"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition duration-75 ease-in"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-1"
            >
                <div
                    v-if="hoveredType === nodeType.type"
                    class="absolute left-full top-1/2 -translate-y-1/2 ml-2 px-2.5 py-1 bg-gray-900 text-white text-[11px] font-medium rounded-lg whitespace-nowrap shadow-lg pointer-events-none"
                >
                    {{ t(`pipeline.nodeTypes.${nodeType.type}`) }}
                    <div class="absolute right-full top-1/2 -translate-y-1/2 w-0 h-0 border-t-4 border-b-4 border-r-4 border-transparent border-r-gray-900" />
                </div>
            </Transition>
        </div>

        <!-- Separator -->
        <div class="mx-1.5 my-1 border-t border-gray-200" />

        <!-- Processing -->
        <div
            v-for="nodeType in grouped.processing"
            :key="nodeType.type"
            class="relative"
            @mouseenter="hoveredType = nodeType.type"
            @mouseleave="hoveredType = null"
        >
            <button
                draggable="true"
                @dragstart="(e) => onDragStart(e, nodeType)"
                :class="[
                    'w-9 h-9 flex items-center justify-center rounded-lg cursor-grab active:cursor-grabbing transition-colors',
                    getNodeColor(nodeType.type),
                ]"
            >
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="getNodeIcon(nodeType.type)" />
                </svg>
            </button>

            <Transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="opacity-0 translate-x-1"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition duration-75 ease-in"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-1"
            >
                <div
                    v-if="hoveredType === nodeType.type"
                    class="absolute left-full top-1/2 -translate-y-1/2 ml-2 px-2.5 py-1 bg-gray-900 text-white text-[11px] font-medium rounded-lg whitespace-nowrap shadow-lg pointer-events-none"
                >
                    {{ t(`pipeline.nodeTypes.${nodeType.type}`) }}
                    <div class="absolute right-full top-1/2 -translate-y-1/2 w-0 h-0 border-t-4 border-b-4 border-r-4 border-transparent border-r-gray-900" />
                </div>
            </Transition>
        </div>

        <!-- Separator -->
        <div class="mx-1.5 my-1 border-t border-gray-200" />

        <!-- Output -->
        <div
            v-for="nodeType in grouped.output"
            :key="nodeType.type"
            class="relative"
            @mouseenter="hoveredType = nodeType.type"
            @mouseleave="hoveredType = null"
        >
            <button
                draggable="true"
                @dragstart="(e) => onDragStart(e, nodeType)"
                :class="[
                    'w-9 h-9 flex items-center justify-center rounded-lg cursor-grab active:cursor-grabbing transition-colors',
                    getNodeColor(nodeType.type),
                ]"
            >
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="getNodeIcon(nodeType.type)" />
                </svg>
            </button>

            <Transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="opacity-0 translate-x-1"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition duration-75 ease-in"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-1"
            >
                <div
                    v-if="hoveredType === nodeType.type"
                    class="absolute left-full top-1/2 -translate-y-1/2 ml-2 px-2.5 py-1 bg-gray-900 text-white text-[11px] font-medium rounded-lg whitespace-nowrap shadow-lg pointer-events-none"
                >
                    {{ t(`pipeline.nodeTypes.${nodeType.type}`) }}
                    <div class="absolute right-full top-1/2 -translate-y-1/2 w-0 h-0 border-t-4 border-b-4 border-r-4 border-transparent border-r-gray-900" />
                </div>
            </Transition>
        </div>
    </div>
</template>
