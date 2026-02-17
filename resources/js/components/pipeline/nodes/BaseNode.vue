<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePipelinesStore } from '@/stores/pipelines';

const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, default: () => ({}) },
    nodeType: { type: String, required: true },
    iconPath: { type: String, default: '' },
    accentDot: { type: String, default: 'bg-gray-400' },
    generating: { type: Boolean, default: false },
});

const emit = defineEmits(['toolbar-action']);

const { t } = useI18n();
const store = usePipelinesStore();

const isHovered = ref(false);
const isSelected = computed(() => store.selectedNodeId === props.id);
const label = computed(() => props.data?.label || t(`pipeline.nodeTypes.${props.nodeType}`));
</script>

<template>
    <div
        class="relative"
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
    >
        <!-- Floating toolbar -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
        >
            <div
                v-if="isHovered || isSelected"
                class="absolute -top-9 left-1/2 -translate-x-1/2 z-10 flex items-center gap-0.5 px-1.5 py-1 bg-white border border-gray-200 rounded-lg shadow-sm"
            >
                <!-- Execute -->
                <button
                    @click.stop="emit('toolbar-action', 'execute')"
                    class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition cursor-pointer"
                    :title="t('pipeline.nodeToolbar.execute')"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                    </svg>
                </button>
                <!-- Edit -->
                <button
                    @click.stop="emit('toolbar-action', 'edit')"
                    class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition cursor-pointer"
                    :title="t('pipeline.nodeToolbar.edit')"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Z" />
                    </svg>
                </button>
                <!-- Download -->
                <button
                    @click.stop="emit('toolbar-action', 'download')"
                    class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition cursor-pointer"
                    :title="t('pipeline.nodeToolbar.download')"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </button>
                <!-- Delete -->
                <button
                    @click.stop="emit('toolbar-action', 'delete')"
                    class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-red-500 hover:bg-red-50 transition cursor-pointer"
                    :title="t('pipeline.nodeToolbar.delete')"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </button>
                <!-- More -->
                <button
                    @click.stop="emit('toolbar-action', 'more')"
                    class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition cursor-pointer"
                    :title="t('pipeline.nodeToolbar.more')"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                </button>
            </div>
        </Transition>

        <!-- Node card -->
        <div
            :class="[
                'group min-w-[240px] max-w-[320px] rounded-xl bg-white transition-all duration-150',
                generating
                    ? 'border-2 border-blue-400 shadow-lg ring-2 ring-blue-400/30 animate-border-pulse'
                    : isSelected
                        ? 'border border-blue-500 shadow-lg ring-2 ring-blue-500/20'
                        : 'border border-transparent shadow-md hover:shadow-lg',
            ]"
        >
            <!-- Header -->
            <div class="flex items-center gap-2 px-3 py-2">
                <span :class="['w-2 h-2 rounded-full shrink-0', accentDot]" />
                <span class="text-xs font-medium text-gray-700 truncate flex-1">{{ label }}</span>
            </div>

            <!-- Content slot -->
            <div class="px-3 py-2.5">
                <slot />
            </div>
        </div>
    </div>
</template>
