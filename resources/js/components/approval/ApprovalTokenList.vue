<script setup>
import { useI18n } from 'vue-i18n';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    tokens: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['revoke', 'regenerate', 'copy']);

const { t } = useI18n();

const formatDate = (date) => {
    if (!date) return t('approval.noExpiry');
    return new Date(date).toLocaleDateString();
};
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="token in tokens"
            :key="token.id"
            class="bg-white rounded-lg border border-gray-200 p-4"
            :class="{ 'opacity-60': !token.is_active }"
        >
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-2">
                        <h3 class="font-medium text-gray-900">
                            {{ token.client_name }}
                        </h3>
                        <span
                            class="px-2 py-0.5 text-xs rounded-full"
                            :class="token.is_valid
                                ? 'bg-green-100 text-green-800'
                                : 'bg-gray-100 text-gray-600'"
                        >
                            {{ token.is_valid ? t('approval.active') : t('approval.inactive') }}
                        </span>
                    </div>
                    <p v-if="token.client_email" class="text-sm text-gray-500 mt-1">
                        {{ token.client_email }}
                    </p>
                    <div class="flex items-center space-x-4 mt-2 text-sm text-gray-500">
                        <span>
                            {{ t('approval.expires') }}: {{ formatDate(token.expires_at) }}
                        </span>
                        <span v-if="token.pending_count !== undefined">
                            {{ t('approval.pending') }}: {{ token.pending_count }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        @click="emit('copy', token.approval_url)"
                    >
                        {{ t('common.copyLink') }}
                    </Button>
                    <Button
                        v-if="token.is_active"
                        variant="secondary"
                        size="sm"
                        @click="emit('regenerate', token)"
                    >
                        {{ t('approval.regenerate') }}
                    </Button>
                    <Button
                        v-if="token.is_active"
                        variant="danger"
                        size="sm"
                        @click="emit('revoke', token)"
                    >
                        {{ t('approval.revoke') }}
                    </Button>
                </div>
            </div>

            <!-- Stats -->
            <div v-if="token.stats" class="mt-4 grid grid-cols-4 gap-4 pt-4 border-t border-gray-100">
                <div class="text-center">
                    <p class="text-2xl font-semibold text-gray-900">{{ token.stats.total }}</p>
                    <p class="text-xs text-gray-500">{{ t('approval.stats.total') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-semibold text-yellow-600">{{ token.stats.pending }}</p>
                    <p class="text-xs text-gray-500">{{ t('approval.stats.pending') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-semibold text-green-600">{{ token.stats.approved }}</p>
                    <p class="text-xs text-gray-500">{{ t('approval.stats.approved') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-semibold text-red-600">{{ token.stats.rejected }}</p>
                    <p class="text-xs text-gray-500">{{ t('approval.stats.rejected') }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
