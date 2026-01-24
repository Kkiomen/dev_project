<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    caption: {
        type: String,
        default: '',
    },
    media: {
        type: Array,
        default: () => [],
    },
    linkPreview: {
        type: Object,
        default: null,
    },
});

const imageMedia = computed(() => props.media.filter(m => m.is_image || m.type === 'image'));
const videoMedia = computed(() => props.media.filter(m => m.is_video || m.type === 'video'));
const hasMedia = computed(() => props.media.length > 0);
const mediaCount = computed(() => props.media.length);

// For grid layout calculation
const gridLayout = computed(() => {
    const count = imageMedia.value.length;
    if (count === 1) return 'single';
    if (count === 2) return 'two';
    if (count === 3) return 'three';
    if (count === 4) return 'four';
    return 'five-plus';
});

const getMediaUrl = (item) => {
    return item.preview_url || item.display_url || item.url || item.thumbnail_url;
};
</script>

<template>
    <div class="max-w-md mx-auto">
        <!-- Facebook post mockup -->
        <div class="bg-white rounded-lg border border-gray-300 shadow-sm">
            <!-- Header -->
            <div class="p-3 flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-gray-300"></div>
                <div>
                    <p class="font-semibold text-sm text-gray-900">Your Page Name</p>
                    <p class="text-xs text-gray-500">Just now · 🌐</p>
                </div>
            </div>

            <!-- Caption -->
            <div v-if="caption" class="px-3 pb-3">
                <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ caption }}</p>
            </div>

            <!-- Media Grid -->
            <div v-if="hasMedia" class="border-t border-b border-gray-200">
                <!-- Single image -->
                <template v-if="gridLayout === 'single'">
                    <div class="relative">
                        <img
                            v-if="media[0].is_image || media[0].type === 'image'"
                            :src="getMediaUrl(media[0])"
                            :alt="media[0].filename"
                            class="w-full max-h-[500px] object-cover"
                        />
                        <video
                            v-else
                            :src="getMediaUrl(media[0])"
                            controls
                            class="w-full max-h-[500px]"
                        />
                    </div>
                </template>

                <!-- Two images - side by side -->
                <template v-else-if="gridLayout === 'two'">
                    <div class="grid grid-cols-2 gap-0.5">
                        <div
                            v-for="(item, index) in media.slice(0, 2)"
                            :key="index"
                            class="aspect-square"
                        >
                            <img
                                v-if="item.is_image || item.type === 'image'"
                                :src="getMediaUrl(item)"
                                :alt="item.filename"
                                class="w-full h-full object-cover"
                            />
                            <video
                                v-else
                                :src="getMediaUrl(item)"
                                class="w-full h-full object-cover"
                            />
                        </div>
                    </div>
                </template>

                <!-- Three images - one big, two small -->
                <template v-else-if="gridLayout === 'three'">
                    <div class="grid grid-cols-2 gap-0.5">
                        <div class="row-span-2 aspect-square">
                            <img
                                v-if="media[0].is_image || media[0].type === 'image'"
                                :src="getMediaUrl(media[0])"
                                :alt="media[0].filename"
                                class="w-full h-full object-cover"
                            />
                            <video
                                v-else
                                :src="getMediaUrl(media[0])"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <div
                            v-for="(item, index) in media.slice(1, 3)"
                            :key="index"
                            class="aspect-square"
                        >
                            <img
                                v-if="item.is_image || item.type === 'image'"
                                :src="getMediaUrl(item)"
                                :alt="item.filename"
                                class="w-full h-full object-cover"
                            />
                            <video
                                v-else
                                :src="getMediaUrl(item)"
                                class="w-full h-full object-cover"
                            />
                        </div>
                    </div>
                </template>

                <!-- Four images - 2x2 grid -->
                <template v-else-if="gridLayout === 'four'">
                    <div class="grid grid-cols-2 gap-0.5">
                        <div
                            v-for="(item, index) in media.slice(0, 4)"
                            :key="index"
                            class="aspect-square"
                        >
                            <img
                                v-if="item.is_image || item.type === 'image'"
                                :src="getMediaUrl(item)"
                                :alt="item.filename"
                                class="w-full h-full object-cover"
                            />
                            <video
                                v-else
                                :src="getMediaUrl(item)"
                                class="w-full h-full object-cover"
                            />
                        </div>
                    </div>
                </template>

                <!-- Five or more images -->
                <template v-else>
                    <div class="grid grid-cols-2 gap-0.5">
                        <div class="row-span-2">
                            <img
                                v-if="media[0].is_image || media[0].type === 'image'"
                                :src="getMediaUrl(media[0])"
                                :alt="media[0].filename"
                                class="w-full h-full object-cover"
                            />
                            <video
                                v-else
                                :src="getMediaUrl(media[0])"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <div class="aspect-square">
                            <img
                                v-if="media[1].is_image || media[1].type === 'image'"
                                :src="getMediaUrl(media[1])"
                                :alt="media[1].filename"
                                class="w-full h-full object-cover"
                            />
                            <video
                                v-else
                                :src="getMediaUrl(media[1])"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <div class="aspect-square relative">
                            <img
                                v-if="media[2].is_image || media[2].type === 'image'"
                                :src="getMediaUrl(media[2])"
                                :alt="media[2].filename"
                                class="w-full h-full object-cover"
                            />
                            <video
                                v-else
                                :src="getMediaUrl(media[2])"
                                class="w-full h-full object-cover"
                            />
                            <!-- Overlay with remaining count -->
                            <div
                                v-if="mediaCount > 3"
                                class="absolute inset-0 bg-black/50 flex items-center justify-center"
                            >
                                <span class="text-white text-2xl font-semibold">
                                    +{{ mediaCount - 3 }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Link Preview (only if no media) -->
            <div v-else-if="linkPreview" class="border-t border-gray-200">
                <div v-if="linkPreview.image" class="bg-gray-100">
                    <img
                        :src="linkPreview.image"
                        :alt="linkPreview.title"
                        class="w-full h-48 object-cover"
                    />
                </div>
                <div class="p-3 bg-gray-50">
                    <p class="text-xs text-gray-500 uppercase">{{ linkPreview.site_name || linkPreview.url }}</p>
                    <p class="font-semibold text-sm text-gray-900 mt-1">{{ linkPreview.title }}</p>
                    <p v-if="linkPreview.description" class="text-xs text-gray-500 mt-1 line-clamp-2">
                        {{ linkPreview.description }}
                    </p>
                </div>
            </div>

            <!-- Media counter badge -->
            <div v-if="mediaCount > 1" class="px-3 py-2 text-xs text-gray-500 border-t border-gray-100">
                {{ mediaCount }} {{ mediaCount === 1 ? 'photo' : 'photos' }}
            </div>

            <!-- Actions -->
            <div class="p-3 flex items-center justify-between border-t border-gray-200">
                <button class="flex items-center space-x-1 text-gray-500 hover:text-blue-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/>
                    </svg>
                    <span class="text-sm">Like</span>
                </button>
                <button class="flex items-center space-x-1 text-gray-500 hover:text-blue-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <span class="text-sm">Comment</span>
                </button>
                <button class="flex items-center space-x-1 text-gray-500 hover:text-blue-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"/>
                    </svg>
                    <span class="text-sm">Share</span>
                </button>
            </div>
        </div>
    </div>
</template>
