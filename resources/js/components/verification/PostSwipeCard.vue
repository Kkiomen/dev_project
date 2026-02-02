<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import FacebookPreview from '@/components/preview/FacebookPreview.vue';
import InstagramPreview from '@/components/preview/InstagramPreview.vue';
import YouTubePreview from '@/components/preview/YouTubePreview.vue';

const props = defineProps({
    post: {
        type: Object,
        required: true,
    },
    swipeDirection: {
        type: String,
        default: null,
    },
    isAnimating: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['swipe']);

const { t } = useI18n();

// Platform tabs
const activePlatform = ref(props.post.enabled_platforms?.[0] || 'facebook');

const platformDefs = [
    { id: 'facebook', label: 'Facebook', icon: 'F', color: 'blue', bgColor: 'bg-blue-600' },
    { id: 'instagram', label: 'Instagram', icon: 'I', color: 'pink', bgColor: 'bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400' },
    { id: 'youtube', label: 'YouTube', icon: 'Y', color: 'red', bgColor: 'bg-red-600' },
];

const enabledPlatforms = computed(() => {
    return platformDefs.filter(p => props.post.enabled_platforms?.includes(p.id));
});

// Get platform post data
const getPlatformPost = (platform) => {
    return props.post.platform_posts?.find(p => p.platform === platform);
};

// Get effective caption for platform
const getEffectiveCaption = (platform) => {
    const platformPost = getPlatformPost(platform);
    if (platformPost?.platform_caption) {
        return platformPost.platform_caption;
    }
    return props.post.main_caption || '';
};

// Normalize media
const normalizedMedia = computed(() => {
    return (props.post.media || []).map(item => ({
        ...item,
        display_url: item.thumbnail_url || item.url,
        is_image: item.type === 'image',
        is_video: item.type === 'video',
    }));
});

// Get hashtags for Instagram
const instagramHashtags = computed(() => {
    const platformPost = getPlatformPost('instagram');
    return platformPost?.hashtags || [];
});

// Get YouTube specific data
const youtubeData = computed(() => {
    const platformPost = getPlatformPost('youtube');
    return {
        title: platformPost?.video_title || props.post.title,
        description: platformPost?.video_description || props.post.main_caption,
    };
});

// First media URL for YouTube thumbnail
const firstMediaUrl = computed(() => {
    const first = props.post.media?.[0];
    if (!first) return null;
    return first.thumbnail_url || first.url;
});

// Touch/drag handling
const cardRef = ref(null);
const startX = ref(0);
const currentX = ref(0);
const isDragging = ref(false);

const dragOffset = computed(() => {
    if (!isDragging.value) return 0;
    return currentX.value - startX.value;
});

const rotation = computed(() => {
    return dragOffset.value * 0.03;
});

const swipeIndicator = computed(() => {
    if (dragOffset.value > 80) return 'approve';
    if (dragOffset.value < -80) return 'reject';
    return null;
});

const cardStyle = computed(() => {
    if (props.swipeDirection) {
        const translateX = props.swipeDirection === 'right' ? '150%' : '-150%';
        const rotate = props.swipeDirection === 'right' ? '20deg' : '-20deg';
        return {
            transform: `translateX(${translateX}) rotate(${rotate})`,
            opacity: 0,
            transition: 'all 0.3s ease-out',
        };
    }

    if (isDragging.value) {
        return {
            transform: `translateX(${dragOffset.value}px) rotate(${rotation.value}deg)`,
            transition: 'none',
        };
    }

    return {
        transform: 'translateX(0) rotate(0)',
        transition: 'transform 0.3s ease-out',
    };
});

// Mouse handlers
const handleMouseDown = (e) => {
    if (props.isAnimating) return;
    startX.value = e.clientX;
    currentX.value = startX.value;
    isDragging.value = true;
    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
};

const handleMouseMove = (e) => {
    if (!isDragging.value) return;
    currentX.value = e.clientX;
};

const handleMouseUp = () => {
    if (!isDragging.value) return;

    const threshold = 120;
    if (dragOffset.value > threshold) {
        emit('swipe', 'right');
    } else if (dragOffset.value < -threshold) {
        emit('swipe', 'left');
    }

    isDragging.value = false;
    currentX.value = 0;
    startX.value = 0;
    document.removeEventListener('mousemove', handleMouseMove);
    document.removeEventListener('mouseup', handleMouseUp);
};

// Touch handlers
const handleTouchStart = (e) => {
    if (props.isAnimating) return;
    startX.value = e.touches[0].clientX;
    currentX.value = startX.value;
    isDragging.value = true;
};

const handleTouchMove = (e) => {
    if (!isDragging.value) return;
    currentX.value = e.touches[0].clientX;
};

const handleTouchEnd = () => {
    if (!isDragging.value) return;

    const threshold = 120;
    if (dragOffset.value > threshold) {
        emit('swipe', 'right');
    } else if (dragOffset.value < -threshold) {
        emit('swipe', 'left');
    }

    isDragging.value = false;
    currentX.value = 0;
    startX.value = 0;
};

onUnmounted(() => {
    document.removeEventListener('mousemove', handleMouseMove);
    document.removeEventListener('mouseup', handleMouseUp);
});

// Format date
const formatDate = (date) => {
    if (!date) return t('verification.notScheduled');
    return new Date(date).toLocaleDateString('pl-PL', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <div
        ref="cardRef"
        class="relative bg-gray-800 rounded-2xl shadow-2xl overflow-hidden cursor-grab active:cursor-grabbing select-none"
        :style="cardStyle"
        @mousedown="handleMouseDown"
        @touchstart="handleTouchStart"
        @touchmove="handleTouchMove"
        @touchend="handleTouchEnd"
    >
        <!-- Swipe indicators -->
        <transition name="fade">
            <div
                v-if="swipeIndicator === 'approve'"
                class="absolute top-6 left-6 z-20 px-4 py-2 bg-green-500 text-white font-bold text-xl rounded-lg transform -rotate-12 border-4 border-green-400"
            >
                {{ t('verification.approve') }}
            </div>
        </transition>
        <transition name="fade">
            <div
                v-if="swipeIndicator === 'reject'"
                class="absolute top-6 right-6 z-20 px-4 py-2 bg-red-500 text-white font-bold text-xl rounded-lg transform rotate-12 border-4 border-red-400"
            >
                {{ t('verification.reject') }}
            </div>
        </transition>

        <!-- Header with title and meta -->
        <div class="bg-gray-900 px-4 py-3 border-b border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-white font-semibold truncate flex-1 mr-4">
                    {{ post.title }}
                </h3>
                <span
                    class="px-2 py-1 rounded text-xs font-medium shrink-0"
                    :class="{
                        'bg-gray-700 text-gray-300': post.status === 'draft',
                        'bg-yellow-900/50 text-yellow-400': post.status === 'pending_approval',
                        'bg-blue-900/50 text-blue-400': post.status === 'approved',
                    }"
                >
                    {{ post.status_label }}
                </span>
            </div>
            <div class="flex items-center justify-between mt-2 text-xs text-gray-500">
                <div class="flex items-center space-x-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>{{ formatDate(post.scheduled_at) }}</span>
                </div>
                <div class="flex items-center space-x-1">
                    <span
                        v-for="platform in enabledPlatforms"
                        :key="platform.id"
                        class="w-5 h-5 rounded flex items-center justify-center text-white text-xs font-bold"
                        :class="platform.bgColor"
                    >
                        {{ platform.icon }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Platform tabs -->
        <div class="bg-gray-850 border-b border-gray-700">
            <div class="flex">
                <button
                    v-for="platform in enabledPlatforms"
                    :key="platform.id"
                    @click.stop="activePlatform = platform.id"
                    class="flex-1 px-3 py-2 text-xs font-medium transition-colors border-b-2"
                    :class="activePlatform === platform.id
                        ? 'border-white text-white bg-gray-700/50'
                        : 'border-transparent text-gray-500 hover:text-gray-300'"
                >
                    <span
                        class="inline-flex items-center justify-center w-4 h-4 rounded text-white text-[10px] font-bold mr-1.5"
                        :class="platform.bgColor"
                    >
                        {{ platform.icon }}
                    </span>
                    {{ platform.label }}
                </button>
            </div>
        </div>

        <!-- Preview content -->
        <div class="bg-gray-100 p-4 max-h-[65vh] overflow-y-auto">
            <FacebookPreview
                v-if="activePlatform === 'facebook'"
                :caption="getEffectiveCaption('facebook')"
                :media="normalizedMedia"
                :link-preview="getPlatformPost('facebook')?.link_preview"
            />

            <InstagramPreview
                v-else-if="activePlatform === 'instagram'"
                :caption="getEffectiveCaption('instagram')"
                :media="normalizedMedia"
                :hashtags="instagramHashtags"
            />

            <YouTubePreview
                v-else-if="activePlatform === 'youtube'"
                :title="youtubeData.title"
                :description="youtubeData.description"
                :thumbnail="firstMediaUrl"
                :media="normalizedMedia"
            />
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.bg-gray-850 {
    background-color: rgb(30, 30, 35);
}
</style>
