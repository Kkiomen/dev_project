<script setup>
import { computed, ref } from 'vue';
import { usePsdEditorStore } from '@/stores/psdEditor';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const store = usePsdEditorStore();

const emit = defineEmits(['import']);

const showImportModal = ref(false);
const addToLibrary = ref(false);

const testFields = [
    { key: 'header', label: 'psd_editor.test_fields.header', type: 'text' },
    { key: 'subtitle', label: 'psd_editor.test_fields.subtitle', type: 'text' },
    { key: 'paragraph', label: 'psd_editor.test_fields.paragraph', type: 'textarea' },
    { key: 'social_handle', label: 'psd_editor.test_fields.social_handle', type: 'text' },
    { key: 'main_image', label: 'psd_editor.test_fields.main_image', type: 'image' },
    { key: 'logo', label: 'psd_editor.test_fields.logo', type: 'image' },
    { key: 'primary_color', label: 'psd_editor.test_fields.primary_color', type: 'color' },
    { key: 'secondary_color', label: 'psd_editor.test_fields.secondary_color', type: 'color' },
];

// Handle image file upload and convert to base64
const handleImageUpload = (key, event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    // Validate file type
    if (!file.type.startsWith('image/')) {
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        const base64 = e.target.result;
        updateField(key, base64);
    };
    reader.readAsDataURL(file);
};

// Clear uploaded image
const clearImage = (key) => {
    updateField(key, '');
};

const selectedVariant = computed(() => {
    if (!store.selectedVariantPath) return null;
    return store.variants.find(v => v.path === store.selectedVariantPath);
});

const updateField = (key, value) => {
    store.updateTestData(key, value);
};

const generatePreview = async () => {
    try {
        await store.generatePreview();
    } catch (error) {
        console.error('Preview generation failed:', error);
    }
};

const openImportModal = () => {
    showImportModal.value = true;
};

const closeImportModal = () => {
    showImportModal.value = false;
};

const importVariants = async () => {
    try {
        await store.importVariants(addToLibrary.value);
        emit('import', store.importedTemplates);
        closeImportModal();
    } catch (error) {
        console.error('Import failed:', error);
    }
};
</script>

<template>
    <div class="h-full flex flex-col bg-white border-l border-gray-200">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">
                {{ t('psd_editor.preview.title') }}
            </h3>
            <button
                v-if="store.variants.length > 0"
                @click="openImportModal"
                :disabled="store.importing"
                class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 disabled:bg-green-400 rounded-md transition-colors flex items-center gap-1"
            >
                <svg v-if="store.importing" class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                {{ t('psd_editor.toolbar.import_templates') }}
            </button>
        </div>

        <!-- Variant list -->
        <div v-if="store.variants.length > 0" class="px-4 py-2 border-b border-gray-100">
            <div class="text-xs font-medium text-gray-500 mb-1">
                {{ t('psd_editor.import.description') }}
            </div>
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="variant in store.variants"
                    :key="variant.path"
                    @click="store.selectVariant(variant.path)"
                    :class="[
                        'px-2 py-1 text-xs rounded-full transition-colors',
                        store.selectedVariantPath === variant.path
                            ? 'bg-purple-100 text-purple-700 ring-1 ring-purple-500'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    ]"
                >
                    {{ variant.name }}
                </button>
            </div>
        </div>

        <!-- Preview area -->
        <div class="flex-1 overflow-y-auto p-4">
            <!-- No variant selected -->
            <div v-if="!selectedVariant" class="h-full flex items-center justify-center text-sm text-gray-500">
                {{ t('psd_editor.preview.no_variant') }}
            </div>

            <!-- Preview content -->
            <div v-else>
                <!-- Preview image -->
                <div class="mb-4 bg-gray-100 rounded-lg overflow-hidden">
                    <div v-if="store.previewLoading" class="aspect-square flex items-center justify-center">
                        <div class="text-center">
                            <svg class="animate-spin h-8 w-8 mx-auto mb-2 text-blue-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-xs text-gray-500">{{ t('psd_editor.preview.loading') }}</span>
                        </div>
                    </div>
                    <img
                        v-else-if="store.previewUrl"
                        :src="store.previewUrl"
                        :alt="selectedVariant.name"
                        class="w-full object-contain"
                    />
                    <div v-else class="aspect-square flex items-center justify-center">
                        <div class="text-center text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <button
                                @click="generatePreview"
                                class="text-xs text-blue-600 hover:text-blue-700"
                            >
                                {{ t('psd_editor.preview.generate') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Test data form -->
                <div class="space-y-3">
                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">
                        {{ t('psd_editor.preview.test_data') }}
                    </h4>

                    <div v-for="field in testFields" :key="field.key" class="space-y-1">
                        <label class="block text-xs font-medium text-gray-600">
                            {{ t(field.label) }}
                        </label>
                        <textarea
                            v-if="field.type === 'textarea'"
                            :value="store.testData[field.key]"
                            @input="(e) => updateField(field.key, e.target.value)"
                            rows="2"
                            class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        />
                        <input
                            v-else-if="field.type === 'color'"
                            type="color"
                            :value="store.testData[field.key]"
                            @input="(e) => updateField(field.key, e.target.value)"
                            class="w-full h-8 border border-gray-200 rounded-md cursor-pointer"
                        />
                        <!-- Image upload field -->
                        <div v-else-if="field.type === 'image'" class="space-y-1">
                            <!-- Preview of uploaded image -->
                            <div v-if="store.testData[field.key]" class="relative">
                                <img
                                    :src="store.testData[field.key]"
                                    class="w-full h-16 object-cover rounded-md border border-gray-200"
                                />
                                <button
                                    @click="clearImage(field.key)"
                                    class="absolute top-1 right-1 p-0.5 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors"
                                    type="button"
                                >
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <!-- File input -->
                            <label class="flex items-center justify-center w-full h-8 px-2 border border-dashed border-gray-300 rounded-md cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors">
                                <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-xs text-gray-500">{{ t('psd_editor.test_fields.upload_image') }}</span>
                                <input
                                    type="file"
                                    accept="image/*"
                                    @change="(e) => handleImageUpload(field.key, e)"
                                    class="hidden"
                                />
                            </label>
                        </div>
                        <input
                            v-else
                            type="text"
                            :value="store.testData[field.key]"
                            @input="(e) => updateField(field.key, e.target.value)"
                            class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <button
                        @click="generatePreview"
                        :disabled="store.previewLoading"
                        class="w-full px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 rounded-md transition-colors"
                    >
                        {{ t('psd_editor.preview.generate') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Import modal -->
        <Teleport to="body">
            <div
                v-if="showImportModal"
                class="fixed inset-0 z-50 flex items-center justify-center"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/50"
                    @click="closeImportModal"
                />

                <!-- Modal -->
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        {{ t('psd_editor.import.title') }}
                    </h3>

                    <p class="text-sm text-gray-600 mb-4">
                        {{ t('psd_editor.import.description') }}
                    </p>

                    <!-- Variant list -->
                    <div class="mb-4 max-h-40 overflow-y-auto">
                        <div
                            v-for="variant in store.variants"
                            :key="variant.path"
                            class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-md mb-1"
                        >
                            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                            </svg>
                            <span class="text-sm text-gray-900">{{ variant.name }}</span>
                        </div>
                    </div>

                    <!-- Add to library checkbox -->
                    <label class="flex items-center gap-2 mb-6">
                        <input
                            v-model="addToLibrary"
                            type="checkbox"
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                        />
                        <span class="text-sm text-gray-700">{{ t('psd_editor.import.add_to_library') }}</span>
                    </label>

                    <!-- Actions -->
                    <div class="flex justify-end gap-2">
                        <button
                            @click="closeImportModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
                        >
                            {{ t('psd_editor.import.cancel') }}
                        </button>
                        <button
                            @click="importVariants"
                            :disabled="store.importing"
                            class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:bg-green-400 rounded-md transition-colors flex items-center gap-2"
                        >
                            <svg v-if="store.importing" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ t('psd_editor.import.confirm', { count: store.variants.length }) }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
