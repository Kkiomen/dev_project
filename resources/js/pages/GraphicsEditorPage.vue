<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useGraphicsStore } from '@/stores/graphics';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import GraphicsEditor from '@/components/graphics/GraphicsEditor.vue';

const { t } = useI18n();

const props = defineProps({
    templateId: {
        type: String,
        required: true,
    },
});

const router = useRouter();
const graphicsStore = useGraphicsStore();

const loading = ref(true);

const fetchData = async () => {
    loading.value = true;
    try {
        await graphicsStore.fetchTemplate(props.templateId);
    } catch (error) {
        console.error('Failed to fetch template:', error);
        router.push({ name: 'dashboard' });
    } finally {
        loading.value = false;
    }
};

onMounted(fetchData);
watch(() => props.templateId, fetchData);

onUnmounted(() => {
    graphicsStore.reset();
});

// Warn about unsaved changes
const handleBeforeUnload = (e) => {
    if (graphicsStore.isDirty) {
        e.preventDefault();
        e.returnValue = t('graphics.editor.unsavedChanges');
    }
};

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
});
</script>

<template>
    <div class="absolute inset-0 flex flex-col bg-white">
        <!-- Loading -->
        <div v-if="loading" class="flex-1 flex items-center justify-center">
            <LoadingSpinner size="lg" class="text-blue-600" />
        </div>

        <!-- Editor -->
        <GraphicsEditor
            v-else-if="graphicsStore.currentTemplate"
            :template="graphicsStore.currentTemplate"
            class="flex-1 min-h-0"
        />
    </div>
</template>
