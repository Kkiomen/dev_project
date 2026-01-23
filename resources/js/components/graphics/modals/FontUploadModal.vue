<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useGraphicsStore } from '@/stores/graphics';
import Button from '@/components/common/Button.vue';

const { t } = useI18n();

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    templateId: {
        type: [String, Number],
        required: true,
    },
});

const emit = defineEmits(['close', 'font-added']);

const graphicsStore = useGraphicsStore();

const fonts = ref([]);
const loading = ref(false);
const uploading = ref(false);
const uploadError = ref(null);
const dragOver = ref(false);

const fileInputRef = ref(null);

const loadFonts = async () => {
    loading.value = true;
    try {
        const response = await fetch(`/api/v1/templates/${props.templateId}/fonts`, {
            headers: {
                'Accept': 'application/json',
            },
        });
        if (response.ok) {
            const data = await response.json();
            fonts.value = data.data || [];
        }
    } catch (error) {
        console.error('Failed to load fonts:', error);
    } finally {
        loading.value = false;
    }
};

const handleFileSelect = async (e) => {
    const files = e.target.files || e.dataTransfer?.files;
    if (!files?.length) return;

    uploadError.value = null;
    uploading.value = true;

    try {
        for (const file of files) {
            if (!file.name.match(/\.(ttf|otf|woff|woff2)$/i)) {
                uploadError.value = t('graphics.fonts.invalidFormat');
                continue;
            }

            const formData = new FormData();
            formData.append('font', file);
            formData.append('template_id', props.templateId);

            const response = await fetch('/api/v1/fonts', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Upload failed');
            }

            const data = await response.json();
            fonts.value.push(data.data);

            // Load font into document for immediate use
            loadFontIntoDocument(data.data);

            emit('font-added', data.data);
        }
    } catch (error) {
        console.error('Font upload failed:', error);
        uploadError.value = error.message || t('graphics.fonts.uploadFailed');
    } finally {
        uploading.value = false;
        if (fileInputRef.value) {
            fileInputRef.value.value = '';
        }
    }
};

const loadFontIntoDocument = (font) => {
    const fontFace = new FontFace(
        font.font_family,
        `url(/storage/${font.font_file})`,
        {
            weight: font.font_weight,
            style: font.font_style,
        }
    );

    fontFace.load().then((loadedFace) => {
        document.fonts.add(loadedFace);
    }).catch((error) => {
        console.error('Failed to load font:', error);
    });
};

const handleDelete = async (font) => {
    if (!confirm(t('graphics.fonts.confirmDelete'))) return;

    try {
        const response = await fetch(`/api/v1/fonts/${font.id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
            },
        });

        if (response.ok) {
            fonts.value = fonts.value.filter(f => f.id !== font.id);
        }
    } catch (error) {
        console.error('Failed to delete font:', error);
    }
};

const handleDragOver = (e) => {
    e.preventDefault();
    dragOver.value = true;
};

const handleDragLeave = () => {
    dragOver.value = false;
};

const handleDrop = (e) => {
    e.preventDefault();
    dragOver.value = false;
    handleFileSelect(e);
};

const triggerFileInput = () => {
    fileInputRef.value?.click();
};

onMounted(() => {
    if (props.show) {
        loadFonts();
    }
});

// Watch for show changes
import { watch } from 'vue';
watch(() => props.show, (newVal) => {
    if (newVal) {
        loadFonts();
    }
});
</script>

<template>
    <teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="$emit('close')"
        >
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ t('graphics.fonts.title') }}
                    </h2>
                </div>

                <!-- Content -->
                <div class="p-6">
                    <!-- Upload area -->
                    <div
                        :class="[
                            'border-2 border-dashed rounded-lg p-6 text-center transition-colors cursor-pointer',
                            dragOver ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-gray-400'
                        ]"
                        @dragover="handleDragOver"
                        @dragleave="handleDragLeave"
                        @drop="handleDrop"
                        @click="triggerFileInput"
                    >
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept=".ttf,.otf,.woff,.woff2"
                            multiple
                            class="hidden"
                            @change="handleFileSelect"
                        />

                        <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>

                        <p class="mt-2 text-sm text-gray-600">
                            {{ t('graphics.fonts.dropzone') }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ t('graphics.fonts.supportedFormats') }}
                        </p>

                        <div v-if="uploading" class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full animate-pulse w-full"></div>
                            </div>
                            <p class="mt-1 text-sm text-gray-600">{{ t('graphics.fonts.uploading') }}</p>
                        </div>
                    </div>

                    <!-- Error message -->
                    <div v-if="uploadError" class="mt-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm">
                        {{ uploadError }}
                    </div>

                    <!-- Font list -->
                    <div class="mt-6">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">
                            {{ t('graphics.fonts.installed') }}
                        </h3>

                        <div v-if="loading" class="text-center py-4">
                            <div class="animate-spin w-6 h-6 border-2 border-blue-600 border-t-transparent rounded-full mx-auto"></div>
                        </div>

                        <div v-else-if="fonts.length === 0" class="text-center py-4 text-gray-500 text-sm">
                            {{ t('graphics.fonts.noFonts') }}
                        </div>

                        <div v-else class="space-y-2 max-h-48 overflow-y-auto">
                            <div
                                v-for="font in fonts"
                                :key="font.id"
                                class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                            >
                                <div>
                                    <p class="font-medium text-gray-900" :style="{ fontFamily: font.font_family }">
                                        {{ font.font_family }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ font.font_weight }} {{ font.font_style }}
                                    </p>
                                </div>
                                <button
                                    @click="handleDelete(font)"
                                    class="p-1 text-gray-400 hover:text-red-500 transition-colors"
                                    :title="t('common.delete')"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex justify-end">
                    <Button variant="secondary" @click="$emit('close')">
                        {{ t('common.close') }}
                    </Button>
                </div>
            </div>
        </div>
    </teleport>
</template>
