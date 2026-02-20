<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    post: { type: Object, required: true },
    compact: { type: Boolean, default: false },
    generatingText: { type: Boolean, default: false },
    generatingImageDescription: { type: Boolean, default: false },
    generatingImage: { type: Boolean, default: false },
    publishing: { type: Boolean, default: false },
});

const { t } = useI18n();

const steps = computed(() => [
    {
        key: 'text',
        label: t('postAutomation.pipeline.text'),
        completed: !!props.post.main_caption,
        inProgress: props.generatingText,
        color: 'bg-blue-500',
        colorLight: 'bg-blue-100',
    },
    {
        key: 'imageDesc',
        label: t('postAutomation.pipeline.imageDesc'),
        completed: !!props.post.image_prompt,
        inProgress: props.generatingImageDescription,
        color: 'bg-teal-500',
        colorLight: 'bg-teal-100',
    },
    {
        key: 'image',
        label: t('postAutomation.pipeline.image'),
        completed: (props.post.media_count > 0) || !!props.post.first_media_url,
        inProgress: props.generatingImage,
        color: 'bg-purple-500',
        colorLight: 'bg-purple-100',
    },
    {
        key: 'approved',
        label: t('postAutomation.pipeline.approved'),
        completed: ['approved', 'scheduled', 'published'].includes(props.post.status),
        inProgress: false,
        color: 'bg-green-500',
        colorLight: 'bg-green-100',
    },
    {
        key: 'published',
        label: t('postAutomation.pipeline.published'),
        completed: props.post.status === 'published',
        inProgress: props.publishing,
        color: 'bg-orange-500',
        colorLight: 'bg-orange-100',
    },
]);
</script>

<template>
    <!-- Compact: small dots with connecting lines -->
    <div v-if="compact" class="flex items-center gap-0.5">
        <template v-for="(step, idx) in steps" :key="step.key">
            <div
                class="w-2 h-2 rounded-full shrink-0 transition-all"
                :class="[
                    step.inProgress ? `${step.color} animate-pulse` :
                    step.completed ? step.color :
                    'bg-gray-200'
                ]"
                :title="step.label"
            />
            <div
                v-if="idx < steps.length - 1"
                class="h-px w-1.5 shrink-0"
                :class="step.completed ? 'bg-gray-400' : 'bg-gray-200'"
            />
        </template>
    </div>

    <!-- Full: larger dots with labels -->
    <div v-else class="flex items-center gap-1">
        <template v-for="(step, idx) in steps" :key="step.key">
            <div class="flex items-center gap-1">
                <div
                    class="w-4 h-4 rounded-full shrink-0 flex items-center justify-center transition-all"
                    :class="[
                        step.inProgress ? `${step.color} animate-pulse` :
                        step.completed ? step.color :
                        'bg-gray-200'
                    ]"
                >
                    <svg
                        v-if="step.completed && !step.inProgress"
                        class="w-2.5 h-2.5 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <span
                    class="text-xs whitespace-nowrap"
                    :class="step.completed ? 'text-gray-700 font-medium' : 'text-gray-400'"
                >
                    {{ step.label }}
                </span>
            </div>
            <div
                v-if="idx < steps.length - 1"
                class="h-px w-3 shrink-0"
                :class="step.completed ? 'bg-gray-400' : 'bg-gray-200'"
            />
        </template>
    </div>
</template>
