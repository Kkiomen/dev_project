<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const managerStore = useManagerStore();
const toast = useToast();
const connectingPlatform = ref(null);

const platforms = [
    { id: 'instagram', name: 'Instagram', color: 'from-purple-500 to-pink-500', textColor: 'text-pink-400' },
    { id: 'facebook', name: 'Facebook', color: 'from-blue-600 to-blue-500', textColor: 'text-blue-400' },
    { id: 'tiktok', name: 'TikTok', color: 'from-gray-700 to-gray-600', textColor: 'text-gray-300' },
    { id: 'linkedin', name: 'LinkedIn', color: 'from-blue-700 to-blue-600', textColor: 'text-blue-400' },
    { id: 'x', name: 'X (Twitter)', color: 'from-gray-800 to-gray-700', textColor: 'text-gray-300' },
    { id: 'youtube', name: 'YouTube', color: 'from-red-600 to-red-500', textColor: 'text-red-400' },
];

const getAccount = (platformId) => {
    return managerStore.getAccountByPlatform(platformId);
};

const handleConnect = async (platformId) => {
    connectingPlatform.value = platformId;
    try {
        const authUrl = await managerStore.getAuthUrl(platformId);
        if (authUrl) {
            window.location.href = authUrl;
        } else {
            toast.info(t('manager.accounts.oauthPending'));
        }
    } catch (error) {
        toast.error(t('manager.accounts.connectError'));
    } finally {
        connectingPlatform.value = null;
    }
};

const handleDisconnect = async (platformId) => {
    try {
        await managerStore.disconnectAccount(platformId);
        toast.success(t('manager.accounts.disconnected'));
    } catch (error) {
        toast.error(t('manager.accounts.disconnectError'));
    }
};

const connectedCount = computed(() => managerStore.connectedAccounts.length);

onMounted(() => {
    managerStore.fetchAccounts();
});
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white">{{ t('manager.accounts.title') }}</h1>
            <p class="mt-1 text-sm text-gray-400">{{ t('manager.accounts.subtitle') }}</p>
        </div>

        <!-- Connected summary -->
        <div class="rounded-xl bg-gray-900 border border-gray-800 p-4 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-indigo-600/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-white">
                            {{ t('manager.accounts.connectedSummary', { count: connectedCount, total: 6 }) }}
                        </p>
                        <p class="text-xs text-gray-500">{{ t('manager.accounts.connectHint') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading state -->
        <div v-if="managerStore.accountsLoading" class="flex items-center justify-center py-12">
            <LoadingSpinner />
        </div>

        <!-- Platform cards -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="platform in platforms"
                :key="platform.id"
                class="rounded-xl bg-gray-900 border border-gray-800 overflow-hidden hover:border-gray-700 transition-colors"
            >
                <!-- Platform header -->
                <div class="p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <div
                            class="w-12 h-12 rounded-xl bg-gradient-to-br flex items-center justify-center shadow-lg"
                            :class="platform.color"
                        >
                            <span class="text-white text-lg font-bold">{{ platform.name.charAt(0) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-semibold text-white">{{ platform.name }}</h3>
                            <template v-if="getAccount(platform.id)?.is_connected">
                                <p class="text-xs truncate" :class="platform.textColor">
                                    {{ getAccount(platform.id).handle || getAccount(platform.id).display_name }}
                                </p>
                            </template>
                            <template v-else>
                                <p class="text-xs text-gray-500">{{ t('manager.accounts.notConnected') }}</p>
                            </template>
                        </div>
                        <!-- Status indicator -->
                        <div
                            class="w-2.5 h-2.5 rounded-full shrink-0"
                            :class="getAccount(platform.id)?.is_connected ? 'bg-emerald-400' : 'bg-gray-600'"
                        />
                    </div>

                    <!-- Account info when connected -->
                    <div v-if="getAccount(platform.id)?.is_connected" class="space-y-2 mb-4">
                        <div v-if="getAccount(platform.id).followers_count" class="flex items-center justify-between text-xs">
                            <span class="text-gray-500">{{ t('manager.accounts.followers') }}</span>
                            <span class="text-white font-medium">{{ getAccount(platform.id).followers_count.toLocaleString() }}</span>
                        </div>
                        <div v-if="getAccount(platform.id).last_synced_at" class="flex items-center justify-between text-xs">
                            <span class="text-gray-500">{{ t('manager.accounts.lastSynced') }}</span>
                            <span class="text-gray-400">{{ getAccount(platform.id).last_synced_at }}</span>
                        </div>
                    </div>
                </div>

                <!-- Action button -->
                <div class="px-5 pb-5">
                    <button
                        v-if="getAccount(platform.id)?.is_connected"
                        @click="handleDisconnect(platform.id)"
                        class="w-full px-4 py-2 text-sm font-medium rounded-lg border border-gray-700 text-gray-400 hover:border-red-500/50 hover:text-red-400 hover:bg-red-500/10 transition-colors"
                    >
                        {{ t('manager.accounts.disconnect') }}
                    </button>
                    <button
                        v-else
                        @click="handleConnect(platform.id)"
                        :disabled="connectingPlatform === platform.id"
                        class="w-full px-4 py-2.5 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition-colors disabled:opacity-50"
                    >
                        <span v-if="connectingPlatform === platform.id" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ t('common.loading') }}
                        </span>
                        <span v-else>{{ t('manager.accounts.connect') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
