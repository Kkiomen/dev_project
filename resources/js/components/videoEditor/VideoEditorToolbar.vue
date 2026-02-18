<script setup>
import { useI18n } from 'vue-i18n';

const props = defineProps({
    title: { type: String, default: '' },
    status: { type: String, default: '' },
    statusLabel: { type: String, default: '' },
    isProcessing: { type: Boolean, default: false },
    canUndo: { type: Boolean, default: false },
    canRedo: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['back', 'undo', 'redo', 'save', 'export']);
const { t } = useI18n();
</script>

<template>
    <div class="h-12 px-3 border-b border-gray-800 flex items-center justify-between shrink-0 bg-gray-900/80">
        <!-- Left: Back + Title -->
        <div class="flex items-center gap-2 min-w-0">
            <button
                @click="emit('back')"
                class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition shrink-0"
                :title="t('videoEditor.toolbar.back')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
            </button>

            <div class="min-w-0">
                <h1 class="text-sm font-medium text-white truncate">{{ title || '...' }}</h1>
            </div>

            <span
                v-if="statusLabel"
                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium shrink-0"
                :class="{
                    'bg-green-500/20 text-green-400': status === 'completed',
                    'bg-purple-500/20 text-purple-400': status === 'transcribed',
                    'bg-amber-500/20 text-amber-400': isProcessing,
                    'bg-red-500/20 text-red-400': status === 'failed',
                }"
            >
                {{ statusLabel }}
            </span>
        </div>

        <!-- Center: Undo / Redo -->
        <div class="flex items-center gap-1">
            <button
                @click="emit('undo')"
                :disabled="!canUndo"
                class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition disabled:opacity-30 disabled:cursor-not-allowed"
                :title="t('videoEditor.toolbar.undo') + ' (Ctrl+Z)'"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                </svg>
            </button>
            <button
                @click="emit('redo')"
                :disabled="!canRedo"
                class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition disabled:opacity-30 disabled:cursor-not-allowed"
                :title="t('videoEditor.toolbar.redo') + ' (Ctrl+Shift+Z)'"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3" />
                </svg>
            </button>
        </div>

        <!-- Right: Save + Export -->
        <div class="flex items-center gap-2 shrink-0">
            <button
                @click="emit('save')"
                :disabled="saving"
                class="px-3 py-1 text-xs font-medium rounded-lg border border-gray-700 text-gray-300 hover:text-white hover:bg-gray-800 transition disabled:opacity-50"
            >
                {{ saving ? t('videoEditor.toolbar.saving') : t('videoEditor.toolbar.save') }}
            </button>
            <button
                @click="emit('export')"
                class="px-3 py-1 text-xs font-medium rounded-lg bg-violet-600 hover:bg-violet-700 text-white transition"
            >
                {{ t('videoEditor.toolbar.export') }}
            </button>
        </div>
    </div>
</template>
