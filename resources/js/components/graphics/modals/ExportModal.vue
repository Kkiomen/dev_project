<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/common/Button.vue';

const { t } = useI18n();

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    stageRef: {
        type: Object,
        default: null,
    },
    template: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close']);

const format = ref('png');
const quality = ref(100);
const scale = ref(2);
const filename = ref('');
const exporting = ref(false);
const uploading = ref(false);

const formats = [
    { value: 'png', label: 'PNG', mimeType: 'image/png' },
    { value: 'jpeg', label: 'JPEG', mimeType: 'image/jpeg' },
    { value: 'webp', label: 'WebP', mimeType: 'image/webp' },
];

const scales = [
    { value: 1, label: '1x' },
    { value: 2, label: '2x' },
    { value: 3, label: '3x' },
    { value: 4, label: '4x' },
];

const defaultFilename = computed(() => {
    return props.template?.name?.replace(/[^a-z0-9]/gi, '_').toLowerCase() || 'graphic';
});

const finalFilename = computed(() => {
    const name = filename.value || defaultFilename.value;
    return `${name}.${format.value}`;
});

const selectedFormat = computed(() => {
    return formats.find(f => f.value === format.value);
});

// Get export options
const getExportOptions = () => ({
    pixelRatio: scale.value,
    format: selectedFormat.value.mimeType,
    quality: format.value === 'png' ? 1 : quality.value / 100,
    filename: finalFilename.value,
});

const handleDownload = async () => {
    exporting.value = true;

    try {
        // Use the downloadImage function from EditorCanvas
        if (props.stageRef?.downloadImage) {
            props.stageRef.downloadImage(getExportOptions());
        } else {
            throw new Error('Export function not available');
        }

        emit('close');
    } catch (error) {
        console.error('Export failed:', error);
    } finally {
        exporting.value = false;
    }
};

const handleUpload = async () => {
    uploading.value = true;

    try {
        // Use the exportToBlob function from EditorCanvas
        if (!props.stageRef?.exportToBlob) {
            throw new Error('Export function not available');
        }

        const blob = await props.stageRef.exportToBlob(getExportOptions());

        if (!blob) {
            throw new Error('Failed to generate blob');
        }

        const formData = new FormData();
        formData.append('image', blob, finalFilename.value);

        const response = await fetch(`/api/v1/templates/${props.template.id}/images`, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Upload failed');
        }

        emit('close');
    } catch (error) {
        console.error('Upload failed:', error);
    } finally {
        uploading.value = false;
    }
};
</script>

<template>
    <teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="$emit('close')"
        >
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ t('graphics.export.title') }}
                    </h2>
                </div>

                <!-- Content -->
                <div class="p-6 space-y-4">
                    <!-- Format -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ t('graphics.export.format') }}
                        </label>
                        <div class="flex rounded-lg overflow-hidden border border-gray-300">
                            <button
                                v-for="f in formats"
                                :key="f.value"
                                @click="format = f.value"
                                :class="[
                                    'flex-1 py-2 text-sm font-medium transition-colors',
                                    format === f.value
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-white text-gray-700 hover:bg-gray-50'
                                ]"
                            >
                                {{ f.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Quality (for JPEG/WebP) -->
                    <div v-if="format !== 'png'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ t('graphics.export.quality') }}: {{ quality }}%
                        </label>
                        <input
                            v-model="quality"
                            type="range"
                            min="10"
                            max="100"
                            step="5"
                            class="w-full"
                        />
                    </div>

                    <!-- Scale -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ t('graphics.export.scale') }}
                        </label>
                        <div class="flex rounded-lg overflow-hidden border border-gray-300">
                            <button
                                v-for="s in scales"
                                :key="s.value"
                                @click="scale = s.value"
                                :class="[
                                    'flex-1 py-2 text-sm font-medium transition-colors',
                                    scale === s.value
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-white text-gray-700 hover:bg-gray-50'
                                ]"
                            >
                                {{ s.label }}
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ template.width * scale }} x {{ template.height * scale }}px
                        </p>
                    </div>

                    <!-- Filename -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ t('graphics.export.filename') }}
                        </label>
                        <div class="flex items-center">
                            <input
                                v-model="filename"
                                type="text"
                                :placeholder="defaultFilename"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                            <span class="px-3 py-2 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-gray-500">
                                .{{ format }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex justify-end space-x-3">
                    <Button variant="secondary" @click="$emit('close')">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        variant="secondary"
                        :loading="uploading"
                        :disabled="exporting"
                        @click="handleUpload"
                    >
                        {{ t('graphics.export.uploadButton') }}
                    </Button>
                    <Button
                        :loading="exporting"
                        :disabled="uploading"
                        @click="handleDownload"
                    >
                        {{ t('graphics.export.downloadButton') }}
                    </Button>
                </div>
            </div>
        </div>
    </teleport>
</template>
