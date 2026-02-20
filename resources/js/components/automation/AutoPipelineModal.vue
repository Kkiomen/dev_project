<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal from '@/components/common/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    isProcessing: { type: Boolean, default: false },
    currentStep: { type: String, default: null },
    progress: {
        type: Object,
        default: () => ({ totalPosts: 0, processedPosts: 0, currentStepIndex: 0, totalSteps: 0, errors: 0 }),
    },
});

const emit = defineEmits(['close', 'cancel']);

const { t } = useI18n();

const steps = computed(() => [
    { key: 'text', label: t('postAutomation.autoPipeline.stepText'), color: 'bg-blue-500', textColor: 'text-blue-600' },
    { key: 'imageDesc', label: t('postAutomation.autoPipeline.stepImageDesc'), color: 'bg-teal-500', textColor: 'text-teal-600' },
    { key: 'image', label: t('postAutomation.autoPipeline.stepImage'), color: 'bg-purple-500', textColor: 'text-purple-600' },
    { key: 'approve', label: t('postAutomation.autoPipeline.stepApprove'), color: 'bg-green-500', textColor: 'text-green-600' },
]);

const isComplete = computed(() => !props.isProcessing && props.progress.currentStepIndex > 0);

const progressPercent = computed(() => {
    if (props.progress.totalSteps === 0) return 0;
    return Math.round((props.progress.currentStepIndex / props.progress.totalSteps) * 100);
});

function getStepState(stepKey) {
    const stepOrder = ['text', 'imageDesc', 'image', 'approve'];
    const currentIdx = stepOrder.indexOf(props.currentStep);
    const thisIdx = stepOrder.indexOf(stepKey);

    if (isComplete.value) return 'completed';
    if (thisIdx < currentIdx) return 'completed';
    if (thisIdx === currentIdx) return 'active';
    return 'pending';
}

function handleClose() {
    if (props.isProcessing) {
        emit('cancel');
        return;
    }
    emit('close');
}
</script>

<template>
    <Modal :show="show" max-width="md" @close="handleClose">
        <div class="space-y-5">
            <!-- Header -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ t('postAutomation.autoPipeline.title') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('postAutomation.autoPipeline.description') }}
                </p>
            </div>

            <!-- Steps -->
            <div class="space-y-3">
                <div
                    v-for="(step, idx) in steps"
                    :key="step.key"
                    class="flex items-center gap-3"
                >
                    <!-- Step indicator -->
                    <div
                        class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 transition-all"
                        :class="[
                            getStepState(step.key) === 'completed' ? step.color :
                            getStepState(step.key) === 'active' ? `${step.color} animate-pulse` :
                            'bg-gray-200'
                        ]"
                    >
                        <svg
                            v-if="getStepState(step.key) === 'completed'"
                            class="w-4 h-4 text-white"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        <span
                            v-else
                            class="text-sm font-medium"
                            :class="getStepState(step.key) === 'active' ? 'text-white' : 'text-gray-500'"
                        >
                            {{ idx + 1 }}
                        </span>
                    </div>

                    <!-- Step label -->
                    <span
                        class="text-sm font-medium transition-colors"
                        :class="[
                            getStepState(step.key) === 'completed' ? 'text-gray-700' :
                            getStepState(step.key) === 'active' ? step.textColor :
                            'text-gray-400'
                        ]"
                    >
                        {{ step.label }}
                    </span>

                    <!-- Active spinner -->
                    <svg
                        v-if="getStepState(step.key) === 'active'"
                        class="w-4 h-4 animate-spin ml-auto"
                        :class="step.textColor"
                        fill="none" viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                </div>
            </div>

            <!-- Progress bar -->
            <div v-if="isProcessing" class="space-y-2">
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <span>{{ t('postAutomation.autoPipeline.progress', { current: progress.currentStepIndex, total: progress.totalSteps }) }}</span>
                    <span>{{ t('postAutomation.autoPipeline.processing', { count: progress.totalPosts }) }}</span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-indigo-500 rounded-full transition-all duration-500"
                        :style="{ width: progressPercent + '%' }"
                    />
                </div>
            </div>

            <!-- Complete state -->
            <div v-if="isComplete" class="rounded-lg bg-green-50 border border-green-200 p-4 text-center">
                <svg class="w-8 h-8 text-green-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm font-medium text-green-800">{{ t('postAutomation.autoPipeline.complete') }}</p>
                <p class="text-xs text-green-600 mt-1">{{ t('postAutomation.autoPipeline.completeDesc') }}</p>
                <p v-if="progress.errors > 0" class="text-xs text-red-600 mt-1">
                    {{ t('postAutomation.autoPipeline.errorCount', { count: progress.errors }) }}
                </p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-2">
                <button
                    v-if="isProcessing"
                    @click="emit('cancel')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    {{ t('postAutomation.autoPipeline.cancel') }}
                </button>
                <button
                    v-else
                    @click="emit('close')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    {{ t('common.close') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
