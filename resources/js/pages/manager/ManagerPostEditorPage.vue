<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const managerStore = useManagerStore();
const toast = useToast();

const postId = route.params.id;

const platforms = [
    { key: 'instagram', label: 'Instagram', abbr: 'IG' },
    { key: 'facebook', label: 'Facebook', abbr: 'FB' },
    { key: 'tiktok', label: 'TikTok', abbr: 'TT' },
    { key: 'linkedin', label: 'LinkedIn', abbr: 'LI' },
    { key: 'x', label: 'X', abbr: 'X' },
    { key: 'youtube', label: 'YouTube', abbr: 'YT' },
];

const localPost = ref({
    title: '',
    caption: '',
    hashtags: [],
    platform_captions: {
        instagram: '',
        facebook: '',
        tiktok: '',
        linkedin: '',
        x: '',
        youtube: '',
    },
    scheduled_at: '',
    scheduled_time: '',
    status: 'draft',
});

const activePlatformTab = ref('instagram');
const hashtagInput = ref('');
const showDeleteConfirm = ref(false);
const aiGenerating = ref({
    caption: false,
    hashtags: false,
    platforms: false,
    variants: false,
});

const captionLength = computed(() => localPost.value.caption.length);

const currentPlatformCaption = computed({
    get() {
        return localPost.value.platform_captions[activePlatformTab.value] || '';
    },
    set(value) {
        localPost.value.platform_captions[activePlatformTab.value] = value;
    },
});

const platformCaptionPlaceholder = computed(() => {
    return localPost.value.caption || t('manager.postEditor.captionPlaceholder');
});

const previewCaption = computed(() => {
    const caption = localPost.value.caption;
    if (!caption) return '';
    return caption.length > 150 ? caption.substring(0, 150) + '...' : caption;
});

const activePlatformsList = computed(() => {
    const active = [];
    for (const platform of platforms) {
        if (localPost.value.platform_captions[platform.key]) {
            active.push(platform);
        }
    }
    if (active.length === 0 && localPost.value.caption) {
        return platforms;
    }
    return active;
});

const formattedCreatedDate = computed(() => {
    return new Date().toLocaleDateString();
});

function addHashtag() {
    const tag = hashtagInput.value.trim().replace(/^#/, '');
    if (tag && !localPost.value.hashtags.includes(tag)) {
        localPost.value.hashtags.push(tag);
    }
    hashtagInput.value = '';
}

function removeHashtag(index) {
    localPost.value.hashtags.splice(index, 1);
}

function handleHashtagKeydown(event) {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        addHashtag();
    }
}

async function generateCaption() {
    aiGenerating.value.caption = true;
    try {
        // Placeholder action
        await new Promise(resolve => setTimeout(resolve, 1000));
        toast.info(t('manager.postEditor.aiActions.generating'));
    } finally {
        aiGenerating.value.caption = false;
    }
}

async function generateHashtags() {
    aiGenerating.value.hashtags = true;
    try {
        await new Promise(resolve => setTimeout(resolve, 1000));
        toast.info(t('manager.postEditor.aiActions.generating'));
    } finally {
        aiGenerating.value.hashtags = false;
    }
}

async function adaptToPlatforms() {
    aiGenerating.value.platforms = true;
    try {
        await new Promise(resolve => setTimeout(resolve, 1000));
        toast.info(t('manager.postEditor.aiActions.generating'));
    } finally {
        aiGenerating.value.platforms = false;
    }
}

async function generateVariants() {
    aiGenerating.value.variants = true;
    try {
        await new Promise(resolve => setTimeout(resolve, 1000));
        toast.info(t('manager.postEditor.aiActions.generating'));
    } finally {
        aiGenerating.value.variants = false;
    }
}

function saveDraft() {
    try {
        localPost.value.status = 'draft';
        toast.success(t('manager.postEditor.saved'));
    } catch {
        toast.error(t('manager.postEditor.saveError'));
    }
}

function submitForApproval() {
    try {
        localPost.value.status = 'pending_approval';
        toast.success(t('manager.postEditor.submitted'));
    } catch {
        toast.error(t('manager.postEditor.submitError'));
    }
}

function deletePost() {
    showDeleteConfirm.value = false;
    router.push('/app/manager/content');
}

function goBack() {
    router.push('/app/manager/content');
}

const statusColors = {
    draft: 'bg-gray-500/20 text-gray-300',
    pending_approval: 'bg-yellow-500/20 text-yellow-400',
    approved: 'bg-blue-500/20 text-blue-400',
    scheduled: 'bg-purple-500/20 text-purple-400',
    published: 'bg-emerald-500/20 text-emerald-400',
    failed: 'bg-red-500/20 text-red-400',
};
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div class="mb-6 sm:mb-8">
            <button
                @click="goBack"
                class="flex items-center gap-2 text-sm text-gray-400 hover:text-gray-200 transition mb-4"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                {{ t('manager.postEditor.backToContent') }}
            </button>
            <div class="flex flex-col sm:flex-row sm:items-center sm:gap-3">
                <h1 class="text-xl sm:text-2xl font-bold text-white">{{ t('manager.postEditor.title') }}</h1>
                <span
                    :class="statusColors[localPost.status] || statusColors.draft"
                    class="mt-2 sm:mt-0 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium w-fit"
                >
                    {{ t(`posts.status.${localPost.status}`) }}
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-400">{{ t('manager.postEditor.subtitle') }}</p>
        </div>

        <!-- Two-column layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left column -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Title field -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-6">
                    <label class="block text-sm font-medium text-white mb-2">
                        {{ t('manager.postEditor.titleField') }}
                    </label>
                    <input
                        v-model="localPost.title"
                        type="text"
                        :placeholder="t('manager.postEditor.titlePlaceholder')"
                        class="w-full rounded-lg bg-gray-800 border border-gray-700 px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                    />
                </div>

                <!-- Caption editor -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-6">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-white">
                            {{ t('manager.postEditor.captionField') }}
                        </label>
                        <span class="text-xs text-gray-500">
                            {{ captionLength }} {{ t('manager.postEditor.characters') }}
                        </span>
                    </div>
                    <textarea
                        v-model="localPost.caption"
                        :placeholder="t('manager.postEditor.captionPlaceholder')"
                        rows="6"
                        class="w-full rounded-lg bg-gray-800 border border-gray-700 px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition resize-y"
                    ></textarea>
                </div>

                <!-- Platform tabs -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-6">
                    <label class="block text-sm font-medium text-white mb-3">
                        {{ t('manager.postEditor.platformTabs') }}
                    </label>
                    <div class="flex flex-wrap gap-1 mb-4 border-b border-gray-800 pb-3">
                        <button
                            v-for="platform in platforms"
                            :key="platform.key"
                            @click="activePlatformTab = platform.key"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium transition',
                                activePlatformTab === platform.key
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-800 text-gray-400 hover:text-gray-200 hover:bg-gray-700',
                            ]"
                        >
                            <span class="sm:hidden">{{ platform.abbr }}</span>
                            <span class="hidden sm:inline">{{ platform.label }}</span>
                        </button>
                    </div>
                    <textarea
                        v-model="currentPlatformCaption"
                        :placeholder="platformCaptionPlaceholder"
                        rows="4"
                        class="w-full rounded-lg bg-gray-800 border border-gray-700 px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition resize-y"
                    ></textarea>
                    <p class="mt-1.5 text-xs text-gray-500">
                        {{ t('manager.postEditor.platformCaptionHint') }}
                    </p>
                </div>

                <!-- Hashtags -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-6">
                    <label class="block text-sm font-medium text-white mb-2">
                        {{ t('manager.postEditor.hashtags') }}
                    </label>
                    <div class="flex gap-2 mb-3">
                        <input
                            v-model="hashtagInput"
                            type="text"
                            :placeholder="t('manager.postEditor.hashtagPlaceholder')"
                            @keydown="handleHashtagKeydown"
                            class="flex-1 rounded-lg bg-gray-800 border border-gray-700 px-4 py-2 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm"
                        />
                        <button
                            @click="addHashtag"
                            class="px-4 py-2 rounded-lg bg-gray-700 text-gray-300 hover:bg-gray-600 hover:text-white transition text-sm font-medium"
                        >
                            {{ t('manager.postEditor.addHashtag') }}
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2" v-if="localPost.hashtags.length">
                        <span
                            v-for="(tag, index) in localPost.hashtags"
                            :key="index"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-500/15 text-indigo-400 text-sm"
                        >
                            <span>#{{ tag }}</span>
                            <button
                                @click="removeHashtag(index)"
                                class="hover:text-indigo-200 transition"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    </div>
                </div>

                <!-- AI Actions toolbar -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-6">
                    <label class="block text-sm font-medium text-white mb-3">
                        {{ t('manager.postEditor.aiActionsTitle') }}
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
                        <button
                            @click="generateCaption"
                            :disabled="aiGenerating.caption"
                            class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-gray-300 hover:bg-gray-700 hover:text-white hover:border-gray-600 transition text-xs sm:text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                            </svg>
                            <span class="truncate">{{ t('manager.postEditor.aiActions.generateCaption') }}</span>
                        </button>
                        <button
                            @click="generateHashtags"
                            :disabled="aiGenerating.hashtags"
                            class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-gray-300 hover:bg-gray-700 hover:text-white hover:border-gray-600 transition text-xs sm:text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5-3.9 19.5m-2.1-19.5-3.9 19.5" />
                            </svg>
                            <span class="truncate">{{ t('manager.postEditor.aiActions.generateHashtags') }}</span>
                        </button>
                        <button
                            @click="adaptToPlatforms"
                            :disabled="aiGenerating.platforms"
                            class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-gray-300 hover:bg-gray-700 hover:text-white hover:border-gray-600 transition text-xs sm:text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.92 17.92 0 0 1-8.716-2.247m0 0A8.966 8.966 0 0 1 3 12c0-1.264.26-2.466.732-3.558" />
                            </svg>
                            <span class="truncate">{{ t('manager.postEditor.aiActions.adaptPlatforms') }}</span>
                        </button>
                        <button
                            @click="generateVariants"
                            :disabled="aiGenerating.variants"
                            class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-gray-300 hover:bg-gray-700 hover:text-white hover:border-gray-600 transition text-xs sm:text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                            </svg>
                            <span class="truncate">{{ t('manager.postEditor.aiActions.generateVariants') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Scheduling section -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-6">
                    <label class="block text-sm font-medium text-white mb-3">
                        {{ t('manager.postEditor.schedule') }}
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">
                                {{ t('manager.postEditor.scheduledDate') }}
                            </label>
                            <input
                                v-model="localPost.scheduled_at"
                                type="date"
                                class="w-full rounded-lg bg-gray-800 border border-gray-700 px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition [color-scheme:dark]"
                            />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">
                                {{ t('manager.postEditor.scheduledTime') }}
                            </label>
                            <input
                                v-model="localPost.scheduled_time"
                                type="time"
                                class="w-full rounded-lg bg-gray-800 border border-gray-700 px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition [color-scheme:dark]"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right column -->
            <div class="space-y-6">
                <!-- Post Preview Card -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-white mb-4">
                        {{ t('manager.postEditor.preview') }}
                    </h3>
                    <div class="rounded-lg bg-gray-800 border border-gray-700 overflow-hidden">
                        <!-- Brand header -->
                        <div class="flex items-center gap-3 p-3 border-b border-gray-700">
                            <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-white">{{ t('manager.postEditor.previewTitle') }}</p>
                                <p class="text-xs text-gray-500">{{ activePlatformTab }}</p>
                            </div>
                        </div>
                        <!-- Caption -->
                        <div class="p-3">
                            <p v-if="previewCaption" class="text-sm text-gray-300 leading-relaxed">
                                {{ previewCaption }}
                            </p>
                            <p v-else class="text-sm text-gray-500 italic">
                                {{ t('manager.postEditor.previewPlaceholder') }}
                            </p>
                            <!-- Hashtags in preview -->
                            <p v-if="localPost.hashtags.length" class="mt-2 text-sm text-indigo-400">
                                {{ localPost.hashtags.map(h => '#' + h).join(' ') }}
                            </p>
                        </div>
                        <!-- Image placeholder -->
                        <div class="mx-3 mb-3 rounded-lg bg-gray-700/50 border border-gray-600 h-40 flex items-center justify-center">
                            <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                            </svg>
                        </div>
                        <!-- Engagement placeholder -->
                        <div class="flex items-center gap-5 px-3 pb-3 text-gray-500">
                            <button class="flex items-center gap-1.5 hover:text-red-400 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                                </svg>
                                <span class="text-xs">0</span>
                            </button>
                            <button class="flex items-center gap-1.5 hover:text-blue-400 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 0 1-.923 1.785A5.969 5.969 0 0 0 6 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337Z" />
                                </svg>
                                <span class="text-xs">0</span>
                            </button>
                            <button class="flex items-center gap-1.5 hover:text-green-400 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                                </svg>
                                <span class="text-xs">0</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Post Info -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-white mb-4">
                        {{ t('manager.postEditor.postInfo') }}
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-400">{{ t('manager.postEditor.status') }}</span>
                            <span
                                :class="statusColors[localPost.status] || statusColors.draft"
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                            >
                                {{ t(`posts.status.${localPost.status}`) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-400">{{ t('manager.postEditor.created') }}</span>
                            <span class="text-xs text-gray-300">{{ formattedCreatedDate }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block mb-1.5">{{ t('manager.postEditor.platforms') }}</span>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="platform in platforms"
                                    :key="platform.key"
                                    :class="[
                                        'px-2 py-0.5 rounded text-xs font-medium',
                                        localPost.platform_captions[platform.key]
                                            ? 'bg-indigo-500/20 text-indigo-400'
                                            : 'bg-gray-800 text-gray-500',
                                    ]"
                                >
                                    {{ platform.abbr }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-6 space-y-3">
                    <button
                        @click="saveDraft"
                        class="w-full px-4 py-2.5 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition text-sm font-medium"
                    >
                        {{ t('manager.postEditor.saveDraft') }}
                    </button>
                    <button
                        @click="submitForApproval"
                        class="w-full px-4 py-2.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition text-sm font-medium"
                    >
                        {{ t('manager.postEditor.submitForApproval') }}
                    </button>
                    <button
                        @click="showDeleteConfirm = true"
                        class="w-full px-4 py-2 text-red-400 hover:text-red-300 transition text-sm font-medium"
                    >
                        {{ t('manager.postEditor.delete') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete confirmation overlay -->
        <Teleport to="body">
            <div
                v-if="showDeleteConfirm"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                @click.self="showDeleteConfirm = false"
            >
                <div class="w-full max-w-sm rounded-xl bg-gray-900 border border-gray-800 p-6 shadow-2xl">
                    <p class="text-white text-sm mb-4">{{ t('manager.postEditor.deleteConfirm') }}</p>
                    <div class="flex justify-end gap-3">
                        <button
                            @click="showDeleteConfirm = false"
                            class="px-4 py-2 rounded-lg bg-gray-700 text-gray-300 hover:bg-gray-600 transition text-sm font-medium"
                        >
                            {{ t('manager.postEditor.cancelAction') }}
                        </button>
                        <button
                            @click="deletePost"
                            class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-500 transition text-sm font-medium"
                        >
                            {{ t('manager.postEditor.delete') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
