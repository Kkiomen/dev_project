<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import FacebookPreview from './FacebookPreview.vue';
import InstagramPreview from './InstagramPreview.vue';
import YouTubePreview from './YouTubePreview.vue';

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
    caption: {
        type: String,
        default: '',
    },
    media: {
        type: Array,
        default: () => [],
    },
    platformPosts: {
        type: Array,
        default: () => [],
    },
    activePlatform: {
        type: String,
        default: null,
    },
    selectedPlatforms: {
        type: Array,
        default: () => ['facebook', 'instagram', 'youtube'],
    },
    hashtags: {
        type: Array,
        default: () => [],
    },
    videoTitle: {
        type: String,
        default: '',
    },
});

const { t } = useI18n();

const activeTab = ref(props.activePlatform || props.selectedPlatforms[0] || 'facebook');

// Sync activeTab with activePlatform prop
watch(() => props.activePlatform, (newVal) => {
    if (newVal && props.selectedPlatforms.includes(newVal)) {
        activeTab.value = newVal;
    }
});

const allTabs = [
    { id: 'facebook', label: 'Facebook', icon: 'F', color: 'blue' },
    { id: 'instagram', label: 'Instagram', icon: 'I', color: 'pink' },
    { id: 'youtube', label: 'YouTube', icon: 'Y', color: 'red' },
];

// Filter tabs based on selected platforms
const tabs = computed(() => {
    return allTabs.filter(tab => props.selectedPlatforms.includes(tab.id));
});

// Normalize media to handle both server and staged media
const normalizedMedia = computed(() => {
    return props.media.map(item => ({
        ...item,
        // Use preview_url for staged media, url for server media
        display_url: item.preview_url || item.thumbnail_url || item.url,
    }));
});

const getPlatformPost = (platform) => {
    return props.platformPosts.find(p => p.platform === platform);
};

const getEffectiveCaption = (platform) => {
    const platformPost = getPlatformPost(platform);
    if (platformPost?.platform_caption) {
        return platformPost.platform_caption;
    }
    return props.caption;
};

const firstMediaUrl = computed(() => {
    const first = props.media[0];
    if (!first) return null;
    return first.preview_url || first.thumbnail_url || first.url;
});
</script>

<template>
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <!-- Tabs -->
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    class="flex-1 px-4 py-3 text-sm font-medium text-center border-b-2 transition-colors"
                    :class="activeTab === tab.id
                        ? {
                            'border-blue-500 text-blue-600': tab.color === 'blue',
                            'border-pink-500 text-pink-600': tab.color === 'pink',
                            'border-red-500 text-red-600': tab.color === 'red',
                        }[`border-${tab.color}-500 text-${tab.color}-600`]
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                >
                    <span
                        class="inline-flex items-center justify-center w-5 h-5 rounded-full text-white text-xs mr-2"
                        :class="{
                            'bg-blue-600': tab.id === 'facebook',
                            'bg-pink-500': tab.id === 'instagram',
                            'bg-red-600': tab.id === 'youtube',
                        }"
                    >
                        {{ tab.icon }}
                    </span>
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <!-- Preview content -->
        <div class="p-6">
            <FacebookPreview
                v-if="activeTab === 'facebook'"
                :caption="getEffectiveCaption('facebook')"
                :media="normalizedMedia"
                :link-preview="getPlatformPost('facebook')?.link_preview"
            />

            <InstagramPreview
                v-else-if="activeTab === 'instagram'"
                :caption="getEffectiveCaption('instagram')"
                :media="normalizedMedia"
                :hashtags="hashtags.length ? hashtags : (getPlatformPost('instagram')?.hashtags || [])"
            />

            <YouTubePreview
                v-else-if="activeTab === 'youtube'"
                :title="videoTitle || getPlatformPost('youtube')?.video_title || title"
                :description="getPlatformPost('youtube')?.video_description || caption"
                :thumbnail="firstMediaUrl"
            />
        </div>
    </div>
</template>
