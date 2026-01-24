<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useStockPhotosStore } from '@/stores/stockPhotos';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    suggestedKeywords: {
        type: Array,
        default: () => [],
    },
    initialSearch: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close', 'select']);

const { t } = useI18n();
const stockPhotosStore = useStockPhotosStore();

const searchQuery = ref('');
const selectedPhoto = ref(null);
const searchMode = ref('featured'); // 'featured' or 'search'

const photos = computed(() => stockPhotosStore.photos);
const loading = computed(() => stockPhotosStore.loading);
const error = computed(() => stockPhotosStore.error);

const loadFeatured = async () => {
    searchMode.value = 'featured';
    await stockPhotosStore.featured();
};

const searchPhotos = async () => {
    if (!searchQuery.value.trim()) {
        return;
    }
    searchMode.value = 'search';
    const keywords = searchQuery.value.split(/[,\s]+/).filter(Boolean);
    await stockPhotosStore.search(keywords);
};

const handleKeywordClick = (keyword) => {
    searchQuery.value = keyword;
    searchPhotos();
};

const handleSearch = (event) => {
    if (event.key === 'Enter') {
        searchPhotos();
    }
};

const selectPhoto = (photo) => {
    selectedPhoto.value = photo;
};

const confirmSelection = () => {
    if (selectedPhoto.value) {
        emit('select', selectedPhoto.value);
    }
};

const getSourceLabel = (source) => {
    return source.charAt(0).toUpperCase() + source.slice(1);
};

onMounted(async () => {
    if (props.suggestedKeywords.length > 0 && props.initialSearch) {
        searchQuery.value = props.suggestedKeywords.join(' ');
        await searchPhotos();
    } else {
        await loadFeatured();
    }
});

watch(() => props.suggestedKeywords, (newKeywords) => {
    if (newKeywords.length > 0 && props.initialSearch) {
        searchQuery.value = newKeywords.join(' ');
        searchPhotos();
    }
});
</script>

<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="emit('close')">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl mx-4 max-h-[90vh] flex flex-col">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">
                        {{ t('stockPhotos.title') }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ t('stockPhotos.powered') }}
                    </p>
                </div>
                <button @click="emit('close')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Search Bar -->
            <div class="px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="flex-1 relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('stockPhotos.searchPlaceholder')"
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            @keydown="handleSearch"
                        />
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <Button @click="searchPhotos" :disabled="!searchQuery.trim()">
                        {{ t('stockPhotos.search') }}
                    </Button>
                </div>

                <!-- Suggested Keywords -->
                <div v-if="suggestedKeywords.length > 0" class="mt-3">
                    <span class="text-sm text-gray-500 mr-2">{{ t('stockPhotos.suggestedKeywords') }}:</span>
                    <button
                        v-for="keyword in suggestedKeywords"
                        :key="keyword"
                        @click="handleKeywordClick(keyword)"
                        class="inline-flex items-center px-2.5 py-1 mr-2 mb-1 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-full transition-colors"
                    >
                        {{ keyword }}
                    </button>
                </div>

                <!-- Search Tip -->
                <p v-else class="mt-2 text-sm text-gray-400">
                    {{ t('stockPhotos.searchTip') }}
                </p>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6">
                <!-- Loading State -->
                <div v-if="loading" class="flex items-center justify-center py-16">
                    <LoadingSpinner size="lg" />
                    <span class="ml-3 text-gray-500">{{ t('stockPhotos.loading') }}</span>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="text-center py-16">
                    <svg class="w-16 h-16 mx-auto text-red-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p class="text-gray-700 font-medium">{{ error }}</p>
                    <Button variant="secondary" class="mt-4" @click="loadFeatured">
                        {{ t('common.tryAgain') }}
                    </Button>
                </div>

                <!-- No Results -->
                <div v-else-if="photos.length === 0" class="text-center py-16">
                    <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-gray-700 font-medium">{{ t('stockPhotos.noResults') }}</p>
                    <p class="text-gray-500 text-sm mt-1">{{ t('stockPhotos.noResultsHint') }}</p>
                </div>

                <!-- Photo Grid -->
                <div v-else>
                    <p class="text-sm text-gray-500 mb-4">
                        {{ searchMode === 'featured' ? t('stockPhotos.featured') : t('stockPhotos.searchResults') }}
                        <span class="ml-1 text-xs bg-gray-100 px-2 py-0.5 rounded-full">
                            {{ photos.length }}
                        </span>
                    </p>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <button
                            v-for="photo in photos"
                            :key="`${photo.source}-${photo.id}`"
                            @click="selectPhoto(photo)"
                            class="group relative aspect-video rounded-lg overflow-hidden border-2 transition-all focus:outline-none"
                            :class="selectedPhoto?.id === photo.id && selectedPhoto?.source === photo.source
                                ? 'border-blue-500 ring-2 ring-blue-200'
                                : 'border-gray-200 hover:border-gray-300'"
                        >
                            <!-- Photo Image -->
                            <img
                                :src="photo.thumbnail || photo.url"
                                :alt="photo.description || 'Stock photo'"
                                class="w-full h-full object-cover"
                                loading="lazy"
                            />

                            <!-- Color Overlay (before image loads) -->
                            <div
                                class="absolute inset-0 -z-10"
                                :style="{ backgroundColor: photo.color || '#e5e7eb' }"
                            />

                            <!-- Selected Indicator -->
                            <div
                                v-if="selectedPhoto?.id === photo.id && selectedPhoto?.source === photo.source"
                                class="absolute top-2 right-2 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center"
                            >
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>

                            <!-- Source Badge -->
                            <div class="absolute top-2 left-2">
                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-black/50 text-white rounded">
                                    {{ getSourceLabel(photo.source) }}
                                </span>
                            </div>

                            <!-- Hover Overlay -->
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                <p class="text-white text-sm font-medium truncate">
                                    {{ photo.author?.name || 'Unknown' }}
                                </p>
                                <p v-if="photo.description" class="text-white/70 text-xs truncate">
                                    {{ photo.description }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50 rounded-b-xl">
                <div v-if="selectedPhoto" class="flex items-center text-sm text-gray-600">
                    <img
                        :src="selectedPhoto.thumbnail"
                        :alt="selectedPhoto.description"
                        class="w-10 h-10 rounded object-cover mr-3"
                    />
                    <div>
                        <p class="font-medium">{{ t('stockPhotos.selected') }}</p>
                        <p class="text-xs text-gray-500">
                            {{ t('stockPhotos.attribution', { author: selectedPhoto.author?.name || 'Unknown' }) }}
                            <a
                                v-if="selectedPhoto.attribution_url"
                                :href="selectedPhoto.attribution_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-blue-500 hover:underline ml-1"
                                @click.stop
                            >
                                {{ t('stockPhotos.viewOnSource', { source: getSourceLabel(selectedPhoto.source) }) }}
                            </a>
                        </p>
                    </div>
                </div>
                <div v-else></div>

                <div class="flex items-center space-x-3">
                    <Button variant="secondary" @click="emit('close')">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        :disabled="!selectedPhoto"
                        @click="confirmSelection"
                    >
                        {{ t('stockPhotos.usePhoto') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
