<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';

const emit = defineEmits(['close', 'select', 'edit']);

const { t } = useI18n();

// Form data
const formData = ref({
    header: '',
    subtitle: '',
    paragraph: '',
    social_handle: '',
    primary_color: '#3B82F6',
    secondary_color: '#1F2937',
    main_image: '',
});

// State
const loading = ref(false);
const generating = ref(false);
const error = ref(null);
const previews = ref([]);
const selectedPreview = ref(null);
const imagePreview = ref(null);
const imageInputRef = ref(null);

// Check if any form data is filled
const hasData = computed(() => {
    return formData.value.header ||
           formData.value.subtitle ||
           formData.value.paragraph ||
           formData.value.social_handle ||
           formData.value.primary_color !== '#3B82F6' ||
           formData.value.secondary_color !== '#1F2937' ||
           formData.value.main_image;
});

// Handle image upload
const handleImageUpload = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        formData.value.main_image = e.target.result;
        imagePreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
};

// Remove uploaded image
const removeImage = () => {
    formData.value.main_image = '';
    imagePreview.value = null;
    if (imageInputRef.value) {
        imageInputRef.value.value = '';
    }
};

// Generate previews
const generatePreviews = async () => {
    if (!hasData.value) {
        error.value = t('posts.template_preview.fill_at_least_one');
        return;
    }

    generating.value = true;
    error.value = null;

    try {
        const response = await axios.post('/api/v1/library/templates/preview', {
            data: formData.value,
        });
        previews.value = response.data.previews || [];

        if (previews.value.length === 0) {
            error.value = t('posts.template_preview.no_tagged_templates');
        }
    } catch (err) {
        console.error('Failed to generate previews:', err);
        error.value = err.response?.data?.message || t('posts.template_preview.generation_failed');
    } finally {
        generating.value = false;
    }
};

// Select a preview
const selectPreview = (preview) => {
    if (preview.error) return;
    selectedPreview.value = preview;
};

// Confirm selection - add to post
const confirmSelection = () => {
    if (selectedPreview.value) {
        emit('select', selectedPreview.value);
    }
};

// Edit in editor
const editSelected = () => {
    if (selectedPreview.value) {
        emit('edit', selectedPreview.value);
    }
};

// Check service health on mount
const checkHealth = async () => {
    try {
        const response = await axios.get('/api/v1/library/templates/preview/health');
        if (response.data.status !== 'ok') {
            error.value = t('posts.template_preview.service_unavailable');
        }
    } catch (err) {
        error.value = t('posts.template_preview.service_unavailable');
    }
};

onMounted(checkHealth);
</script>

<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="emit('close')">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-6xl mx-4 max-h-[90vh] flex flex-col">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ t('posts.template_preview.title') }}
                </h2>
                <button @click="emit('close')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-hidden flex">
                <!-- Left panel - Form -->
                <div class="w-80 border-r border-gray-200 p-6 overflow-y-auto">
                    <h3 class="text-sm font-medium text-gray-900 mb-4">
                        {{ t('posts.template_preview.your_content') }}
                    </h3>

                    <!-- Header input -->
                    <div class="mb-4">
                        <label class="block text-sm text-gray-700 mb-1">
                            {{ t('posts.template_preview.header_text') }}
                        </label>
                        <input
                            v-model="formData.header"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            :placeholder="t('posts.template_preview.header_placeholder')"
                        />
                    </div>

                    <!-- Subtitle input -->
                    <div class="mb-4">
                        <label class="block text-sm text-gray-700 mb-1">
                            {{ t('posts.template_preview.subtitle_text') }}
                        </label>
                        <input
                            v-model="formData.subtitle"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            :placeholder="t('posts.template_preview.subtitle_placeholder')"
                        />
                    </div>

                    <!-- Paragraph input -->
                    <div class="mb-4">
                        <label class="block text-sm text-gray-700 mb-1">
                            {{ t('posts.template_preview.paragraph_text') }}
                        </label>
                        <textarea
                            v-model="formData.paragraph"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                            :placeholder="t('posts.template_preview.paragraph_placeholder')"
                        />
                    </div>

                    <!-- Social handle input -->
                    <div class="mb-4">
                        <label class="block text-sm text-gray-700 mb-1">
                            {{ t('posts.template_preview.social_handle') }}
                        </label>
                        <input
                            v-model="formData.social_handle"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            :placeholder="t('posts.template_preview.social_handle_placeholder')"
                        />
                    </div>

                    <!-- Main image upload -->
                    <div class="mb-4">
                        <label class="block text-sm text-gray-700 mb-1">
                            {{ t('posts.template_preview.main_image') }}
                        </label>
                        <div v-if="imagePreview" class="relative mb-2">
                            <img
                                :src="imagePreview"
                                alt="Preview"
                                class="w-full h-32 object-cover rounded-lg border border-gray-300"
                            />
                            <button
                                @click="removeImage"
                                class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full hover:bg-red-600"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <label
                            v-else
                            class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors"
                        >
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="mt-1 text-xs text-gray-500">{{ t('posts.template_preview.upload_image') }}</span>
                            <input
                                ref="imageInputRef"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="handleImageUpload"
                            />
                        </label>
                    </div>

                    <!-- Color pickers -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">
                                {{ t('posts.template_preview.primary_color') }}
                            </label>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="formData.primary_color"
                                    type="color"
                                    class="w-8 h-8 rounded border border-gray-300 cursor-pointer"
                                />
                                <input
                                    v-model="formData.primary_color"
                                    type="text"
                                    class="flex-1 px-2 py-1 border border-gray-300 rounded text-xs font-mono"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">
                                {{ t('posts.template_preview.secondary_color') }}
                            </label>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="formData.secondary_color"
                                    type="color"
                                    class="w-8 h-8 rounded border border-gray-300 cursor-pointer"
                                />
                                <input
                                    v-model="formData.secondary_color"
                                    type="text"
                                    class="flex-1 px-2 py-1 border border-gray-300 rounded text-xs font-mono"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Generate button -->
                    <Button
                        class="w-full"
                        :disabled="generating || !hasData"
                        @click="generatePreviews"
                    >
                        <LoadingSpinner v-if="generating" size="sm" class="mr-2" />
                        {{ generating ? t('posts.template_preview.generating') : t('posts.template_preview.generate') }}
                    </Button>

                    <!-- Error message -->
                    <p v-if="error" class="mt-3 text-sm text-red-600">
                        {{ error }}
                    </p>
                </div>

                <!-- Right panel - Preview grid -->
                <div class="flex-1 p-6 overflow-y-auto bg-gray-50">
                    <div v-if="generating" class="flex items-center justify-center h-full">
                        <div class="text-center">
                            <LoadingSpinner size="lg" />
                            <p class="mt-4 text-gray-500">{{ t('posts.template_preview.generating_message') }}</p>
                        </div>
                    </div>

                    <div v-else-if="previews.length === 0" class="flex items-center justify-center h-full">
                        <div class="text-center">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-gray-500">{{ t('posts.template_preview.empty_state') }}</p>
                            <p class="text-sm text-gray-400 mt-1">{{ t('posts.template_preview.empty_state_hint') }}</p>
                        </div>
                    </div>

                    <div v-else class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                        <button
                            v-for="preview in previews"
                            :key="preview.id"
                            @click="selectPreview(preview)"
                            class="group relative aspect-square rounded-lg overflow-hidden border-2 transition-all bg-white"
                            :class="[
                                preview.error
                                    ? 'border-red-200 cursor-not-allowed opacity-60'
                                    : selectedPreview?.id === preview.id
                                        ? 'border-blue-500 ring-2 ring-blue-200'
                                        : 'border-gray-200 hover:border-gray-300'
                            ]"
                        >
                            <!-- Preview image -->
                            <img
                                v-if="preview.preview_url"
                                :src="preview.preview_url"
                                :alt="preview.name"
                                class="w-full h-full object-contain"
                            />

                            <!-- Error state -->
                            <div
                                v-else-if="preview.error"
                                class="w-full h-full flex items-center justify-center bg-red-50"
                            >
                                <div class="text-center p-4">
                                    <svg class="w-8 h-8 mx-auto text-red-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <p class="text-sm text-red-600">{{ preview.error }}</p>
                                </div>
                            </div>

                            <!-- Selected indicator -->
                            <div
                                v-if="selectedPreview?.id === preview.id && !preview.error"
                                class="absolute top-2 right-2 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center"
                            >
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>

                            <!-- Overlay with name -->
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-3">
                                <p class="text-white text-sm font-medium truncate">{{ preview.name }}</p>
                                <p v-if="preview.width && preview.height" class="text-white/70 text-xs">
                                    {{ preview.width }} × {{ preview.height }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50 rounded-b-xl">
                <p v-if="selectedPreview" class="text-sm text-gray-600">
                    {{ t('posts.template_preview.selected', { name: selectedPreview.name }) }}
                </p>
                <div v-else></div>
                <div class="flex items-center space-x-3">
                    <Button variant="secondary" @click="emit('close')">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        variant="secondary"
                        :disabled="!selectedPreview"
                        @click="editSelected"
                    >
                        {{ t('posts.template_preview.edit_in_editor') }}
                    </Button>
                    <Button
                        :disabled="!selectedPreview"
                        @click="confirmSelection"
                    >
                        {{ t('posts.template_preview.add_to_post') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
