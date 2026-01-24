<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePostsStore } from '@/stores/posts';
import RichTextEditor from './RichTextEditor.vue';
import Button from '@/components/common/Button.vue';
import AiPlatformGenerateModal from './AiPlatformGenerateModal.vue';

// Unicode bold/italic character maps for stripping
const unicodeToNormal = {
    // Bold
    '𝗔': 'A', '𝗕': 'B', '𝗖': 'C', '𝗗': 'D', '𝗘': 'E', '𝗙': 'F', '𝗚': 'G', '𝗛': 'H', '𝗜': 'I', '𝗝': 'J',
    '𝗞': 'K', '𝗟': 'L', '𝗠': 'M', '𝗡': 'N', '𝗢': 'O', '𝗣': 'P', '𝗤': 'Q', '𝗥': 'R', '𝗦': 'S', '𝗧': 'T',
    '𝗨': 'U', '𝗩': 'V', '𝗪': 'W', '𝗫': 'X', '𝗬': 'Y', '𝗭': 'Z',
    '𝗮': 'a', '𝗯': 'b', '𝗰': 'c', '𝗱': 'd', '𝗲': 'e', '𝗳': 'f', '𝗴': 'g', '𝗵': 'h', '𝗶': 'i', '𝗷': 'j',
    '𝗸': 'k', '𝗹': 'l', '𝗺': 'm', '𝗻': 'n', '𝗼': 'o', '𝗽': 'p', '𝗾': 'q', '𝗿': 'r', '𝘀': 's', '𝘁': 't',
    '𝘂': 'u', '𝘃': 'v', '𝘄': 'w', '𝘅': 'x', '𝘆': 'y', '𝘇': 'z',
    '𝟬': '0', '𝟭': '1', '𝟮': '2', '𝟯': '3', '𝟰': '4', '𝟱': '5', '𝟲': '6', '𝟳': '7', '𝟴': '8', '𝟵': '9',
    // Italic
    '𝘈': 'A', '𝘉': 'B', '𝘊': 'C', '𝘋': 'D', '𝘌': 'E', '𝘍': 'F', '𝘎': 'G', '𝘏': 'H', '𝘐': 'I', '𝘑': 'J',
    '𝘒': 'K', '𝘓': 'L', '𝘔': 'M', '𝘕': 'N', '𝘖': 'O', '𝘗': 'P', '𝘘': 'Q', '𝘙': 'R', '𝘚': 'S', '𝘛': 'T',
    '𝘜': 'U', '𝘝': 'V', '𝘞': 'W', '𝘟': 'X', '𝘠': 'Y', '𝘡': 'Z',
    '𝘢': 'a', '𝘣': 'b', '𝘤': 'c', '𝘥': 'd', '𝘦': 'e', '𝘧': 'f', '𝘨': 'g', '𝘩': 'h', '𝘪': 'i', '𝘫': 'j',
    '𝘬': 'k', '𝘭': 'l', '𝘮': 'm', '𝘯': 'n', '𝘰': 'o', '𝘱': 'p', '𝘲': 'q', '𝘳': 'r', '𝘴': 's', '𝘵': 't',
    '𝘶': 'u', '𝘷': 'v', '𝘸': 'w', '𝘹': 'x', '𝘺': 'y', '𝘻': 'z',
};

// Strip Unicode formatting from text
const stripFormatting = (text) => {
    if (!text) return text;
    return [...text].map(char => unicodeToNormal[char] || char).join('');
};

const props = defineProps({
    postId: {
        type: String,
        required: true,
    },
    platformPosts: {
        type: Array,
        default: () => [],
    },
    mainCaption: {
        type: String,
        default: '',
    },
});

const { t } = useI18n();
const postsStore = usePostsStore();

const activeTab = ref('facebook');
const saving = ref(false);
const showAiModal = ref(null); // null or platform name

const tabs = [
    { id: 'facebook', label: 'Facebook', icon: 'F', color: 'blue' },
    { id: 'instagram', label: 'Instagram', icon: 'I', color: 'pink' },
    { id: 'youtube', label: 'YouTube', icon: 'Y', color: 'red' },
];

// Get platform post data
const getPlatformPost = (platform) => {
    return props.platformPosts.find(p => p.platform === platform) || {
        platform,
        enabled: true,
        platform_caption: null,
        hashtags: [],
        video_title: '',
        video_description: '',
        link_preview: null,
    };
};

// Local state for each platform
const platformData = ref({
    facebook: { ...getPlatformPost('facebook') },
    instagram: { ...getPlatformPost('instagram') },
    youtube: { ...getPlatformPost('youtube') },
});

// Watch for changes in platformPosts prop
watch(() => props.platformPosts, (newPosts) => {
    tabs.forEach(tab => {
        const post = newPosts.find(p => p.platform === tab.id);
        if (post) {
            platformData.value[tab.id] = { ...post };
        }
    });
}, { deep: true });

// Use main caption or override
const useMainCaption = ref({
    facebook: true,
    instagram: true,
    youtube: true,
});

// Initialize useMainCaption based on platform_caption
watch(() => props.platformPosts, (posts) => {
    posts.forEach(post => {
        useMainCaption.value[post.platform] = !post.platform_caption;
    });
}, { immediate: true });

// Get displayed caption for platform
const getDisplayCaption = (platform) => {
    if (useMainCaption.value[platform]) {
        return props.mainCaption;
    }
    return platformData.value[platform].platform_caption || '';
};

// Set custom caption
const setCustomCaption = (platform, caption) => {
    platformData.value[platform].platform_caption = caption;
};

// Save platform settings
const savePlatformSettings = async (platform) => {
    saving.value = true;
    try {
        const data = {
            enabled: platformData.value[platform].enabled,
            platform_caption: useMainCaption.value[platform] ? null : platformData.value[platform].platform_caption,
        };

        if (platform === 'youtube') {
            // Strip Unicode formatting for YouTube
            data.video_title = stripFormatting(platformData.value[platform].video_title);
            data.video_description = stripFormatting(platformData.value[platform].video_description);
        }

        if (platform === 'instagram') {
            data.hashtags = platformData.value[platform].hashtags;
        }

        if (platform === 'facebook') {
            data.link_preview = platformData.value[platform].link_preview;
        }

        await postsStore.updatePlatformPost(props.postId, platform, data);
    } catch (error) {
        console.error('Failed to save platform settings:', error);
    } finally {
        saving.value = false;
    }
};

// Sync from main caption
const syncFromMain = async (platform) => {
    try {
        // For YouTube, strip formatting when syncing
        if (platform === 'youtube') {
            const strippedCaption = stripFormatting(props.mainCaption);
            platformData.value.youtube.video_description = strippedCaption;
            // Don't call backend sync, just update locally
            return;
        }
        await postsStore.syncPlatformPost(props.postId, platform);
    } catch (error) {
        console.error('Failed to sync platform:', error);
    }
};

// Toggle platform enabled
const togglePlatform = async (platform) => {
    try {
        await postsStore.togglePlatform(props.postId, platform);
        platformData.value[platform].enabled = !platformData.value[platform].enabled;
    } catch (error) {
        console.error('Failed to toggle platform:', error);
    }
};

// Hashtag management for Instagram
const newHashtag = ref('');
const addHashtag = (platform) => {
    if (!newHashtag.value.trim()) return;

    let tag = newHashtag.value.trim();
    if (!tag.startsWith('#')) tag = '#' + tag;

    if (!platformData.value[platform].hashtags) {
        platformData.value[platform].hashtags = [];
    }

    if (!platformData.value[platform].hashtags.includes(tag)) {
        platformData.value[platform].hashtags.push(tag);
    }

    newHashtag.value = '';
};

const removeHashtag = (platform, index) => {
    platformData.value[platform].hashtags.splice(index, 1);
};

// Popular hashtags suggestions
const popularHashtags = [
    '#marketing', '#socialmedia', '#business', '#entrepreneur',
    '#motivation', '#success', '#tips', '#strategy',
];

// Handle AI generated content
const handleAiGenerated = (platform, result) => {
    if (platform === 'facebook') {
        useMainCaption.value.facebook = false;
        platformData.value.facebook.platform_caption = result.caption;
    } else if (platform === 'instagram') {
        useMainCaption.value.instagram = false;
        platformData.value.instagram.platform_caption = result.caption;
        if (result.hashtags?.length) {
            platformData.value.instagram.hashtags = result.hashtags;
        }
    } else if (platform === 'youtube') {
        if (result.title) {
            platformData.value.youtube.video_title = result.title;
        }
        if (result.description) {
            platformData.value.youtube.video_description = result.description;
        }
    }
    showAiModal.value = null;
};
</script>

<template>
    <div class="bg-white rounded-lg border border-gray-200">
        <!-- Tabs -->
        <div class="flex border-b border-gray-200">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                @click="activeTab = tab.id"
                class="flex-1 px-4 py-3 text-sm font-medium transition-colors relative"
                :class="activeTab === tab.id
                    ? 'text-gray-900 bg-gray-50'
                    : 'text-gray-500 hover:text-gray-700'"
            >
                <div class="flex items-center justify-center space-x-2">
                    <span
                        class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs"
                        :class="{
                            'bg-blue-600': tab.id === 'facebook',
                            'bg-pink-500': tab.id === 'instagram',
                            'bg-red-600': tab.id === 'youtube',
                        }"
                    >
                        {{ tab.icon }}
                    </span>
                    <span>{{ tab.label }}</span>
                    <span
                        v-if="!platformData[tab.id]?.enabled"
                        class="text-xs text-gray-400"
                    >
                        ({{ t('common.disabled') }})
                    </span>
                </div>
                <div
                    v-if="activeTab === tab.id"
                    class="absolute bottom-0 left-0 right-0 h-0.5"
                    :class="{
                        'bg-blue-600': tab.id === 'facebook',
                        'bg-pink-500': tab.id === 'instagram',
                        'bg-red-600': tab.id === 'youtube',
                    }"
                ></div>
            </button>
        </div>

        <!-- Tab content -->
        <div class="p-4">
            <!-- Facebook Settings -->
            <div v-if="activeTab === 'facebook'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="platformData.facebook.enabled"
                            @change="togglePlatform('facebook')"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span class="text-sm font-medium text-gray-700">
                            {{ t('platforms.enableFacebook') }}
                        </span>
                    </label>
                    <div class="flex items-center space-x-2">
                        <button
                            @click="showAiModal = 'facebook'"
                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all"
                        >
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                            </svg>
                            {{ t('posts.ai.generate') }}
                        </button>
                        <Button variant="ghost" size="sm" @click="syncFromMain('facebook')">
                            {{ t('platforms.syncFromMain') }}
                        </Button>
                    </div>
                </div>

                <div>
                    <label class="flex items-center space-x-2 cursor-pointer mb-2">
                        <input
                            type="checkbox"
                            v-model="useMainCaption.facebook"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span class="text-sm text-gray-600">{{ t('platforms.useMainCaption') }}</span>
                    </label>

                    <RichTextEditor
                        v-if="!useMainCaption.facebook"
                        :model-value="platformData.facebook.platform_caption || ''"
                        @update:model-value="setCustomCaption('facebook', $event)"
                        :placeholder="t('platforms.customCaptionPlaceholder')"
                        :rows="4"
                    />
                </div>

                <!-- Link Preview URL -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('facebook.linkPreviewUrl') }}
                    </label>
                    <input
                        v-model="platformData.facebook.link_preview"
                        type="url"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        :placeholder="t('facebook.linkPreviewPlaceholder')"
                    />
                    <p class="mt-1 text-xs text-gray-500">{{ t('facebook.linkPreviewHint') }}</p>
                </div>

                <div class="flex justify-end">
                    <Button :loading="saving" @click="savePlatformSettings('facebook')">
                        {{ t('common.save') }}
                    </Button>
                </div>
            </div>

            <!-- Instagram Settings -->
            <div v-if="activeTab === 'instagram'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="platformData.instagram.enabled"
                            @change="togglePlatform('instagram')"
                            class="rounded border-gray-300 text-pink-600 focus:ring-pink-500"
                        />
                        <span class="text-sm font-medium text-gray-700">
                            {{ t('platforms.enableInstagram') }}
                        </span>
                    </label>
                    <div class="flex items-center space-x-2">
                        <button
                            @click="showAiModal = 'instagram'"
                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-purple-600 via-pink-500 to-orange-400 rounded-lg hover:from-purple-700 hover:via-pink-600 hover:to-orange-500 transition-all"
                        >
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                            </svg>
                            {{ t('posts.ai.generate') }}
                        </button>
                        <Button variant="ghost" size="sm" @click="syncFromMain('instagram')">
                            {{ t('platforms.syncFromMain') }}
                        </Button>
                    </div>
                </div>

                <div>
                    <label class="flex items-center space-x-2 cursor-pointer mb-2">
                        <input
                            type="checkbox"
                            v-model="useMainCaption.instagram"
                            class="rounded border-gray-300 text-pink-600 focus:ring-pink-500"
                        />
                        <span class="text-sm text-gray-600">{{ t('platforms.useMainCaption') }}</span>
                    </label>

                    <RichTextEditor
                        v-if="!useMainCaption.instagram"
                        :model-value="platformData.instagram.platform_caption || ''"
                        @update:model-value="setCustomCaption('instagram', $event)"
                        :placeholder="t('platforms.customCaptionPlaceholder')"
                        :rows="4"
                        :max-length="2200"
                    />
                </div>

                <!-- Hashtags -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ t('instagram.hashtags') }}
                    </label>

                    <!-- Current hashtags -->
                    <div v-if="platformData.instagram.hashtags?.length" class="flex flex-wrap gap-2 mb-3">
                        <span
                            v-for="(tag, index) in platformData.instagram.hashtags"
                            :key="index"
                            class="inline-flex items-center px-2 py-1 rounded-full text-sm bg-pink-100 text-pink-700"
                        >
                            {{ tag }}
                            <button
                                @click="removeHashtag('instagram', index)"
                                class="ml-1 text-pink-500 hover:text-pink-700"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </span>
                    </div>

                    <!-- Add hashtag -->
                    <div class="flex space-x-2">
                        <input
                            v-model="newHashtag"
                            type="text"
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500"
                            :placeholder="t('instagram.addHashtag')"
                            @keyup.enter="addHashtag('instagram')"
                        />
                        <Button variant="secondary" @click="addHashtag('instagram')">
                            {{ t('common.add') }}
                        </Button>
                    </div>

                    <!-- Popular hashtags -->
                    <div class="mt-3">
                        <p class="text-xs text-gray-500 mb-2">{{ t('instagram.popularHashtags') }}:</p>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="tag in popularHashtags"
                                :key="tag"
                                @click="() => { newHashtag = tag; addHashtag('instagram'); }"
                                class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600 hover:bg-gray-200"
                            >
                                {{ tag }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button :loading="saving" @click="savePlatformSettings('instagram')">
                        {{ t('common.save') }}
                    </Button>
                </div>
            </div>

            <!-- YouTube Settings -->
            <div v-if="activeTab === 'youtube'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="platformData.youtube.enabled"
                            @change="togglePlatform('youtube')"
                            class="rounded border-gray-300 text-red-600 focus:ring-red-500"
                        />
                        <span class="text-sm font-medium text-gray-700">
                            {{ t('platforms.enableYouTube') }}
                        </span>
                    </label>
                    <div class="flex items-center space-x-2">
                        <button
                            @click="showAiModal = 'youtube'"
                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700 rounded-lg hover:from-red-700 hover:to-red-800 transition-all"
                        >
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                            </svg>
                            {{ t('posts.ai.generate') }}
                        </button>
                        <Button variant="ghost" size="sm" @click="syncFromMain('youtube')">
                            {{ t('platforms.syncFromMain') }}
                        </Button>
                    </div>
                </div>

                <!-- Video Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('youtube.videoTitle') }}
                        <span class="text-gray-400 font-normal">({{ t('youtube.titleLimit') }})</span>
                    </label>
                    <input
                        v-model="platformData.youtube.video_title"
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                        :placeholder="t('youtube.videoTitlePlaceholder')"
                        maxlength="100"
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        {{ platformData.youtube.video_title?.length || 0 }} / 100
                    </p>
                </div>

                <!-- Video Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('youtube.videoDescription') }}
                    </label>
                    <textarea
                        v-model="platformData.youtube.video_description"
                        :placeholder="t('youtube.videoDescriptionPlaceholder')"
                        rows="6"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none"
                    ></textarea>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ t('youtube.formattingNote') }}
                    </p>
                </div>

                <!-- Video URL (for linking existing video) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('youtube.videoUrl') }}
                    </label>
                    <input
                        v-model="platformData.youtube.video_url"
                        type="url"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                        :placeholder="t('youtube.videoUrlPlaceholder')"
                    />
                    <p class="mt-1 text-xs text-gray-500">{{ t('youtube.videoUrlHint') }}</p>
                </div>

                <div class="flex justify-end">
                    <Button :loading="saving" @click="savePlatformSettings('youtube')">
                        {{ t('common.save') }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- AI Generate Modal -->
        <teleport to="body">
            <AiPlatformGenerateModal
                v-if="showAiModal"
                :platform="showAiModal"
                @close="showAiModal = null"
                @generated="handleAiGenerated(showAiModal, $event)"
            />
        </teleport>
    </div>
</template>
