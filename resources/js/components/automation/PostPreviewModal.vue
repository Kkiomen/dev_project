<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useBrandsStore } from '@/stores/brands';
import Modal from '@/components/common/Modal.vue';

const { t } = useI18n();
const router = useRouter();
const brandsStore = useBrandsStore();

const props = defineProps({
    show: { type: Boolean, default: false },
    post: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const activePlatform = ref('instagram');

const platforms = computed(() => {
    if (!props.post?.platform_posts) return [];
    return props.post.platform_posts
        .filter(pp => pp.enabled)
        .map(pp => pp.platform);
});

// Set default platform when post changes
watch(() => props.post, (newPost) => {
    if (newPost?.platform_posts?.length) {
        const enabledPlatform = newPost.platform_posts.find(pp => pp.enabled);
        if (enabledPlatform) {
            activePlatform.value = enabledPlatform.platform;
        }
    }
}, { immediate: true });

const currentPlatformPost = computed(() => {
    if (!props.post?.platform_posts) return null;
    return props.post.platform_posts.find(pp => pp.platform === activePlatform.value);
});

const brandName = computed(() => {
    return brandsStore.currentBrand?.name || 'Brand';
});

const brandAvatar = computed(() => {
    return brandsStore.currentBrand?.avatar_url || null;
});

const caption = computed(() => {
    return currentPlatformPost.value?.platform_caption || props.post?.main_caption || '';
});

const hashtags = computed(() => {
    return currentPlatformPost.value?.hashtags || [];
});

const formattedDate = computed(() => {
    if (!props.post?.scheduled_at) return t('postAutomation.preview.justNow');
    const date = new Date(props.post.scheduled_at);
    const now = new Date();
    const diffMs = date - now;
    const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return t('postAutomation.preview.today');
    if (diffDays === 1) return t('postAutomation.preview.tomorrow');
    if (diffDays > 0) return date.toLocaleDateString();
    return t('postAutomation.preview.justNow');
});

const mediaUrl = computed(() => {
    if (props.post?.media?.length) {
        return props.post.media[0].url;
    }
    return null;
});

function openEditor() {
    if (props.post) {
        router.push({ name: 'post.edit', params: { postId: props.post.id } });
        emit('close');
    }
}

const platformTabs = [
    { key: 'instagram', label: 'Instagram', icon: 'instagram' },
    { key: 'facebook', label: 'Facebook', icon: 'facebook' },
    { key: 'youtube', label: 'YouTube', icon: 'youtube' },
];

function selectPlatform(platform) {
    activePlatform.value = platform;
}

function isPlatformEnabled(platform) {
    return platforms.value.includes(platform);
}
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <div v-if="post" class="space-y-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ t('postAutomation.preview.title') }}
                </h2>
            </div>

            <!-- Platform Tabs -->
            <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
                <button
                    v-for="tab in platformTabs"
                    :key="tab.key"
                    @click="selectPlatform(tab.key)"
                    :disabled="!isPlatformEnabled(tab.key)"
                    class="flex-1 py-2 px-3 text-sm font-medium rounded-md transition-all"
                    :class="[
                        activePlatform === tab.key
                            ? 'bg-white shadow text-gray-900'
                            : isPlatformEnabled(tab.key)
                                ? 'text-gray-600 hover:text-gray-900'
                                : 'text-gray-300 cursor-not-allowed'
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Instagram Mockup -->
            <div v-if="activePlatform === 'instagram'" class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <!-- Instagram Header -->
                <div class="flex items-center gap-3 p-3 border-b border-gray-100">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 via-pink-500 to-orange-400 p-0.5">
                        <div class="w-full h-full rounded-full bg-white p-0.5">
                            <img
                                v-if="brandAvatar"
                                :src="brandAvatar"
                                :alt="brandName"
                                class="w-full h-full rounded-full object-cover"
                            />
                            <div v-else class="w-full h-full rounded-full bg-gray-200 flex items-center justify-center">
                                <span class="text-xs font-bold text-gray-500">{{ brandName.charAt(0) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-900">{{ brandName }}</p>
                    </div>
                    <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="6" r="1.5"/>
                        <circle cx="12" cy="12" r="1.5"/>
                        <circle cx="12" cy="18" r="1.5"/>
                    </svg>
                </div>

                <!-- Image -->
                <div class="aspect-square bg-gray-100 relative">
                    <img
                        v-if="mediaUrl"
                        :src="mediaUrl"
                        alt="Post image"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-400">
                        <svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-sm">{{ t('postAutomation.preview.noImage') }}</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="px-3 pt-3">
                    <div class="flex items-center gap-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                        </svg>
                        <svg class="w-6 h-6 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                        </svg>
                    </div>
                </div>

                <!-- Caption -->
                <div class="p-3 pt-2">
                    <div v-if="caption" class="text-sm">
                        <span class="font-semibold">{{ brandName }}</span>
                        <span class="ml-1 whitespace-pre-wrap">{{ caption }}</span>
                        <span v-if="hashtags.length" class="text-blue-600 ml-1">
                            {{ hashtags.map(h => '#' + h).join(' ') }}
                        </span>
                    </div>
                    <p v-else class="text-sm text-gray-400 italic">
                        {{ t('postAutomation.preview.noContent') }}
                    </p>
                    <p class="text-xs text-gray-400 mt-1 uppercase">{{ formattedDate }}</p>
                </div>
            </div>

            <!-- Facebook Mockup -->
            <div v-if="activePlatform === 'facebook'" class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <!-- Facebook Header -->
                <div class="flex items-center gap-3 p-3">
                    <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center overflow-hidden">
                        <img
                            v-if="brandAvatar"
                            :src="brandAvatar"
                            :alt="brandName"
                            class="w-full h-full object-cover"
                        />
                        <span v-else class="text-white font-bold">{{ brandName.charAt(0) }}</span>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-900">{{ brandName }}</p>
                        <div class="flex items-center gap-1 text-xs text-gray-500">
                            <span>{{ formattedDate }}</span>
                            <span>·</span>
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                            </svg>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="6" r="1.5"/>
                        <circle cx="12" cy="12" r="1.5"/>
                        <circle cx="12" cy="18" r="1.5"/>
                    </svg>
                </div>

                <!-- Caption -->
                <div class="px-3 pb-3">
                    <p v-if="caption" class="text-sm text-gray-900 whitespace-pre-wrap">{{ caption }}</p>
                    <p v-else class="text-sm text-gray-400 italic">{{ t('postAutomation.preview.noContent') }}</p>
                </div>

                <!-- Image -->
                <div class="aspect-video bg-gray-100 relative">
                    <img
                        v-if="mediaUrl"
                        :src="mediaUrl"
                        alt="Post image"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-400">
                        <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-sm">{{ t('postAutomation.preview.noImage') }}</span>
                    </div>
                </div>

                <!-- Reactions -->
                <div class="p-3 border-t border-gray-100">
                    <div class="flex items-center justify-between text-sm text-gray-500 mb-2">
                        <div class="flex items-center gap-1">
                            <div class="flex -space-x-1">
                                <span class="w-5 h-5 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs">👍</span>
                                <span class="w-5 h-5 rounded-full bg-red-500 flex items-center justify-center text-white text-xs">❤️</span>
                            </div>
                            <span class="ml-1">0</span>
                        </div>
                        <span>0 {{ t('postAutomation.preview.comments') }}</span>
                    </div>
                    <div class="flex items-center gap-1 border-t border-gray-100 pt-2">
                        <button class="flex-1 flex items-center justify-center gap-2 py-2 rounded-md hover:bg-gray-50 text-sm text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5" />
                            </svg>
                            {{ t('postAutomation.preview.like') }}
                        </button>
                        <button class="flex-1 flex items-center justify-center gap-2 py-2 rounded-md hover:bg-gray-50 text-sm text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            {{ t('postAutomation.preview.comment') }}
                        </button>
                        <button class="flex-1 flex items-center justify-center gap-2 py-2 rounded-md hover:bg-gray-50 text-sm text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                            </svg>
                            {{ t('postAutomation.preview.share') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- YouTube Mockup -->
            <div v-if="activePlatform === 'youtube'" class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <!-- Video Thumbnail -->
                <div class="aspect-video bg-gray-900 relative">
                    <img
                        v-if="mediaUrl"
                        :src="mediaUrl"
                        alt="Video thumbnail"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-500">
                        <svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <span class="text-sm">{{ t('postAutomation.preview.noVideo') }}</span>
                    </div>
                    <!-- Play Button Overlay -->
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-16 h-16 rounded-full bg-black/70 flex items-center justify-center">
                            <svg class="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                        </div>
                    </div>
                    <!-- Duration Badge -->
                    <div class="absolute bottom-2 right-2 bg-black/80 text-white text-xs px-1 rounded">
                        0:00
                    </div>
                </div>

                <!-- Video Info -->
                <div class="p-3">
                    <div class="flex gap-3">
                        <div class="w-9 h-9 rounded-full bg-red-500 flex items-center justify-center overflow-hidden flex-shrink-0">
                            <img
                                v-if="brandAvatar"
                                :src="brandAvatar"
                                :alt="brandName"
                                class="w-full h-full object-cover"
                            />
                            <span v-else class="text-white font-bold text-sm">{{ brandName.charAt(0) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 line-clamp-2">
                                {{ currentPlatformPost?.video_title || post.title || t('postAutomation.preview.untitledVideo') }}
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ brandName }} · 0 {{ t('postAutomation.preview.views') }} · {{ formattedDate }}
                            </p>
                        </div>
                        <svg class="w-5 h-5 text-gray-600 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <circle cx="12" cy="6" r="1.5"/>
                            <circle cx="12" cy="12" r="1.5"/>
                            <circle cx="12" cy="18" r="1.5"/>
                        </svg>
                    </div>
                    <!-- Description Preview -->
                    <div v-if="currentPlatformPost?.video_description || caption" class="mt-3 p-2 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-600 line-clamp-2">
                            {{ currentPlatformPost?.video_description || caption }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-2">
                <button
                    @click="emit('close')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    {{ t('common.close') }}
                </button>
                <button
                    @click="openEditor"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                >
                    {{ t('postAutomation.preview.openEditor') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
