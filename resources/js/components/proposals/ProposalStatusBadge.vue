<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    status: { type: String, required: true },
});

const { t } = useI18n();

const config = computed(() => {
    const configs = {
        pending: { bg: 'bg-yellow-100', text: 'text-yellow-800' },
        used: { bg: 'bg-green-100', text: 'text-green-800' },
    };
    return configs[props.status] || configs.pending;
});

const label = computed(() => {
    return t(`postAutomation.proposals.status.${props.status}`);
});
</script>

<template>
    <span
        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
        :class="[config.bg, config.text]"
    >
        {{ label }}
    </span>
</template>
