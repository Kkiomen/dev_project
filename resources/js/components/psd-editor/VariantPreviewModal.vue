<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePsdEditorStore } from '@/stores/psdEditor';
import axios from 'axios';

const emit = defineEmits(['close']);

const { t } = useI18n();
const store = usePsdEditorStore();

// Form data
const formData = ref({
    header: '',
    subtitle: '',
    paragraph: '',
    social_handle: '',
    primary_color: '#3B82F6',
    secondary_color: '#1F2937',
    main_image: '',
    logo: '',
});

// State
const generating = ref(false);
const error = ref(null);
const previews = ref([]);
const imagePreview = ref(null);
const imageInputRef = ref(null);
const logoPreview = ref(null);
const logoInputRef = ref(null);

// Check if any form data is filled
const hasData = computed(() => {
    return formData.value.header ||
           formData.value.subtitle ||
           formData.value.paragraph ||
           formData.value.social_handle ||
           formData.value.main_image ||
           formData.value.logo;
});

// Handle image upload (generic for main_image and logo)
const handleImageUpload = (event, field = 'main_image') => {
    const file = event.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        formData.value[field] = e.target.result;
        if (field === 'main_image') {
            imagePreview.value = e.target.result;
        } else if (field === 'logo') {
            logoPreview.value = e.target.result;
        }
    };
    reader.readAsDataURL(file);
};

// Remove uploaded image
const removeImage = (field = 'main_image') => {
    formData.value[field] = '';
    if (field === 'main_image') {
        imagePreview.value = null;
        if (imageInputRef.value) {
            imageInputRef.value.value = '';
        }
    } else if (field === 'logo') {
        logoPreview.value = null;
        if (logoInputRef.value) {
            logoInputRef.value.value = '';
        }
    }
};

// Generate previews for all variants using the new unified endpoint
const generatePreviews = async () => {
    if (!hasData.value) {
        error.value = t('psd_editor.variant_preview.fill_at_least_one');
        return;
    }

    if (store.variants.length === 0) {
        error.value = t('psd_editor.variant_preview.no_variants');
        return;
    }

    generating.value = true;
    error.value = null;
    previews.value = [];

    try {
        // Use the new preview-all endpoint that renders via psd-parser (PIL)
        const response = await axios.post(
            `/api/v1/psd-files/${encodeURIComponent(store.currentFile)}/preview-all`,
            { data: formData.value }
        );

        previews.value = response.data.previews || [];

        if (previews.value.length === 0) {
            error.value = response.data.error || t('psd_editor.variant_preview.no_variants');
        } else if (previews.value.every(p => p.error)) {
            error.value = t('psd_editor.variant_preview.all_failed');
        }
    } catch (err) {
        error.value = err.response?.data?.error || t('psd_editor.variant_preview.all_failed');
    } finally {
        generating.value = false;
    }
};

// Download preview image
const downloadPreview = async (preview) => {
    if (!preview.preview_url) return;

    try {
        const response = await fetch(preview.preview_url);
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${preview.name}.png`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch (err) {
        console.error('Download failed:', err);
    }
};

// Clear form
const clearForm = () => {
    formData.value = {
        header: '',
        subtitle: '',
        paragraph: '',
        social_handle: '',
        primary_color: '#3B82F6',
        secondary_color: '#1F2937',
        main_image: '',
        logo: '',
    };
    imagePreview.value = null;
    logoPreview.value = null;
    previews.value = [];
    error.value = null;
};
</script>

<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="emit('close')">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-6xl mx-4 max-h-[90vh] flex flex-col">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">
                        {{ t('psd_editor.variant_preview.title') }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ store.currentFile }} - {{ store.variants.length }} {{ t('psd_editor.variant_preview.variants_count') }}
                    </p>
                </div>
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
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-900">
                            {{ t('psd_editor.variant_preview.your_content') }}
                        </h3>
                        <button
                            v-if="hasData"
                            @click="clearForm"
                            class="text-xs text-gray-500 hover:text-gray-700"
                        >
                            {{ t('psd_editor.variant_preview.clear') }}
                        </button>
                    </div>

                    <p class="text-xs text-gray-500 mb-4">
                        {{ t('psd_editor.variant_preview.hint') }}
                    </p>

                    <!-- Header input -->
                    <div class="mb-4">
                        <label class="block text-sm text-gray-700 mb-1">
                            {{ t('psd_editor.variant_preview.header_text') }}
                        </label>
                        <input
                            v-model="formData.header"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            :placeholder="t('psd_editor.variant_preview.header_placeholder')"
                        />
                    </div>

                    <!-- Subtitle input -->
                    <div class="mb-4">
                        <label class="block text-sm text-gray-700 mb-1">
                            {{ t('psd_editor.variant_preview.subtitle_text') }}
                        </label>
                        <input
                            v-model="formData.subtitle"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            :placeholder="t('psd_editor.variant_preview.subtitle_placeholder')"
                        />
                    </div>

                    <!-- Paragraph input -->
                    <div class="mb-4">
                        <label class="block text-sm text-gray-700 mb-1">
                            {{ t('psd_editor.variant_preview.paragraph_text') }}
                        </label>
                        <textarea
                            v-model="formData.paragraph"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                            :placeholder="t('psd_editor.variant_preview.paragraph_placeholder')"
                        />
                    </div>

                    <!-- Social handle input -->
                    <div class="mb-4">
                        <label class="block text-sm text-gray-700 mb-1">
                            {{ t('psd_editor.variant_preview.social_handle') }}
                        </label>
                        <input
                            v-model="formData.social_handle"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="@username"
                        />
                    </div>

                    <!-- Main image upload -->
                    <div class="mb-4">
                        <label class="block text-sm text-gray-700 mb-1">
                            {{ t('psd_editor.variant_preview.main_image') }}
                        </label>
                        <div v-if="imagePreview" class="relative mb-2">
                            <img
                                :src="imagePreview"
                                alt="Preview"
                                class="w-full h-24 object-cover rounded-lg border border-gray-300"
                            />
                            <button
                                @click="removeImage('main_image')"
                                class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full hover:bg-red-600"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <label
                            v-else
                            class="flex flex-col items-center justify-center w-full h-20 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors"
                        >
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="mt-1 text-xs text-gray-500">{{ t('psd_editor.variant_preview.upload_image') }}</span>
                            <input
                                ref="imageInputRef"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="(e) => handleImageUpload(e, 'main_image')"
                            />
                        </label>
                    </div>

                    <!-- Logo upload -->
                    <div class="mb-4">
                        <label class="block text-sm text-gray-700 mb-1">
                            {{ t('psd_editor.test_fields.logo') }}
                        </label>
                        <div v-if="logoPreview" class="relative mb-2">
                            <img
                                :src="logoPreview"
                                alt="Logo Preview"
                                class="w-full h-16 object-contain rounded-lg border border-gray-300 bg-gray-50"
                            />
                            <button
                                @click="removeImage('logo')"
                                class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full hover:bg-red-600"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <label
                            v-else
                            class="flex flex-col items-center justify-center w-full h-16 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors"
                        >
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-xs text-gray-500">{{ t('psd_editor.variant_preview.upload_image') }}</span>
                            <input
                                ref="logoInputRef"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="(e) => handleImageUpload(e, 'logo')"
                            />
                        </label>
                    </div>

                    <!-- Color pickers -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">
                                {{ t('psd_editor.variant_preview.primary_color') }}
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
                                {{ t('psd_editor.variant_preview.secondary_color') }}
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
                    <button
                        class="w-full px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
                        :disabled="generating || !hasData"
                        @click="generatePreviews"
                    >
                        <svg v-if="generating" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ generating ? t('psd_editor.variant_preview.generating') : t('psd_editor.variant_preview.generate') }}
                    </button>

                    <!-- Error message -->
                    <p v-if="error" class="mt-3 text-sm text-red-600">
                        {{ error }}
                    </p>
                </div>

                <!-- Right panel - Preview grid -->
                <div class="flex-1 p-6 overflow-y-auto bg-gray-50">
                    <!-- Loading state -->
                    <div v-if="generating" class="flex items-center justify-center h-full">
                        <div class="text-center">
                            <svg class="animate-spin h-12 w-12 mx-auto text-blue-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <p class="mt-4 text-gray-500">{{ t('psd_editor.variant_preview.generating_message') }}</p>
                            <p class="mt-1 text-sm text-gray-400">{{ t('psd_editor.variant_preview.generating_hint', { count: store.variants.length }) }}</p>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="previews.length === 0" class="flex items-center justify-center h-full">
                        <div class="text-center">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-gray-500">{{ t('psd_editor.variant_preview.empty_state') }}</p>
                            <p class="text-sm text-gray-400 mt-1">{{ t('psd_editor.variant_preview.empty_state_hint') }}</p>
                        </div>
                    </div>

                    <!-- Preview grid -->
                    <div v-else class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="preview in previews"
                            :key="preview.id"
                            class="group relative aspect-square rounded-lg overflow-hidden border-2 transition-all bg-white"
                            :class="[
                                preview.error
                                    ? 'border-red-200'
                                    : 'border-gray-200 hover:border-blue-300'
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

                            <!-- Download button (hover) -->
                            <button
                                v-if="preview.preview_url"
                                @click="downloadPreview(preview)"
                                class="absolute top-2 right-2 p-2 bg-white/90 hover:bg-white rounded-lg shadow opacity-0 group-hover:opacity-100 transition-opacity"
                                :title="t('psd_editor.variant_preview.download')"
                            >
                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                            </button>

                            <!-- Overlay with name -->
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-3">
                                <p class="text-white text-sm font-medium truncate">{{ preview.name }}</p>
                                <p v-if="preview.width && preview.height" class="text-white/70 text-xs">
                                    {{ preview.width }} × {{ preview.height }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50 rounded-b-xl">
                <p class="text-sm text-gray-600">
                    <span v-if="previews.length > 0">
                        {{ previews.filter(p => !p.error).length }} / {{ previews.length }} {{ t('psd_editor.variant_preview.successful_renders') }}
                    </span>
                </p>
                <button
                    @click="emit('close')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                >
                    {{ t('common.close') }}
                </button>
            </div>
        </div>
    </div>
</template>
