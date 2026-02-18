<template>
    <div class="flex flex-col h-screen bg-gray-950 text-white">
        <!-- Loading -->
        <div v-if="store.loading" class="flex items-center justify-center h-full">
            <div class="text-center">
                <div class="w-8 h-8 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-3" />
                <p class="text-sm text-gray-400">{{ t('nle.loading') }}</p>
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="store.error && !store.composition" class="flex items-center justify-center h-full">
            <div class="text-center max-w-md">
                <svg class="w-12 h-12 text-red-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <p class="text-sm text-red-400 mb-4">{{ store.error }}</p>
                <button
                    @click="$router.back()"
                    class="px-4 py-2 text-sm bg-gray-700 hover:bg-gray-600 rounded transition-colors"
                >
                    {{ t('nle.goBack') }}
                </button>
            </div>
        </div>

        <!-- Editor -->
        <template v-else-if="store.composition">
            <!-- Toolbar -->
            <NleToolbar
                :can-undo="history.canUndo()"
                :can-redo="history.canRedo()"
                @undo="history.undo()"
                @redo="history.redo()"
                @save="autoSave.saveNow()"
                @render="handleRender"
            />

            <!-- Layout: Media + Preview + Inspector -->
            <NleLayout>
                <template #media>
                    <NleMediaPanel :media-items="mediaItems" @upload="handleUpload" />
                </template>

                <template #preview>
                    <NlePreviewPanel ref="previewRef" />
                </template>

                <template #inspector>
                    <NleInspectorPanel />
                </template>
            </NleLayout>

            <!-- Timeline -->
            <NleTimelinePanel />
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVideoEditorStore } from '@/stores/videoEditorNew';
import { useNleHistory } from '@/composables/useNleHistory';
import { useNleAutoSave } from '@/composables/useNleAutoSave';
import NleToolbar from './NleToolbar.vue';
import NleLayout from './NleLayout.vue';
import NleMediaPanel from './NleMediaPanel.vue';
import NlePreviewPanel from './NlePreviewPanel.vue';
import NleInspectorPanel from './NleInspectorPanel.vue';
import NleTimelinePanel from './NleTimelinePanel.vue';

const { t } = useI18n();
const store = useVideoEditorStore();
const history = useNleHistory();
const autoSave = useNleAutoSave();

const props = defineProps({
    projectId: { type: String, required: true },
});

const previewRef = ref(null);

const mediaItems = computed(() => {
    // Build media items from composition sources
    const items = [];
    const seen = new Set();

    if (store.composition?.tracks) {
        for (const track of store.composition.tracks) {
            for (const el of track.elements) {
                if (el.source && !seen.has(el.source)) {
                    seen.add(el.source);
                    items.push({
                        id: el.id,
                        type: el.type,
                        name: el.name,
                        source: el.source,
                        duration: el.duration,
                    });
                }
            }
        }
    }

    // Include uploaded media not yet on the timeline
    for (const media of store.uploadedMedia) {
        if (!seen.has(media.source)) {
            seen.add(media.source);
            items.push(media);
        }
    }

    return items;
});

// Keyboard shortcuts
function handleKeyDown(event) {
    // Ignore if focused in input/textarea
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)) return;

    if ((event.ctrlKey || event.metaKey) && event.key === 'z') {
        event.preventDefault();
        if (event.shiftKey) {
            history.redo();
        } else {
            history.undo();
        }
    } else if ((event.ctrlKey || event.metaKey) && event.key === 's') {
        event.preventDefault();
        autoSave.saveNow();
    } else if (event.key === ' ') {
        event.preventDefault();
        store.togglePlayback();
    } else if ((event.ctrlKey || event.metaKey) && event.key === 'a') {
        event.preventDefault();
        store.selectAllElements();
    } else if (event.key === 'Delete' || event.key === 'Backspace') {
        if (store.selectedElementIds.length) {
            history.captureState();
            store.removeElements([...store.selectedElementIds]);
        }
    } else if ((event.ctrlKey || event.metaKey) && event.key === 'd') {
        event.preventDefault();
        if (store.selectedElementId) {
            history.captureState();
            store.duplicateElement(store.selectedElementId);
        }
    } else if (event.key === 'Escape') {
        store.clearSelection();
    }
}

function handleRender() {
    // Will be expanded in Phase 2
    autoSave.saveNow();
}

async function handleUpload(files) {
    for (const file of files) {
        const result = await store.uploadMedia(file);
        if (result) {
            store.uploadedMedia.push({
                id: `uploaded_${Date.now()}_${Math.random().toString(36).substr(2, 6)}`,
                type: result.type,
                name: result.name,
                source: result.source,
                duration: result.type === 'image' ? 5 : (result.duration || 5),
            });
        }
    }
}

onMounted(async () => {
    document.addEventListener('keydown', handleKeyDown);
    await store.loadProject(props.projectId);

    if (store.composition) {
        history.captureState();
    }
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown);
    store.$reset();
    history.clear();
});
</script>
