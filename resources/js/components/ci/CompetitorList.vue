<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    competitors: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['add', 'edit', 'remove', 'view', 'discover']);

const platformColors = {
    instagram: 'bg-pink-500/10 text-pink-400 border-pink-500/20',
    tiktok: 'bg-purple-500/10 text-purple-400 border-purple-500/20',
    linkedin: 'bg-sky-500/10 text-sky-400 border-sky-500/20',
    youtube: 'bg-red-500/10 text-red-400 border-red-500/20',
    twitter: 'bg-gray-500/10 text-gray-300 border-gray-500/20',
};
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white">{{ t('ci.competitors.title') }}</h2>
            <div class="flex items-center gap-2">
                <button
                    @click="emit('discover')"
                    class="inline-flex items-center gap-2 rounded-lg border border-orange-600 px-3 py-1.5 text-sm font-medium text-orange-400 hover:bg-orange-600/10 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                    </svg>
                    {{ t('ci.discover.button') }}
                </button>
                <button
                    @click="emit('add')"
                    class="inline-flex items-center gap-2 rounded-lg bg-orange-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-500 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ t('ci.competitors.add') }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-8">
            <div class="w-6 h-6 border-2 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else-if="competitors.length === 0" class="rounded-xl bg-gray-900 border border-gray-800 p-8 text-center">
            <div class="w-12 h-12 rounded-full bg-orange-500/10 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                </svg>
            </div>
            <p class="text-sm text-gray-400">{{ t('ci.competitors.noCompetitors') }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ t('ci.competitors.noCompetitorsDescription') }}</p>
            <button
                @click="emit('discover')"
                class="mt-4 inline-flex items-center gap-2 rounded-lg border border-orange-600 px-4 py-2 text-sm font-medium text-orange-400 hover:bg-orange-600/10 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                </svg>
                {{ t('ci.discover.button') }}
            </button>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div
                v-for="competitor in competitors"
                :key="competitor.public_id"
                class="rounded-xl bg-gray-900 border border-gray-800 p-4 group hover:border-gray-700 transition-colors cursor-pointer"
                @click="emit('view', competitor)"
            >
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-semibold text-white truncate">{{ competitor.name }}</h3>
                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                            @click.stop="emit('edit', competitor)"
                            class="p-1 text-gray-500 hover:text-white transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                            </svg>
                        </button>
                        <button
                            @click.stop="emit('remove', competitor)"
                            class="p-1 text-gray-500 hover:text-red-400 transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-1.5 mb-2">
                    <span
                        v-for="account in competitor.accounts"
                        :key="account.id"
                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs border"
                        :class="platformColors[account.platform] || 'bg-gray-500/10 text-gray-400 border-gray-500/20'"
                    >
                        {{ account.platform }}
                        <span class="text-gray-500">@{{ account.handle }}</span>
                    </span>
                </div>

                <div class="flex items-center justify-between text-xs text-gray-500">
                    <span>{{ t('ci.competitors.postsCount', { count: competitor.posts_count || 0 }) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
