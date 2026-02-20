<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Dropdown from '@/components/common/Dropdown.vue';
import { getPostPipelineStep } from '@/composables/useAutoPipeline';

const props = defineProps({
    post: { type: Object, required: true },
    generatingText: { type: Boolean, default: false },
    generatingImageDescription: { type: Boolean, default: false },
    generatingImage: { type: Boolean, default: false },
    publishing: { type: Boolean, default: false },
});

const emit = defineEmits([
    'generate-text',
    'generate-image-description',
    'generate-image',
    'approve',
    'publish',
    'preview',
    'edit',
    'process-next',
]);

const { t } = useI18n();

const canApprove = ['draft', 'pending_approval'].includes(props.post.status);
const canPublish = ['approved', 'scheduled'].includes(props.post.status);

const nextStep = computed(() => getPostPipelineStep(props.post));
const nextStepLabel = computed(() => {
    const labels = {
        text: t('postAutomation.actions.processNextText'),
        imageDesc: t('postAutomation.actions.processNextImageDesc'),
        image: t('postAutomation.actions.processNextImage'),
        approve: t('postAutomation.actions.processNextApprove'),
    };
    return labels[nextStep.value] || t('postAutomation.actions.fullyProcessed');
});
</script>

<template>
    <Dropdown align="right" width="48">
        <template #trigger>
            <button class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01" />
                </svg>
            </button>
        </template>
        <template #content>
            <button
                v-if="nextStep"
                @click="emit('process-next')"
                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-indigo-700 bg-indigo-50 hover:bg-indigo-100 font-medium"
            >
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                {{ nextStepLabel }}
            </button>
            <div v-if="nextStep" class="border-t border-gray-100 my-1" />
            <button
                @click="emit('generate-text')"
                :disabled="generatingText"
                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            >
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                {{ generatingText ? t('postAutomation.actions.generatingText') : t('postAutomation.actions.generateText') }}
            </button>
            <button
                @click="emit('generate-image-description')"
                :disabled="generatingImageDescription"
                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            >
                <svg class="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {{ generatingImageDescription ? t('postAutomation.actions.generatingImageDescription') : t('postAutomation.actions.generateImageDescription') }}
            </button>
            <button
                @click="emit('generate-image')"
                :disabled="generatingImage"
                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            >
                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                {{ generatingImage ? t('postAutomation.actions.generatingImage') : t('postAutomation.actions.generateImage') }}
            </button>
            <div class="border-t border-gray-100 my-1" />
            <button
                v-if="canApprove"
                @click="emit('approve')"
                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            >
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ t('postAutomation.actions.approve') }}
            </button>
            <button
                v-if="canPublish"
                @click="emit('publish')"
                :disabled="publishing"
                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            >
                <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                </svg>
                {{ publishing ? t('postAutomation.actions.publishing') : t('postAutomation.actions.publish') }}
            </button>
            <div class="border-t border-gray-100 my-1" />
            <button
                @click="emit('preview')"
                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            >
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                {{ t('postAutomation.actions.preview') }}
            </button>
            <button
                @click="emit('edit')"
                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            >
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                {{ t('postAutomation.actions.edit') }}
            </button>
        </template>
    </Dropdown>
</template>
