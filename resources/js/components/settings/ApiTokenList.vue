<script setup>
import { useI18n } from 'vue-i18n';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    tokens: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['revoke']);

const { t } = useI18n();

const formatDate = (date) => {
    if (!date) return t('settings.tokens.noExpiry');
    return new Date(date).toLocaleDateString();
};

const formatDateTime = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleString();
};

const isExpired = (token) => {
    if (!token.expires_at) return false;
    return new Date(token.expires_at) <= new Date();
};

const formatAbilities = (abilities) => {
    if (!abilities || abilities.length === 0) return '-';
    if (abilities.includes('*')) return t('settings.tokens.allAbilities');
    return abilities.join(', ');
};
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="token in tokens"
            :key="token.id"
            class="bg-white rounded-lg border border-gray-200 p-4"
            :class="{ 'opacity-60': isExpired(token) }"
        >
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-2">
                        <h3 class="font-medium text-gray-900">
                            {{ token.name }}
                        </h3>
                        <span
                            v-if="isExpired(token)"
                            class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800"
                        >
                            {{ t('settings.tokens.expired') }}
                        </span>
                        <span
                            v-else
                            class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800"
                        >
                            {{ t('settings.tokens.active') }}
                        </span>
                    </div>
                    <div class="mt-2 text-sm text-gray-500 space-y-1">
                        <p>
                            <span class="font-medium">{{ t('settings.tokens.abilities') }}:</span>
                            {{ formatAbilities(token.abilities) }}
                        </p>
                        <p>
                            <span class="font-medium">{{ t('settings.tokens.created') }}:</span>
                            {{ formatDateTime(token.created_at) }}
                        </p>
                        <p>
                            <span class="font-medium">{{ t('settings.tokens.expires') }}:</span>
                            {{ formatDate(token.expires_at) }}
                        </p>
                        <p>
                            <span class="font-medium">{{ t('settings.tokens.lastUsed') }}:</span>
                            {{ token.last_used_at ? formatDateTime(token.last_used_at) : t('settings.tokens.never') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <Button
                        variant="danger"
                        size="sm"
                        @click="emit('revoke', token)"
                    >
                        {{ t('settings.tokens.revoke') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
