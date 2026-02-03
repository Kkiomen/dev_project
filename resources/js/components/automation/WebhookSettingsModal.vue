<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import Modal from '@/components/common/Modal.vue';
import { useToast } from '@/composables/useToast';
import { useBrandsStore } from '@/stores/brands';

const { t } = useI18n();
const toast = useToast();
const brandsStore = useBrandsStore();

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const saving = ref(false);
const form = ref({
    text_generation_url: '',
    text_generation_prompt: '',
    image_generation_url: '',
    image_generation_prompt: '',
    publish_url: '',
    on_approve_url: '',
});

watch(() => props.show, (value) => {
    if (value) {
        loadSettings();
    }
});

function loadSettings() {
    const brand = brandsStore.currentBrand;
    if (!brand) return;

    const webhooks = brand.automation_settings?.webhooks || {};
    form.value = {
        text_generation_url: webhooks.text_generation_url || '',
        text_generation_prompt: webhooks.text_generation_prompt || '',
        image_generation_url: webhooks.image_generation_url || '',
        image_generation_prompt: webhooks.image_generation_prompt || '',
        publish_url: webhooks.publish_url || '',
        on_approve_url: webhooks.on_approve_url || '',
    };
}

async function save() {
    const brand = brandsStore.currentBrand;
    if (!brand) return;

    saving.value = true;
    try {
        await axios.put(`/api/v1/brands/${brand.id}/automation/settings`, {
            webhooks: { ...form.value },
        });
        toast.success(t('postAutomation.webhookSettings.saved'));
        emit('close');
    } catch (error) {
        toast.error(error.response?.data?.message || 'Failed to save settings');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Modal :show="show" max-width="xl" @close="emit('close')">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ t('postAutomation.webhookSettings.title') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('postAutomation.webhookSettings.description') }}
                </p>
            </div>

            <form @submit.prevent="save" class="space-y-5">
                <!-- Text Generation -->
                <div class="space-y-3">
                    <label class="block text-sm font-medium text-gray-700">
                        {{ t('postAutomation.webhookSettings.textGenerationUrl') }}
                    </label>
                    <input
                        v-model="form.text_generation_url"
                        type="url"
                        :placeholder="t('postAutomation.webhookSettings.urlPlaceholder')"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    />
                    <label class="block text-sm font-medium text-gray-700">
                        {{ t('postAutomation.webhookSettings.textGenerationPrompt') }}
                    </label>
                    <textarea
                        v-model="form.text_generation_prompt"
                        :placeholder="t('postAutomation.webhookSettings.promptPlaceholder')"
                        rows="2"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    />
                </div>

                <!-- Image Generation -->
                <div class="space-y-3">
                    <label class="block text-sm font-medium text-gray-700">
                        {{ t('postAutomation.webhookSettings.imageGenerationUrl') }}
                    </label>
                    <input
                        v-model="form.image_generation_url"
                        type="url"
                        :placeholder="t('postAutomation.webhookSettings.urlPlaceholder')"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    />
                    <label class="block text-sm font-medium text-gray-700">
                        {{ t('postAutomation.webhookSettings.imageGenerationPrompt') }}
                    </label>
                    <textarea
                        v-model="form.image_generation_prompt"
                        :placeholder="t('postAutomation.webhookSettings.promptPlaceholder')"
                        rows="2"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    />
                </div>

                <!-- Publish -->
                <div class="space-y-3">
                    <label class="block text-sm font-medium text-gray-700">
                        {{ t('postAutomation.webhookSettings.publishUrl') }}
                    </label>
                    <input
                        v-model="form.publish_url"
                        type="url"
                        :placeholder="t('postAutomation.webhookSettings.urlPlaceholder')"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    />
                </div>

                <!-- On Approve -->
                <div class="space-y-3">
                    <label class="block text-sm font-medium text-gray-700">
                        {{ t('postAutomation.webhookSettings.onApproveUrl') }}
                    </label>
                    <input
                        v-model="form.on_approve_url"
                        type="url"
                        :placeholder="t('postAutomation.webhookSettings.urlPlaceholder')"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    />
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ saving ? t('common.loading') : t('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
