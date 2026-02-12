<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/composables/useToast';
import { useBrandsStore } from '@/stores/brands';
import Button from '@/components/common/Button.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import axios from 'axios';

const props = defineProps({
    brandId: {
        type: [String, Number],
        required: true,
    },
});

const { t } = useI18n();
const toast = useToast();
const brandsStore = useBrandsStore();

const loading = ref(true);
const providers = ref([]);
const keyInputs = ref({});
const savingProvider = ref(null);
const deletingProvider = ref(null);
const publishingProvider = ref(null);
const savingPublishingProvider = ref(false);

const providerIcons = {
    openai: `<svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M22.282 9.821a5.985 5.985 0 0 0-.516-4.91 6.046 6.046 0 0 0-6.51-2.9A6.065 6.065 0 0 0 4.981 4.18a5.985 5.985 0 0 0-3.998 2.9 6.046 6.046 0 0 0 .743 7.097 5.98 5.98 0 0 0 .51 4.911 6.051 6.051 0 0 0 6.515 2.9A5.985 5.985 0 0 0 13.26 24a6.056 6.056 0 0 0 5.772-4.206 5.99 5.99 0 0 0 3.997-2.9 6.056 6.056 0 0 0-.747-7.073zM13.26 22.43a4.476 4.476 0 0 1-2.876-1.04l.141-.081 4.779-2.758a.795.795 0 0 0 .392-.681v-6.737l2.02 1.168a.071.071 0 0 1 .038.052v5.583a4.504 4.504 0 0 1-4.494 4.494zM3.6 18.304a4.47 4.47 0 0 1-.535-3.014l.142.085 4.783 2.759a.771.771 0 0 0 .78 0l5.843-3.369v2.332a.08.08 0 0 1-.033.062L9.74 19.95a4.5 4.5 0 0 1-6.14-1.646zM2.34 7.896a4.485 4.485 0 0 1 2.366-1.973V11.6a.766.766 0 0 0 .388.676l5.815 3.355-2.02 1.168a.076.076 0 0 1-.071 0l-4.83-2.786A4.504 4.504 0 0 1 2.34 7.872zm16.597 3.855l-5.833-3.387L15.119 7.2a.076.076 0 0 1 .071 0l4.83 2.791a4.494 4.494 0 0 1-.676 8.105v-5.678a.79.79 0 0 0-.407-.667zm2.01-3.023l-.141-.085-4.774-2.782a.776.776 0 0 0-.785 0L9.409 9.23V6.897a.066.066 0 0 1 .028-.061l4.83-2.787a4.5 4.5 0 0 1 6.68 4.66zm-12.64 4.135l-2.02-1.164a.08.08 0 0 1-.038-.057V6.075a4.5 4.5 0 0 1 7.375-3.453l-.142.08L8.704 5.46a.795.795 0 0 0-.393.681zm1.097-2.365l2.602-1.5 2.607 1.5v2.999l-2.597 1.5-2.607-1.5z"/></svg>`,
    gemini: `<svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm0 2.4c2.293 0 4.381.823 6.018 2.182C16.418 6.505 14.33 7.8 12 7.8S7.582 6.505 5.982 4.582A9.55 9.55 0 0 1 12 2.4zm-9.6 9.6c0-2.293.823-4.381 2.182-6.018C6.505 7.582 7.8 9.67 7.8 12s-1.295 4.418-3.218 6.018A9.55 9.55 0 0 1 2.4 12zm9.6 9.6c-2.293 0-4.381-.823-6.018-2.182C7.582 17.495 9.67 16.2 12 16.2s4.418 1.295 6.018 3.218A9.55 9.55 0 0 1 12 21.6zm9.6-9.6c0 2.293-.823 4.381-2.182 6.018C17.495 16.418 16.2 14.33 16.2 12s1.295-4.418 3.218-6.018A9.55 9.55 0 0 1 21.6 12z"/></svg>`,
    wavespeed: `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>`,
    getlate: `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>`,
};

const providerColors = {
    openai: { bg: 'bg-gray-900', text: 'text-white' },
    gemini: { bg: 'bg-blue-100', text: 'text-blue-600' },
    wavespeed: { bg: 'bg-purple-100', text: 'text-purple-600' },
    getlate: { bg: 'bg-emerald-100', text: 'text-emerald-600' },
};

const publishingProviderOptions = [
    { value: '', labelKey: 'publishingProvider.auto' },
    { value: 'direct', labelKey: 'publishingProvider.direct' },
    { value: 'webhook', labelKey: 'publishingProvider.webhook' },
    { value: 'getlate', labelKey: 'publishingProvider.getlate' },
];

const hasGetLateKey = computed(() => {
    return providers.value.some(p => p.provider === 'getlate' && p.has_key);
});

const filteredPublishingOptions = computed(() => {
    return publishingProviderOptions.filter(opt => {
        if (opt.value === 'getlate') {
            return hasGetLateKey.value;
        }
        return true;
    });
});

const loadKeys = async () => {
    loading.value = true;
    try {
        const response = await axios.get(`/api/v1/brands/${props.brandId}/ai-keys`);
        providers.value = response.data;
        providers.value.forEach(p => {
            if (!keyInputs.value[p.provider]) {
                keyInputs.value[p.provider] = '';
            }
        });
    } catch (error) {
        toast.error(t('aiKeys.errors.loadFailed'));
    } finally {
        loading.value = false;
    }
};

const loadPublishingProvider = async () => {
    try {
        const brand = brandsStore.brands.find(b =>
            b.id === props.brandId || b.public_id === props.brandId
        );
        if (brand) {
            publishingProvider.value = brand.publishing_provider || '';
        }
    } catch {
        // Silently fail — defaults to auto
    }
};

const savePublishingProvider = async () => {
    savingPublishingProvider.value = true;
    try {
        await brandsStore.updateBrand(props.brandId, {
            publishing_provider: publishingProvider.value || null,
        });
        toast.success(t('publishingProvider.saved'));
    } catch {
        toast.error(t('publishingProvider.saveFailed'));
    } finally {
        savingPublishingProvider.value = false;
    }
};

const saveKey = async (provider) => {
    const apiKey = keyInputs.value[provider];
    if (!apiKey) return;

    savingProvider.value = provider;
    try {
        await axios.post(`/api/v1/brands/${props.brandId}/ai-keys`, {
            provider,
            api_key: apiKey,
        });
        toast.success(t('aiKeys.success.saved'));
        keyInputs.value[provider] = '';
        await loadKeys();
    } catch (error) {
        toast.error(t('aiKeys.errors.saveFailed'));
    } finally {
        savingProvider.value = null;
    }
};

const deleteKey = async (provider) => {
    if (!confirm(t('aiKeys.form.confirmDelete'))) return;

    deletingProvider.value = provider;
    try {
        await axios.delete(`/api/v1/brands/${props.brandId}/ai-keys/${provider}`);
        toast.success(t('aiKeys.success.deleted'));
        await loadKeys();
    } catch (error) {
        toast.error(t('aiKeys.errors.deleteFailed'));
    } finally {
        deletingProvider.value = null;
    }
};

onMounted(() => {
    loadKeys();
    loadPublishingProvider();
});
</script>

<template>
    <div>
        <!-- Publishing Provider Selector -->
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ t('publishingProvider.title') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ t('publishingProvider.subtitle') }}
            </p>

            <div class="mt-4 flex items-center gap-3">
                <select
                    v-model="publishingProvider"
                    @change="savePublishingProvider"
                    :disabled="savingPublishingProvider"
                    class="block w-full max-w-xs px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:opacity-50"
                >
                    <option
                        v-for="option in filteredPublishingOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ t(option.labelKey) }}
                    </option>
                </select>
                <LoadingSpinner v-if="savingPublishingProvider" class="w-5 h-5" />
            </div>
        </div>

        <!-- AI Keys Title -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ t('aiKeys.title') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ t('aiKeys.subtitle') }}
            </p>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-12">
            <LoadingSpinner />
        </div>

        <div v-else class="space-y-4">
            <div
                v-for="provider in providers"
                :key="provider.provider"
                class="border border-gray-200 rounded-xl p-5 bg-white"
            >
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <div
                            class="w-12 h-12 rounded-full flex items-center justify-center"
                            :class="[providerColors[provider.provider]?.bg, providerColors[provider.provider]?.text]"
                        >
                            <span v-html="providerIcons[provider.provider]" />
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">
                                {{ t(`aiKeys.providers.${provider.provider}`) }}
                            </h3>
                            <p class="text-sm flex items-center gap-1.5" :class="provider.has_key ? 'text-green-600' : 'text-gray-500'">
                                <span
                                    class="w-2 h-2 rounded-full inline-block"
                                    :class="provider.has_key ? 'bg-green-500' : 'bg-gray-300'"
                                />
                                {{ provider.has_key ? t('aiKeys.status.hasKey') : t('aiKeys.status.noKey') }}
                            </p>
                        </div>
                    </div>

                    <Button
                        v-if="provider.has_key"
                        variant="secondary"
                        size="sm"
                        @click="deleteKey(provider.provider)"
                        :loading="deletingProvider === provider.provider"
                        :disabled="deletingProvider === provider.provider"
                        class="text-red-600 hover:text-red-700 hover:bg-red-50"
                    >
                        {{ t('aiKeys.form.delete') }}
                    </Button>
                </div>

                <!-- Key Input -->
                <div class="flex gap-3">
                    <div class="flex-1">
                        <input
                            type="password"
                            v-model="keyInputs[provider.provider]"
                            :placeholder="provider.has_key ? '••••••••••••••••' : t('aiKeys.form.apiKeyPlaceholder')"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors"
                        />
                    </div>
                    <Button
                        size="sm"
                        @click="saveKey(provider.provider)"
                        :loading="savingProvider === provider.provider"
                        :disabled="!keyInputs[provider.provider] || savingProvider === provider.provider"
                    >
                        {{ t('aiKeys.form.save') }}
                    </Button>
                </div>
            </div>

            <!-- Info box -->
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-blue-700">
                        {{ t('aiKeys.subtitle') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
