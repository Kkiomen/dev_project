<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    hasProposals: { type: Boolean, default: false },
    pipelineSummary: {
        type: Object,
        default: () => ({ needText: 0, needImageDesc: 0, needImage: 0, needApproval: 0, complete: 0 }),
    },
});

const emit = defineEmits(['go-to-proposals', 'process-all']);

const { t } = useI18n();

const hasPosts = computed(() => {
    const s = props.pipelineSummary;
    return (s.needText + s.needImageDesc + s.needImage + s.needApproval + s.complete) > 0;
});

const steps = computed(() => [
    {
        number: 1,
        title: t('postAutomation.emptyState.step1Title'),
        description: t('postAutomation.emptyState.step1Desc'),
        action: t('postAutomation.emptyState.step1Action'),
        completed: props.hasProposals,
        emit: 'go-to-proposals',
    },
    {
        number: 2,
        title: t('postAutomation.emptyState.step2Title'),
        description: t('postAutomation.emptyState.step2Desc'),
        action: t('postAutomation.emptyState.step2Action'),
        completed: hasPosts.value,
        emit: 'go-to-proposals',
    },
    {
        number: 3,
        title: t('postAutomation.emptyState.step3Title'),
        description: t('postAutomation.emptyState.step3Desc'),
        action: t('postAutomation.emptyState.step3Action'),
        completed: false,
        emit: 'process-all',
    },
]);

function handleAction(step) {
    emit(step.emit);
}
</script>

<template>
    <div class="flex flex-col items-center justify-center py-16 px-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">
            {{ t('postAutomation.emptyState.title') }}
        </h3>
        <p class="text-sm text-gray-500 text-center max-w-md mb-10">
            {{ t('postAutomation.emptyState.description') }}
        </p>

        <!-- Steps wizard -->
        <div class="w-full max-w-lg space-y-0">
            <div
                v-for="(step, idx) in steps"
                :key="step.number"
                class="relative flex gap-4"
            >
                <!-- Vertical line -->
                <div class="flex flex-col items-center">
                    <!-- Step circle -->
                    <div
                        class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 border-2 transition-colors"
                        :class="step.completed
                            ? 'bg-green-500 border-green-500'
                            : 'bg-white border-gray-300'"
                    >
                        <svg
                            v-if="step.completed"
                            class="w-5 h-5 text-white"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else class="text-sm font-bold text-gray-500">
                            {{ step.number }}
                        </span>
                    </div>
                    <!-- Connecting line -->
                    <div
                        v-if="idx < steps.length - 1"
                        class="w-0.5 flex-1 min-h-[2rem]"
                        :class="step.completed ? 'bg-green-300' : 'bg-gray-200'"
                    />
                </div>

                <!-- Step content -->
                <div class="pb-8 pt-1 flex-1">
                    <div class="flex items-center gap-2">
                        <h4 class="text-sm font-semibold text-gray-900">
                            {{ step.title }}
                        </h4>
                        <span
                            v-if="step.completed"
                            class="text-xs font-medium text-green-600 bg-green-50 px-2 py-0.5 rounded-full"
                        >
                            {{ t('postAutomation.emptyState.stepComplete') }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ step.description }}
                    </p>
                    <button
                        v-if="!step.completed"
                        @click="handleAction(step)"
                        class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                        :class="idx === 0 || steps[idx - 1]?.completed
                            ? 'text-white bg-indigo-600 hover:bg-indigo-500'
                            : 'text-gray-400 bg-gray-100 cursor-not-allowed'"
                        :disabled="idx > 0 && !steps[idx - 1]?.completed"
                    >
                        {{ step.action }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
