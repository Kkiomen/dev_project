<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { useToast } from '@/composables/useToast';
import Button from '@/components/common/Button.vue';
import Modal from '@/components/common/Modal.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import api from '@/api';

const props = defineProps({
    brandId: {
        type: [String, Number],
        required: true,
    },
});

const { t } = useI18n();
const route = useRoute();
const toast = useToast();

const loading = ref(true);
const connecting = ref(false);
const platforms = ref({
    facebook: null,
    instagram: null,
});

const showPageSelector = ref(false);
const availablePages = ref([]);
const selectedPageId = ref(null);
const loadingPages = ref(false);

const facebookIcon = `<svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>`;

const instagramIcon = `<svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>`;

const loadPlatforms = async () => {
    loading.value = true;
    try {
        const response = await api.get(`/v1/brands/${props.brandId}/platforms`);
        platforms.value = response.data.platforms;
    } catch (error) {
        console.error('Failed to load platforms:', error);
    } finally {
        loading.value = false;
    }
};

const connectFacebook = async () => {
    connecting.value = true;
    try {
        const response = await api.get(`/v1/brands/${props.brandId}/platforms/facebook/auth-url`);
        // Redirect to Facebook OAuth
        window.location.href = response.data.auth_url;
    } catch (error) {
        toast.error(t('connectedPlatforms.connectionError'));
        connecting.value = false;
    }
};

const loadAvailablePages = async () => {
    loadingPages.value = true;
    try {
        const response = await api.get(`/v1/brands/${props.brandId}/platforms/facebook/pages`);
        availablePages.value = response.data.pages;
        if (availablePages.value.length === 1) {
            selectedPageId.value = availablePages.value[0].id;
        }
    } catch (error) {
        toast.error(t('connectedPlatforms.noPages'));
        showPageSelector.value = false;
    } finally {
        loadingPages.value = false;
    }
};

const confirmPageSelection = async () => {
    if (!selectedPageId.value) return;

    connecting.value = true;
    try {
        const response = await api.post(`/v1/brands/${props.brandId}/platforms/facebook/select-page`, {
            page_id: selectedPageId.value,
        });

        toast.success(t('connectedPlatforms.connectionSuccess'));
        showPageSelector.value = false;
        await loadPlatforms();
    } catch (error) {
        toast.error(t('connectedPlatforms.connectionError'));
    } finally {
        connecting.value = false;
    }
};

const disconnectPlatform = async (platform) => {
    if (!confirm(t('connectedPlatforms.disconnectConfirm', { platform }))) {
        return;
    }

    try {
        await api.delete(`/v1/brands/${props.brandId}/platforms/${platform}`);
        toast.success(t('connectedPlatforms.disconnectSuccess'));
        await loadPlatforms();
    } catch (error) {
        toast.error(t('common.error'));
    }
};

// Check if we're returning from OAuth flow
onMounted(async () => {
    await loadPlatforms();

    // Check URL params for OAuth redirect
    if (route.query.step === 'select-page') {
        showPageSelector.value = true;
        await loadAvailablePages();
    }
});

const selectedPage = computed(() => {
    return availablePages.value.find(p => p.id === selectedPageId.value);
});
</script>

<template>
    <div>
        <!-- Title -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ t('connectedPlatforms.title') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ t('connectedPlatforms.subtitle') }}
            </p>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-12">
            <LoadingSpinner />
        </div>

        <div v-else class="space-y-4">
            <!-- Facebook -->
            <div class="border border-gray-200 rounded-xl p-5 bg-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                            <span v-html="facebookIcon" />
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">Facebook</h3>
                            <p v-if="platforms.facebook?.connected" class="text-sm text-green-600 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                {{ t('connectedPlatforms.connectedAs', { name: platforms.facebook.account_name }) }}
                            </p>
                            <p v-else class="text-sm text-gray-500">
                                {{ t('connectedPlatforms.notConnected') }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <Button
                            v-if="platforms.facebook?.connected"
                            variant="secondary"
                            size="sm"
                            @click="disconnectPlatform('facebook')"
                        >
                            {{ t('connectedPlatforms.disconnect') }}
                        </Button>
                        <Button
                            v-else
                            @click="connectFacebook"
                            :loading="connecting"
                            size="sm"
                        >
                            {{ t('connectedPlatforms.connectFacebook') }}
                        </Button>
                    </div>
                </div>

                <!-- Token warning -->
                <div
                    v-if="platforms.facebook?.is_expiring_soon && !platforms.facebook?.is_expired"
                    class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-center gap-3"
                >
                    <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p class="text-sm text-amber-700">{{ t('connectedPlatforms.tokenExpiringSoon') }}</p>
                    <Button variant="secondary" size="sm" @click="connectFacebook" class="ml-auto">
                        {{ t('connectedPlatforms.reconnect') }}
                    </Button>
                </div>

                <div
                    v-if="platforms.facebook?.is_expired"
                    class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-center gap-3"
                >
                    <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-red-700">{{ t('connectedPlatforms.tokenExpired') }}</p>
                    <Button variant="primary" size="sm" @click="connectFacebook" class="ml-auto">
                        {{ t('connectedPlatforms.reconnect') }}
                    </Button>
                </div>
            </div>

            <!-- Instagram -->
            <div class="border border-gray-200 rounded-xl p-5 bg-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-500 via-pink-500 to-orange-400 flex items-center justify-center text-white">
                            <span v-html="instagramIcon" />
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">Instagram</h3>
                            <p v-if="platforms.instagram?.connected" class="text-sm text-green-600 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                {{ t('connectedPlatforms.connectedAs', { name: '@' + platforms.instagram.account_name }) }}
                            </p>
                            <p v-else class="text-sm text-gray-500">
                                {{ t('connectedPlatforms.instagramRequiresFacebook') }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <span
                            v-if="platforms.instagram?.connected"
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"
                        >
                            {{ t('connectedPlatforms.connected') }}
                        </span>
                        <span
                            v-else
                            class="text-xs text-gray-400"
                        >
                            {{ t('connectedPlatforms.instagramAutoConnect') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Info box -->
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm text-blue-700">
                        <p class="font-medium mb-1">{{ t('connectedPlatforms.publishingMethod') }}</p>
                        <p v-if="platforms.facebook?.connected">
                            <strong>{{ t('connectedPlatforms.directApi') }}:</strong> {{ t('connectedPlatforms.directApiDescription') }}
                        </p>
                        <p v-else>
                            <strong>{{ t('connectedPlatforms.webhook') }}:</strong> {{ t('connectedPlatforms.webhookDescription') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Selector Modal -->
        <Modal v-model="showPageSelector" size="md">
            <template #header>
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ t('connectedPlatforms.selectPage') }}
                </h3>
            </template>

            <div v-if="loadingPages" class="flex items-center justify-center py-12">
                <LoadingSpinner />
            </div>

            <div v-else-if="availablePages.length === 0" class="py-8 text-center">
                <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-gray-600">{{ t('connectedPlatforms.noPages') }}</p>
            </div>

            <div v-else class="space-y-3">
                <p class="text-sm text-gray-600 mb-4">
                    {{ t('connectedPlatforms.selectPageDescription') }}
                </p>

                <label
                    v-for="page in availablePages"
                    :key="page.id"
                    class="flex items-center gap-4 p-4 border rounded-lg cursor-pointer transition-colors"
                    :class="[
                        selectedPageId === page.id
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    <input
                        type="radio"
                        :value="page.id"
                        v-model="selectedPageId"
                        class="w-4 h-4 text-blue-600 focus:ring-blue-500"
                    />
                    <div class="flex-1">
                        <p class="font-medium text-gray-900">{{ page.name }}</p>
                        <p v-if="page.has_instagram" class="text-sm text-gray-500">
                            {{ t('connectedPlatforms.withInstagram', { username: page.instagram?.username || page.instagram?.id }) }}
                        </p>
                    </div>
                    <div v-if="page.has_instagram" class="flex-shrink-0">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-pink-100 text-pink-800">
                            + Instagram
                        </span>
                    </div>
                </label>
            </div>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showPageSelector = false">
                        {{ t('connectedPlatforms.cancel') }}
                    </Button>
                    <Button
                        @click="confirmPageSelection"
                        :loading="connecting"
                        :disabled="!selectedPageId"
                    >
                        {{ t('connectedPlatforms.confirm') }}
                    </Button>
                </div>
            </template>
        </Modal>
    </div>
</template>
