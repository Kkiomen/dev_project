<script setup>
import { computed } from 'vue';

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
});

const truncatedDescription = computed(() => {
    if (!props.description) return '';
    return props.description.length > 200
        ? props.description.substring(0, 200) + '...'
        : props.description;
});
</script>

<template>
    <div class="max-w-lg mx-auto">
        <!-- YouTube video mockup -->
        <div class="bg-white">
            <!-- Thumbnail -->
            <div class="aspect-video bg-gray-900 rounded-lg overflow-hidden relative">
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

                <!-- Play button overlay -->
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center shadow-lg">
                        <svg class="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                </div>

                <!-- Duration -->
                <div class="absolute bottom-2 right-2 bg-black bg-opacity-80 text-white text-xs px-1.5 py-0.5 rounded">
                    10:30
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
