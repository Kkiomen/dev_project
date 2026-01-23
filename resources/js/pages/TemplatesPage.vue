<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useGraphicsStore } from '@/stores/graphics';
import { useConfirm } from '@/composables/useConfirm';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';

const { t } = useI18n();

const router = useRouter();
const graphicsStore = useGraphicsStore();
const { confirm } = useConfirm();

const loading = ref(true);
const showCreateModal = ref(false);
const newTemplateName = ref('');
const creating = ref(false);

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
        });
        showCreateModal.value = false;
        newTemplateName.value = '';
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
                <Button @click="showCreateModal = true">
                    {{ t('graphics.templates.newTemplate') }}
                </Button>
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
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('common.name') }}
                            </label>
                            <input
                                v-model="newTemplateName"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                :placeholder="t('base.namePlaceholder')"
                                @keyup.enter="handleCreate"
                            />
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
    </div>
</template>
