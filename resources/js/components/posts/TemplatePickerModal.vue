<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';

const emit = defineEmits(['close', 'select']);

const { t } = useI18n();

const loading = ref(true);
const activeTab = ref('my'); // 'my' or 'library'
const templates = ref([]);
const libraryTemplates = ref([]);
const selectedTemplate = ref(null);

const currentTemplates = computed(() => {
    return activeTab.value === 'my' ? templates.value : libraryTemplates.value;
});

const fetchTemplates = async () => {
    loading.value = true;
    try {
        const [myResponse, libraryResponse] = await Promise.all([
            axios.get('/api/v1/templates'),
            axios.get('/api/v1/library/templates'),
        ]);
        templates.value = myResponse.data.data || [];
        libraryTemplates.value = libraryResponse.data.data || [];
    } catch (error) {
        console.error('Failed to fetch templates:', error);
    } finally {
        loading.value = false;
    }
};

const selectTemplate = (template) => {
    selectedTemplate.value = template;
};

const confirmSelection = () => {
    if (selectedTemplate.value) {
        emit('select', selectedTemplate.value);
    }
};

onMounted(fetchTemplates);
</script>

<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="emit('close')">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-4 max-h-[90vh] flex flex-col">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ t('posts.media.fromTemplates') }}
                </h2>
                <button @click="emit('close')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Tabs -->
            <div class="px-6 pt-4 border-b border-gray-200">
                <nav class="flex space-x-6">
                    <button
                        @click="activeTab = 'my'"
                        class="pb-3 text-sm font-medium border-b-2 transition-colors"
                        :class="activeTab === 'my'
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'"
                    >
                        {{ t('posts.media.myTemplates') }}
                        <span class="ml-1 text-xs bg-gray-100 px-2 py-0.5 rounded-full">
                            {{ templates.length }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'library'"
                        class="pb-3 text-sm font-medium border-b-2 transition-colors"
                        :class="activeTab === 'library'
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'"
                    >
                        {{ t('posts.media.templateLibrary') }}
                        <span class="ml-1 text-xs bg-gray-100 px-2 py-0.5 rounded-full">
                            {{ libraryTemplates.length }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6">
                <div v-if="loading" class="flex items-center justify-center py-12">
                    <LoadingSpinner size="lg" />
                </div>

                <div v-else-if="currentTemplates.length === 0" class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-gray-500">
                        {{ activeTab === 'my' ? t('posts.media.noMyTemplates') : t('posts.media.noLibraryTemplates') }}
                    </p>
                    <RouterLink
                        v-if="activeTab === 'my'"
                        :to="{ name: 'templates' }"
                        class="mt-4 inline-flex items-center text-blue-600 hover:text-blue-700"
                    >
                        {{ t('posts.media.createTemplate') }}
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </RouterLink>
                </div>

                <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    <button
                        v-for="template in currentTemplates"
                        :key="template.id"
                        @click="selectTemplate(template)"
                        class="group relative aspect-square rounded-lg overflow-hidden border-2 transition-all"
                        :class="selectedTemplate?.id === template.id
                            ? 'border-blue-500 ring-2 ring-blue-200'
                            : 'border-gray-200 hover:border-gray-300'"
                    >
                        <!-- Thumbnail -->
                        <img
                            v-if="template.thumbnail_url"
                            :src="template.thumbnail_url"
                            :alt="template.name"
                            class="w-full h-full object-cover"
                        />
                        <div
                            v-else
                            class="w-full h-full flex items-center justify-center"
                            :style="{ backgroundColor: template.background_color || '#f3f4f6' }"
                        >
                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>

                        <!-- Selected indicator -->
                        <div
                            v-if="selectedTemplate?.id === template.id"
                            class="absolute top-2 right-2 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center"
                        >
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>

                        <!-- Overlay with name -->
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-3">
                            <p class="text-white text-sm font-medium truncate">{{ template.name }}</p>
                            <p class="text-white/70 text-xs">{{ template.width }} × {{ template.height }}</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50 rounded-b-xl">
                <p v-if="selectedTemplate" class="text-sm text-gray-600">
                    {{ t('posts.media.selectedTemplate', { name: selectedTemplate.name }) }}
                </p>
                <div v-else></div>
                <div class="flex items-center space-x-3">
                    <Button variant="secondary" @click="emit('close')">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        :disabled="!selectedTemplate"
                        @click="confirmSelection"
                    >
                        {{ t('posts.media.editAndAdd') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
