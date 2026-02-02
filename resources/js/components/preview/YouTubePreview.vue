<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    thumbnail: {
        type: String,
        default: null,
    },
    media: {
        type: Array,
        default: () => [],
    },
});

const currentIndex = ref(0);
const hasMedia = computed(() => props.media.length > 0 || props.thumbnail);
const mediaCount = computed(() => props.media.length);
const isCarousel = computed(() => props.media.length > 1);

const getMediaUrl = (item) => {
    return item.preview_url || item.display_url || item.url || item.thumbnail_url;
};

const currentThumbnail = computed(() => {
    if (props.media.length > 0) {
        return getMediaUrl(props.media[currentIndex.value]);
    }
    return props.thumbnail;
});

const truncatedDescription = computed(() => {
    if (!props.description) return '';
    return props.description.length > 200
        ? props.description.substring(0, 200) + '...'
        : props.description;
});

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

const goToSlide = (index) => {
    currentIndex.value = index;
};

// Reset carousel index when media changes
watch(() => props.media.length, (newLength) => {
    if (currentIndex.value >= newLength) {
        currentIndex.value = Math.max(0, newLength - 1);
    }
});
</script>

<template>
    <div class="max-w-lg mx-auto">
        <!-- YouTube video mockup -->
        <div class="bg-white">
            <!-- Thumbnail with carousel -->
            <div class="aspect-video bg-gray-900 rounded-lg overflow-hidden relative">
                <!-- Carousel slides -->
                <div
                    v-if="media.length > 0"
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
                            :alt="title"
                            class="w-full h-full object-cover"
                        />
                        <video
                            v-else-if="item.is_video || item.type === 'video'"
                            :src="getMediaUrl(item)"
                            class="w-full h-full object-cover"
                        />
                    </div>
                </div>

                <!-- Single thumbnail fallback -->
                <template v-else>
                    <img
                        v-if="thumbnail"
                        :src="thumbnail"
                        :alt="title"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <svg class="w-20 h-20 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                </template>

                <!-- Play button overlay -->
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center shadow-lg">
                        <svg class="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                </div>

                <!-- Navigation arrows (only show for carousel) -->
                <template v-if="isCarousel">
                    <!-- Previous button -->
                    <button
                        v-if="currentIndex > 0"
                        @click="prevSlide"
                        class="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 bg-white/90 rounded-full flex items-center justify-center shadow-lg hover:bg-white transition-colors z-10"
                    >
                        <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>

                    <!-- Next button -->
                    <button
                        v-if="currentIndex < mediaCount - 1"
                        @click="nextSlide"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 bg-white/90 rounded-full flex items-center justify-center shadow-lg hover:bg-white transition-colors z-10"
                    >
                        <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <!-- Slide counter badge -->
                    <div class="absolute top-2 right-2 bg-black/70 text-white text-xs px-2 py-1 rounded-full z-10">
                        {{ currentIndex + 1 }}/{{ mediaCount }}
                    </div>
                </template>

                <!-- Duration -->
                <div class="absolute bottom-2 right-2 bg-black bg-opacity-80 text-white text-xs px-1.5 py-0.5 rounded z-10">
                    10:30
                </div>
            </div>

            <!-- Dots indicator (centered below thumbnail) -->
            <div v-if="isCarousel" class="flex justify-center mt-2">
                <div class="flex items-center space-x-1">
                    <button
                        v-for="(_, index) in media"
                        :key="index"
                        @click="goToSlide(index)"
                        class="w-1.5 h-1.5 rounded-full transition-colors"
                        :class="index === currentIndex ? 'bg-red-600' : 'bg-gray-300'"
                    />
                </div>
            </div>

            <!-- Video info -->
            <div class="mt-3 flex space-x-3">
                <!-- Channel avatar -->
                <div class="w-9 h-9 rounded-full bg-gray-300 flex-shrink-0"></div>

                <!-- Video details -->
                <div class="flex-1 min-w-0">
                    <h3 class="font-medium text-sm text-gray-900 line-clamp-2">
                        {{ title || 'Video Title' }}
                    </h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Your Channel • 0 views • Just now
                    </p>
                </div>

                <!-- More button -->
                <button class="flex-shrink-0 text-gray-500">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="6" r="1.5"/>
                        <circle cx="12" cy="12" r="1.5"/>
                        <circle cx="12" cy="18" r="1.5"/>
                    </svg>
                </button>
            </div>

            <!-- Description preview -->
            <div v-if="description" class="mt-4 p-3 bg-gray-100 rounded-lg">
                <div class="flex items-center space-x-4 text-sm text-gray-600 mb-2">
                    <span>0 views</span>
                    <span>Just now</span>
                </div>
                <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ truncatedDescription }}</p>
                <button v-if="description.length > 200" class="text-sm text-gray-500 mt-1">
                    ...more
                </button>
            </div>
        </div>
    </div>
</template>
