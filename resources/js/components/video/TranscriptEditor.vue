<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    segments: { type: Array, default: () => [] },
    isTranscribing: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['save']);

const { t } = useI18n();
const editableSegments = ref([]);
const hasChanges = ref(false);

watch(() => props.segments, (newSegments) => {
    editableSegments.value = newSegments.map(seg => ({ ...seg }));
    hasChanges.value = false;
}, { immediate: true, deep: true });

function updateSegmentText(index, text) {
    editableSegments.value[index].text = text;
    hasChanges.value = true;
}

function deleteSegment(index) {
    editableSegments.value.splice(index, 1);
    hasChanges.value = true;
}

function mergeWithNext(index) {
    if (index >= editableSegments.value.length - 1) return;
    const current = editableSegments.value[index];
    const next = editableSegments.value[index + 1];
    current.text = current.text + ' ' + next.text;
    current.end = next.end;
    if (current.words && next.words) {
        current.words = [...current.words, ...next.words];
    }
    editableSegments.value.splice(index + 1, 1);
    hasChanges.value = true;
}

function saveChanges() {
    emit('save', editableSegments.value);
}

function formatTime(seconds) {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    const ms = Math.floor((seconds % 1) * 10);
    return `${m}:${s.toString().padStart(2, '0')}.${ms}`;
}
</script>

<template>
    <div class="p-4">
        <!-- Transcribing State -->
        <div v-if="isTranscribing" class="text-center py-12">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600 mx-auto mb-4"></div>
            <h3 class="text-lg font-medium text-gray-700 mb-1">{{ t('videoEditor.transcript.transcribing') }}</h3>
            <p class="text-sm text-gray-500">{{ t('videoEditor.transcript.transcribingDescription') }}</p>
        </div>

        <!-- No Segments -->
        <div v-else-if="editableSegments.length === 0" class="text-center py-12">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="text-sm text-gray-500">{{ t('videoEditor.transcript.noSegments') }}</p>
        </div>

        <!-- Segments Editor -->
        <div v-else>
            <!-- Toolbar -->
            <div class="flex items-center justify-between mb-4">
                <span class="text-sm text-gray-500">
                    {{ t('videoEditor.transcript.segmentCount', { count: editableSegments.length }) }}
                </span>
                <button
                    v-if="hasChanges"
                    @click="saveChanges"
                    :disabled="saving"
                    class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center gap-1"
                >
                    <div v-if="saving" class="animate-spin rounded-full h-3 w-3 border-b-2 border-white"></div>
                    {{ saving ? t('videoEditor.transcript.saving') : t('videoEditor.transcript.saveChanges') }}
                </button>
            </div>

            <!-- Segment List -->
            <div class="space-y-2">
                <div
                    v-for="(segment, index) in editableSegments"
                    :key="index"
                    class="group flex gap-3 p-3 rounded-lg border border-gray-100 hover:border-gray-200 transition-colors"
                >
                    <!-- Timestamp -->
                    <div class="flex-shrink-0 text-xs text-gray-400 font-mono pt-1 w-20">
                        {{ formatTime(segment.start) }}
                        <br />
                        {{ formatTime(segment.end) }}
                    </div>

                    <!-- Text -->
                    <div class="flex-1">
                        <textarea
                            :value="segment.text"
                            @input="updateSegmentText(index, $event.target.value)"
                            class="w-full text-sm text-gray-800 bg-transparent border-none resize-none focus:outline-none focus:ring-0 p-0"
                            rows="2"
                        ></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="flex-shrink-0 flex flex-col gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                            v-if="index < editableSegments.length - 1"
                            @click="mergeWithNext(index)"
                            class="p-1 text-gray-400 hover:text-blue-600 rounded"
                            :title="t('videoEditor.transcript.merge')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <button
                            @click="deleteSegment(index)"
                            class="p-1 text-gray-400 hover:text-red-600 rounded"
                            :title="t('videoEditor.transcript.delete')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
