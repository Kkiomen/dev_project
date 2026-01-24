<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    caption: {
        type: String,
        default: '',
    },
    media: {
        type: Array,
        default: () => [],
    },
    hashtags: {
        type: Array,
        default: () => [],
    },
});

const currentIndex = ref(0);
const hasMedia = computed(() => props.media.length > 0);
const mediaCount = computed(() => props.media.length);
const isCarousel = computed(() => props.media.length > 1);

const currentMedia = computed(() => props.media[currentIndex.value] || null);

// Format hashtags with # prefix
const formattedHashtags = computed(() => {
    return props.hashtags.map(h => h.startsWith('#') ? h : `#${h}`);
});

const getMediaUrl = (item) => {
    return item.preview_url || item.display_url || item.url || item.thumbnail_url;
};

const goToSlide = (index) => {
    currentIndex.value = index;
};

const prevSlide = () => {
    if (currentIndex.value > 0) {
        currentIndex.value--;
    }
};

const nextSlide = () => {
    if (currentIndex.value < props.media.length - 1) {
        currentIndex.value++;
    }
};

// Touch/swipe handling
const touchStartX = ref(0);
const touchEndX = ref(0);

const handleTouchStart = (e) => {
    touchStartX.value = e.touches[0].clientX;
};

const handleTouchMove = (e) => {
    touchEndX.value = e.touches[0].clientX;
};

const handleTouchEnd = () => {
    const diff = touchStartX.value - touchEndX.value;
    const threshold = 50;

    if (diff > threshold) {
        nextSlide();
    } else if (diff < -threshold) {
        prevSlide();
    }
};

// Reset carousel index when media changes
watch(() => props.media.length, (newLength) => {
    if (currentIndex.value >= newLength) {
        currentIndex.value = Math.max(0, newLength - 1);
    }
});
</script>

<template>
    <div class="max-w-md mx-auto">
        <!-- Instagram post mockup -->
        <div class="bg-white border border-gray-200 rounded-lg">
            <!-- Header -->
            <div class="p-3 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-yellow-400 via-pink-500 to-purple-600 p-0.5">
                        <div class="w-full h-full rounded-full bg-white p-0.5">
                            <div class="w-full h-full rounded-full bg-gray-300"></div>
                        </div>
                    </div>
                    <span class="font-semibold text-sm">yourusername</span>
                </div>
                <button class="text-gray-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="1.5"/>
                        <circle cx="6" cy="12" r="1.5"/>
                        <circle cx="18" cy="12" r="1.5"/>
                    </svg>
                </button>
            </div>

            <!-- Media Carousel -->
            <div
                class="aspect-square bg-gray-100 relative overflow-hidden"
                @touchstart="handleTouchStart"
                @touchmove="handleTouchMove"
                @touchend="handleTouchEnd"
            >
                <!-- Carousel slides -->
                <div
                    class="flex transition-transform duration-300 ease-out h-full"
                    :style="{ transform: `translateX(-${currentIndex * 100}%)` }"
                >
                    <div
                        v-for="(item, index) in media"
                        :key="index"
                        class="w-full h-full flex-shrink-0"
                    >
                        <img
                            v-if="item.is_image || item.type === 'image'"
                            :src="getMediaUrl(item)"
                            :alt="item.filename"
                            class="w-full h-full object-cover"
                        />
                        <video
                            v-else-if="item.is_video || item.type === 'video'"
                            :src="getMediaUrl(item)"
                            class="w-full h-full object-cover"
                        />
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="!hasMedia" class="absolute inset-0 flex items-center justify-center text-gray-400">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>

                <!-- Navigation arrows (only show for carousel) -->
                <template v-if="isCarousel">
                    <!-- Previous button -->
                    <button
                        v-if="currentIndex > 0"
                        @click="prevSlide"
                        class="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 bg-white/90 rounded-full flex items-center justify-center shadow-lg hover:bg-white transition-colors"
                    >
                        <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>

                    <!-- Next button -->
                    <button
                        v-if="currentIndex < mediaCount - 1"
                        @click="nextSlide"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 bg-white/90 rounded-full flex items-center justify-center shadow-lg hover:bg-white transition-colors"
                    >
                        <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <!-- Slide counter badge -->
                    <div class="absolute top-3 right-3 bg-black/70 text-white text-xs px-2 py-1 rounded-full">
                        {{ currentIndex + 1 }}/{{ mediaCount }}
                    </div>
                </template>
            </div>

            <!-- Actions -->
            <div class="p-3">
                <!-- Dots indicator (centered below image) -->
                <div v-if="isCarousel" class="flex justify-center mb-3">
                    <div class="flex items-center space-x-1">
                        <button
                            v-for="(_, index) in media"
                            :key="index"
                            @click="goToSlide(index)"
                            class="w-1.5 h-1.5 rounded-full transition-colors"
                            :class="index === currentIndex ? 'bg-blue-500' : 'bg-gray-300'"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center space-x-4">
                        <button class="text-gray-700 hover:text-gray-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </button>
                        <button class="text-gray-700 hover:text-gray-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </button>
                        <button class="text-gray-700 hover:text-gray-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"/>
                            </svg>
                        </button>
                    </div>

                    <button class="text-gray-700 hover:text-gray-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                        </svg>
                    </button>
                </div>

                <!-- Likes -->
                <p class="font-semibold text-sm mb-1">1,234 likes</p>

                <!-- Caption -->
                <div class="text-sm">
                    <span class="font-semibold">yourusername</span>
                    <span class="whitespace-pre-wrap"> {{ caption }}</span>
                    <!-- Hashtags -->
                    <div v-if="formattedHashtags.length" class="mt-2">
                        <span
                            v-for="(tag, index) in formattedHashtags"
                            :key="index"
                            class="text-blue-900 hover:text-blue-600 cursor-pointer"
                        >{{ tag }} </span>
                    </div>
                </div>

                <!-- Time -->
                <p class="text-xs text-gray-400 mt-2 uppercase">Just now</p>
            </div>
        </div>
    </div>
</template>
