<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const managerStore = useManagerStore();
const toast = useToast();

const newHandle = ref('');
const newPlatform = ref('instagram');
const loading = computed(() => managerStore.strategyLoading);
const submitting = ref(false);

const competitors = computed(() => managerStore.strategy?.competitor_handles || []);

const platforms = ['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube'];

const platformColorClass = (platform) => {
    const map = {
        instagram: 'bg-pink-500/10 text-pink-400 border-pink-500/20',
        facebook: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
        tiktok: 'bg-purple-500/10 text-purple-400 border-purple-500/20',
        linkedin: 'bg-sky-500/10 text-sky-400 border-sky-500/20',
        x: 'bg-gray-500/10 text-gray-300 border-gray-500/20',
        youtube: 'bg-red-500/10 text-red-400 border-red-500/20',
    };
    return map[platform] || 'bg-gray-500/10 text-gray-400 border-gray-500/20';
};

const platformIcon = (platform) => {
    const icons = {
        instagram: 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z',
        facebook: 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
        tiktok: 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z',
        linkedin: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
        x: 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z',
        youtube: 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z',
    };
    return icons[platform] || '';
};

const addCompetitor = async () => {
    const handle = newHandle.value.trim().replace(/^@/, '');
    if (!handle) return;

    const currentHandles = [...competitors.value];
    const entry = `${handle}:${newPlatform.value}`;

    if (currentHandles.includes(entry)) {
        toast.error(t('manager.competitors.alreadyTracked'));
        return;
    }

    currentHandles.push(entry);
    submitting.value = true;

    try {
        await managerStore.updateStrategy({ competitor_handles: currentHandles });
        toast.success(t('manager.competitors.added'));
        newHandle.value = '';
    } catch {
        toast.error(t('manager.competitors.addError'));
    } finally {
        submitting.value = false;
    }
};

const removeCompetitor = async (entry) => {
    if (!confirm(t('manager.competitors.removeConfirm'))) return;

    const currentHandles = competitors.value.filter(h => h !== entry);

    try {
        await managerStore.updateStrategy({ competitor_handles: currentHandles });
        toast.success(t('manager.competitors.removed'));
    } catch {
        toast.error(t('manager.competitors.removeError'));
    }
};

const parseEntry = (entry) => {
    const parts = entry.split(':');
    return {
        handle: parts[0] || entry,
        platform: parts[1] || 'instagram',
    };
};

onMounted(() => {
    if (!managerStore.strategy) {
        managerStore.fetchStrategy();
    }
});
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white">{{ t('manager.competitors.title') }}</h1>
            <p class="mt-1 text-sm text-gray-400">{{ t('manager.competitors.subtitle') }}</p>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
            <LoadingSpinner size="lg" />
        </div>

        <template v-else>
            <!-- Add competitor form -->
            <div class="mb-6 rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-6">
                <h3 class="text-sm font-semibold text-white mb-4">{{ t('manager.competitors.addCompetitor') }}</h3>
                <form @submit.prevent="addCompetitor" class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 text-sm">@</span>
                        <input
                            v-model="newHandle"
                            type="text"
                            :placeholder="t('manager.competitors.handlePlaceholder')"
                            class="w-full rounded-lg border border-gray-700 bg-gray-800 pl-7 pr-3 py-2 text-sm text-white placeholder-gray-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 focus:outline-none"
                            required
                        />
                    </div>
                    <select
                        v-model="newPlatform"
                        class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white focus:border-orange-500 focus:ring-1 focus:ring-orange-500 focus:outline-none"
                    >
                        <option v-for="p in platforms" :key="p" :value="p">{{ p }}</option>
                    </select>
                    <button
                        type="submit"
                        :disabled="submitting || !newHandle.trim()"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ t('common.add') }}
                    </button>
                </form>
            </div>

            <!-- Competitors grid -->
            <div v-if="competitors.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                <div
                    v-for="entry in competitors"
                    :key="entry"
                    class="rounded-xl bg-gray-900 border border-gray-800 p-4 flex items-center gap-4 group"
                >
                    <!-- Platform icon -->
                    <div
                        class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                        :class="platformColorClass(parseEntry(entry).platform)"
                    >
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <path :d="platformIcon(parseEntry(entry).platform)" />
                        </svg>
                    </div>

                    <!-- Handle info -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white truncate">@{{ parseEntry(entry).handle }}</p>
                        <p class="text-xs text-gray-500 capitalize">{{ parseEntry(entry).platform }}</p>
                    </div>

                    <!-- Remove button -->
                    <button
                        @click="removeCompetitor(entry)"
                        class="shrink-0 inline-flex items-center justify-center rounded-lg p-2 text-gray-600 hover:text-red-400 hover:bg-red-500/10 transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100"
                        :title="t('manager.competitors.remove')"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="rounded-xl bg-gray-900 border border-gray-800 p-12 flex flex-col items-center justify-center text-center mb-8">
                <div class="w-16 h-16 rounded-full bg-orange-500/10 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.competitors.noCompetitors') }}</h3>
                <p class="text-sm text-gray-400 max-w-md">{{ t('manager.competitors.noCompetitorsDescription') }}</p>
            </div>

            <!-- Coming soon analytics section -->
            <div class="rounded-xl bg-gray-900 border border-gray-800 border-dashed p-8 flex flex-col items-center justify-center text-center">
                <div class="w-12 h-12 rounded-full bg-orange-500/5 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-orange-400/50" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-400 mb-1">{{ t('manager.competitors.analyticsComingSoon') }}</h3>
                <p class="text-sm text-gray-600 max-w-md">{{ t('manager.competitors.analyticsComingSoonDescription') }}</p>
            </div>
        </template>
    </div>
</template>
