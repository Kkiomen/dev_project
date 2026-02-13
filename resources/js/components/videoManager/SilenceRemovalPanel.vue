<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SegmentTimeline from './SegmentTimeline.vue';

const { t } = useI18n();

const props = defineProps({
    segments: { type: Array, default: () => [] },
    duration: { type: Number, default: 0 },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['remove-silence']);

const minSilence = ref(0.5);
const padding = ref(0.1);

const silenceStats = computed(() => {
    if (!props.segments.length || !props.duration) return { count: 0, totalSilence: 0, percentage: 0 };

    let totalSpeech = 0;
    let lastEnd = 0;
    let silenceCount = 0;

    for (const seg of props.segments) {
        if (seg.start > lastEnd + minSilence.value) {
            silenceCount++;
        }
        totalSpeech += seg.end - seg.start;
        lastEnd = seg.end;
    }

    const totalSilence = props.duration - totalSpeech;
    const percentage = props.duration > 0 ? Math.round((totalSilence / props.duration) * 100) : 0;

    return { count: silenceCount, totalSilence: Math.round(totalSilence * 10) / 10, percentage };
});

const formatTime = (seconds) => {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
};

const handleRemoveSilence = () => {
    emit('remove-silence', { min_silence: minSilence.value, padding: padding.value });
};
</script>

<template>
    <div class="space-y-5">
        <!-- Timeline visualization -->
        <div>
            <h4 class="text-sm font-medium text-gray-300 mb-3">{{ t('videoManager.editor.segmentMap') }}</h4>
            <SegmentTimeline :segments="segments" :duration="duration" />
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-3">
            <div class="bg-gray-800/50 rounded-lg p-3 text-center">
                <p class="text-lg font-bold text-white">{{ silenceStats.count }}</p>
                <p class="text-xs text-gray-500">{{ t('videoManager.editor.silenceGaps') }}</p>
            </div>
            <div class="bg-gray-800/50 rounded-lg p-3 text-center">
                <p class="text-lg font-bold text-white">{{ formatTime(silenceStats.totalSilence) }}</p>
                <p class="text-xs text-gray-500">{{ t('videoManager.editor.totalSilence') }}</p>
            </div>
            <div class="bg-gray-800/50 rounded-lg p-3 text-center">
                <p class="text-lg font-bold text-white">{{ silenceStats.percentage }}%</p>
                <p class="text-xs text-gray-500">{{ t('videoManager.editor.silencePercentage') }}</p>
            </div>
        </div>

        <!-- Settings -->
        <div class="space-y-4">
            <div>
                <label class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-300">{{ t('videoManager.editor.minSilenceThreshold') }}</span>
                    <span class="text-sm text-violet-400 font-medium">{{ minSilence }}s</span>
                </label>
                <input
                    v-model.number="minSilence"
                    type="range"
                    min="0.1"
                    max="5"
                    step="0.1"
                    class="w-full h-1.5 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-violet-500"
                />
                <div class="flex justify-between text-xs text-gray-600 mt-1">
                    <span>0.1s</span>
                    <span>5.0s</span>
                </div>
            </div>

            <div>
                <label class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-300">{{ t('videoManager.editor.padding') }}</span>
                    <span class="text-sm text-violet-400 font-medium">{{ padding }}s</span>
                </label>
                <input
                    v-model.number="padding"
                    type="range"
                    min="0"
                    max="1"
                    step="0.05"
                    class="w-full h-1.5 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-violet-500"
                />
                <div class="flex justify-between text-xs text-gray-600 mt-1">
                    <span>0s</span>
                    <span>1.0s</span>
                </div>
            </div>
        </div>

        <!-- Action -->
        <button
            @click="handleRemoveSilence"
            :disabled="processing || segments.length === 0"
            class="w-full py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
            <svg v-if="processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" fill="none" stroke-width="3" />
                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" fill="none" stroke-width="3" stroke-linecap="round" />
            </svg>
            {{ processing ? t('videoManager.editor.processing') : t('videoManager.editor.removeSilence') }}
        </button>
    </div>
</template>
