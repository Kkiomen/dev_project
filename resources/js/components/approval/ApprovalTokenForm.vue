<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/common/Button.vue';

const emit = defineEmits(['submit', 'cancel']);

const { t } = useI18n();

const form = ref({
    client_name: '',
    client_email: '',
    expires_at: '',
});

const submitting = ref(false);

const handleSubmit = async () => {
    if (!form.value.client_name.trim()) return;

    submitting.value = true;
    try {
        emit('submit', {
            client_name: form.value.client_name.trim(),
            client_email: form.value.client_email.trim() || null,
            expires_at: form.value.expires_at || null,
        });
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <form @submit.prevent="handleSubmit">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                {{ t('approval.createToken') }}
            </h2>

            <div class="space-y-4">
                <!-- Client Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('approval.clientName') }} *
                    </label>
                    <input
                        v-model="form.client_name"
                        type="text"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        :placeholder="t('approval.clientNamePlaceholder')"
                    />
                </div>

                <!-- Client Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('approval.clientEmail') }}
                    </label>
                    <input
                        v-model="form.client_email"
                        type="email"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        :placeholder="t('approval.clientEmailPlaceholder')"
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        {{ t('approval.clientEmailHint') }}
                    </p>
                </div>

                <!-- Expiry Date -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('approval.expiresAt') }}
                    </label>
                    <input
                        v-model="form.expires_at"
                        type="date"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        {{ t('approval.expiresAtHint') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3 rounded-b-lg">
            <Button type="button" variant="secondary" @click="emit('cancel')">
                {{ t('common.cancel') }}
            </Button>
            <Button
                type="submit"
                :loading="submitting"
                :disabled="!form.client_name.trim()"
            >
                {{ t('common.create') }}
            </Button>
        </div>
    </form>
</template>
