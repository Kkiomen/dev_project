<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useGraphicsStore } from '@/stores/graphics';
import { useAuthStore } from '@/stores/auth';
import { useConfirm } from '@/composables/useConfirm';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import TemplateLibraryModal from '@/components/graphics/TemplateLibraryModal.vue';
import PsdUploadModal from '@/components/graphics/modals/PsdUploadModal.vue';
import TemplatePreviewModal from '@/components/posts/TemplatePreviewModal.vue';

const { t } = useI18n();

const router = useRouter();
const graphicsStore = useGraphicsStore();
const authStore = useAuthStore();
const { confirm } = useConfirm();

const loading = ref(true);
const showCreateModal = ref(false);
const showLibraryModal = ref(false);
const showPsdUploadModal = ref(false);
const showPreviewModal = ref(false);
const newTemplateName = ref('');
const newTemplateWidth = ref(1080);
const newTemplateHeight = ref(1080);
const creating = ref(false);

// Preset sizes
const presetSizes = [
    { label: 'Instagram Post', width: 1080, height: 1080 },
    { label: 'Instagram Story', width: 1080, height: 1920 },
    { label: 'Facebook Post', width: 1200, height: 630 },
    { label: 'Twitter Post', width: 1200, height: 675 },
    { label: 'YouTube Thumbnail', width: 1280, height: 720 },
    { label: 'LinkedIn Post', width: 1200, height: 627 },
    { label: 'Full HD', width: 1920, height: 1080 },
];

const applyPreset = (preset) => {
    newTemplateWidth.value = preset.width;
    newTemplateHeight.value = preset.height;
};

const fetchData = async () => {
    loading.value = true;
    try {
        await graphicsStore.fetchTemplates();
    } catch (error) {
        console.error('Failed to fetch templates:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchData);

const handleCreate = async () => {
    if (!newTemplateName.value.trim()) return;

    creating.value = true;
    try {
        const template = await graphicsStore.createTemplate({
            name: newTemplateName.value.trim(),
            width: newTemplateWidth.value,
            height: newTemplateHeight.value,
        });
        showCreateModal.value = false;
        newTemplateName.value = '';
        newTemplateWidth.value = 1080;
        newTemplateHeight.value = 1080;
        router.push({ name: 'template.editor', params: { templateId: template.id } });
    } catch (error) {
        console.error('Failed to create template:', error);
    } finally {
        creating.value = false;
    }
};

const handleEdit = (template) => {
    router.push({ name: 'template.editor', params: { templateId: template.id } });
};

const handleDuplicate = async (template) => {
    try {
        await graphicsStore.duplicateTemplate(template.id);
    } catch (error) {
        console.error('Failed to duplicate template:', error);
    }
};

const handleDelete = async (template) => {
    const confirmed = await confirm({
        title: t('graphics.templates.delete'),
        message: t('graphics.templates.deleteMessage', { name: template.name }),
        confirmText: t('common.delete'),
        danger: true,
    });

    if (confirmed) {
        try {
            await graphicsStore.deleteTemplate(template.id);
        } catch (error) {
            console.error('Failed to delete template:', error);
        }
    }
};

// Handle PSD import
const handlePsdImported = (newTemplate) => {
    showPsdUploadModal.value = false;
    router.push({ name: 'template.editor', params: { templateId: newTemplate.id } });
};

// Handle library template copied
const handleTemplateCopied = (newTemplate) => {
    showLibraryModal.value = false;
    fetchData();
};

// Handle preview select (add to nothing - just close)
const handlePreviewSelect = (preview) => {
    showPreviewModal.value = false;
};

// Handle preview edit
const handlePreviewEdit = (preview) => {
    showPreviewModal.value = false;
    if (preview?.id) {
        router.push({ name: 'template.editor', params: { templateId: preview.id } });
    }
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <RouterLink
                        :to="{ name: 'dashboard' }"
                        class="text-gray-500 hover:text-gray-700"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </RouterLink>
                    <h1 class="text-xl font-semibold text-gray-900">
                        {{ t('graphics.templates.title') }}
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Admin buttons -->
                    <template v-if="authStore.isAdmin">
                        <button
                            @click="showPreviewModal = true"
                            class="flex items-center gap-1.5 px-3 py-2 rounded-lg font-medium text-sm bg-green-50 text-green-700 hover:bg-green-100 border border-green-200 transition-all"
                            :title="t('posts.template_preview.preview_button')"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <span>{{ t('posts.template_preview.preview_button') }}</span>
                        </button>
                        <button
                            @click="showLibraryModal = true"
                            class="flex items-center gap-1.5 px-3 py-2 rounded-lg font-medium text-sm bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 transition-all"
                            :title="t('graphics.library.browse')"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <span>{{ t('graphics.library.titleShort') }}</span>
                        </button>
                        <button
                            @click="showPsdUploadModal = true"
                            class="flex items-center gap-1.5 px-3 py-2 rounded-lg font-medium text-sm bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200 transition-all"
                            :title="t('graphics.psd.uploadButton')"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <span>{{ t('graphics.psd.uploadButton') }}</span>
                        </button>
                    </template>
                    <Button @click="showCreateModal = true">
                        {{ t('graphics.templates.newTemplate') }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <LoadingSpinner size="lg" />
            </div>

            <!-- Empty state -->
            <div
                v-else-if="graphicsStore.templates.length === 0"
                class="text-center py-12"
            >
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">
                    {{ t('graphics.templates.noTemplates') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('graphics.templates.noTemplatesDescription') }}
                </p>
                <div class="mt-6">
                    <Button @click="showCreateModal = true">
                        {{ t('graphics.templates.newTemplate') }}
                    </Button>
                </div>
            </div>

            <!-- Template grid -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <div
                    v-for="template in graphicsStore.templates"
                    :key="template.id"
                    class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-md transition-shadow"
                >
                    <!-- Thumbnail -->
                    <div
                        class="aspect-square bg-gray-100 cursor-pointer relative group"
                        @click="handleEdit(template)"
                    >
                        <img
                            v-if="template.thumbnail_url"
                            :src="template.thumbnail_url"
                            :alt="template.name"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                            <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <!-- Hover overlay -->
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all flex items-center justify-center opacity-0 group-hover:opacity-100">
                            <span class="text-white font-medium">{{ t('common.edit') }}</span>
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium text-gray-900 truncate">
                                {{ template.name }}
                            </h3>
                            <div class="flex items-center space-x-1">
                                <button
                                    @click="handleDuplicate(template)"
                                    class="p-1 text-gray-400 hover:text-gray-600"
                                    :title="t('graphics.templates.duplicate')"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </button>
                                <button
                                    @click="handleDelete(template)"
                                    class="p-1 text-gray-400 hover:text-red-600"
                                    :title="t('graphics.templates.delete')"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ template.width }} x {{ template.height }}px
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create modal -->
        <teleport to="body">
            <div
                v-if="showCreateModal"
                class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                @click.self="showCreateModal = false"
            >
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                    <div class="p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">
                            {{ t('graphics.templates.create') }}
                        </h2>

                        <!-- Name -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('common.name') }}
                            </label>
                            <input
                                v-model="newTemplateName"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                :placeholder="t('base.namePlaceholder')"
                            />
                        </div>

                        <!-- Size presets -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ t('graphics.templates.presets') }}
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="preset in presetSizes"
                                    :key="preset.label"
                                    @click="applyPreset(preset)"
                                    class="px-3 py-1.5 text-xs rounded-full border transition-colors"
                                    :class="newTemplateWidth === preset.width && newTemplateHeight === preset.height
                                        ? 'bg-blue-100 border-blue-500 text-blue-700'
                                        : 'border-gray-300 text-gray-600 hover:border-gray-400'"
                                >
                                    {{ preset.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Custom size -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ t('graphics.properties.width') }}
                                </label>
                                <div class="relative">
                                    <input
                                        v-model.number="newTemplateWidth"
                                        type="number"
                                        min="100"
                                        max="4096"
                                        class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">px</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ t('graphics.properties.height') }}
                                </label>
                                <div class="relative">
                                    <input
                                        v-model.number="newTemplateHeight"
                                        type="number"
                                        min="100"
                                        max="4096"
                                        class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">px</span>
                                </div>
                            </div>
                        </div>

                        <!-- Size preview -->
                        <div class="mt-4 text-center text-sm text-gray-500">
                            {{ newTemplateWidth }} x {{ newTemplateHeight }} px
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3 rounded-b-lg">
                        <Button variant="secondary" @click="showCreateModal = false">
                            {{ t('common.cancel') }}
                        </Button>
                        <Button
                            :loading="creating"
                            :disabled="!newTemplateName.trim()"
                            @click="handleCreate"
                        >
                            {{ t('common.create') }}
                        </Button>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- Library Modal -->
        <TemplateLibraryModal
            :show="showLibraryModal"
            @close="showLibraryModal = false"
            @template-copied="handleTemplateCopied"
        />

        <!-- PSD Upload Modal -->
        <PsdUploadModal
            :show="showPsdUploadModal"
            @close="showPsdUploadModal = false"
            @imported="handlePsdImported"
        />

        <!-- Template Preview Modal -->
        <TemplatePreviewModal
            v-if="showPreviewModal"
            @close="showPreviewModal = false"
            @select="handlePreviewSelect"
            @edit="handlePreviewEdit"
        />
    </div>
</template>
