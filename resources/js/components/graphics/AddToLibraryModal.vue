<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    templateId: {
        type: String,
        required: true,
    },
    canvasRef: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'added']);

const { t } = useI18n();

const loading = ref(false);

const addToLibrary = async () => {
    loading.value = true;
    try {
        // Generate thumbnail from canvas
        let thumbnailBase64 = null;
        if (props.canvasRef?.exportImage) {
            thumbnailBase64 = props.canvasRef.exportImage({
                pixelRatio: 0.5, // Smaller for thumbnail
                format: 'image/jpeg',
                quality: 0.8,
            });
        }

        const response = await axios.post(`/api/v1/templates/${props.templateId}/add-to-library`, {
            thumbnail: thumbnailBase64,
        });
        emit('added', response.data.data);
        emit('close');
    } catch (error) {
        console.error('Failed to add to library:', error);
        alert(error.response?.data?.message || 'Failed to add to library');
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 overflow-y-auto"
            @click.self="$emit('close')"
        >
            <div class="flex min-h-full items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50" @click="$emit('close')"></div>

                <!-- Modal -->
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ t('graphics.library.addToLibrary') }}
                        </h2>
                        <button
                            @click="$emit('close')"
                            class="text-gray-400 hover:text-gray-600"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="p-6">
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-gray-900 font-medium">{{ t('graphics.library.confirmAdd') }}</p>
                                <p class="text-sm text-gray-500 mt-1">{{ t('graphics.library.confirmAddDescription') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                        <Button variant="secondary" @click="$emit('close')">
                            {{ t('common.cancel') }}
                        </Button>
                        <Button :loading="loading" @click="addToLibrary">
                            {{ t('graphics.library.addToLibrary') }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
