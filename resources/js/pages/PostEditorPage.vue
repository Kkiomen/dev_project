<script setup>
import { ref, toRef, onMounted, onUnmounted, computed, watch, reactive } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { usePostsStore } from '@/stores/posts';
import { useApprovalStore } from '@/stores/approval';
import { usePostDraft } from '@/composables/usePostDraft';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import Toast from '@/components/common/Toast.vue';
import PostMediaGallery from '@/components/posts/PostMediaGallery.vue';
import StagedMediaGallery from '@/components/posts/StagedMediaGallery.vue';
import RichTextEditor from '@/components/posts/RichTextEditor.vue';
import PreviewPanel from '@/components/preview/PreviewPanel.vue';
import PlatformSelectModal from '@/components/posts/PlatformSelectModal.vue';
import AiPlatformGenerateModal from '@/components/posts/AiPlatformGenerateModal.vue';
import TemplatePickerModal from '@/components/posts/TemplatePickerModal.vue';
import TemplateEditorModal from '@/components/posts/TemplateEditorModal.vue';
import TemplatePreviewModal from '@/components/posts/TemplatePreviewModal.vue';
import StockPhotoPicker from '@/components/stock/StockPhotoPicker.vue';

const props = defineProps({
    postId: {
        type: String,
        default: null,
    },
});

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const postsStore = usePostsStore();
const approvalStore = useApprovalStore();
const toast = useToast();

// Draft management - pass postId as reactive ref so it updates when route changes
const draft = usePostDraft(toRef(props, 'postId'));

const loading = ref(true);
const saving = ref(false);
const publishing = ref(false);
const showSaveDropdown = ref(false);
const showApprovalModal = ref(false);
const selectedTokenId = ref(null);
const activeSection = ref('content'); // 'content', 'media'
const showDraftRestoreModal = ref(false);
const pendingDraft = ref(null);
const showPlatformSelectModal = ref(false);
const showAiModal = ref(null); // null or platform name
const showTemplatePickerModal = ref(false);
const showTemplateEditorModal = ref(false);
const showTemplatePreviewModal = ref(false);
const showStockPhotoPicker = ref(false);
const selectedTemplateForEdit = ref(null);
const resumeTemplateId = ref(null);
const templateInProgressInfo = ref(null);

const isEditing = computed(() => !!props.postId);

// Platform definitions
const platformDefs = [
    { id: 'facebook', label: 'Facebook', shortLabel: 'FB', color: 'blue', bgColor: 'bg-blue-600', borderColor: 'border-blue-500', textColor: 'text-blue-600' },
    { id: 'instagram', label: 'Instagram', shortLabel: 'IG', color: 'pink', bgColor: 'bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400', borderColor: 'border-pink-500', textColor: 'text-pink-600' },
    { id: 'youtube', label: 'YouTube', shortLabel: 'YT', color: 'red', bgColor: 'bg-red-600', borderColor: 'border-red-500', textColor: 'text-red-600' },
];

// Selected platforms
const selectedPlatforms = ref(['facebook', 'instagram', 'youtube']);
const activePlatformTab = ref('facebook');

// Platform-specific content with inheritance tracking
// Note: 'captionModified' controls caption inheritance, hashtags are always independent
const platformContent = reactive({
    facebook: { caption: '', captionModified: false, hashtags: [], videoTitle: '', videoDescription: '' },
    instagram: { caption: '', captionModified: false, hashtags: [], videoTitle: '', videoDescription: '' },
    youtube: { caption: '', captionModified: false, hashtags: [], videoTitle: '', videoDescription: '' },
});

// Shared data
const sharedData = ref({
    title: '',
    scheduled_at: route.query.date ? new Date(route.query.date).toISOString() : null,
});

// Get the first selected platform (source for inheritance)
const firstPlatform = computed(() => {
    const order = ['facebook', 'instagram', 'youtube'];
    return order.find(p => selectedPlatforms.value.includes(p)) || 'facebook';
});

// Get effective caption for a platform (with inheritance)
const getEffectiveCaption = (platform) => {
    if (platformContent[platform].captionModified) {
        return platformContent[platform].caption;
    }
    // Inherit from first platform
    return platformContent[firstPlatform.value].caption;
};

// Update caption and mark as modified
const updatePlatformCaption = (platform, value) => {
    platformContent[platform].caption = value;
    platformContent[platform].captionModified = true;
};

// Reset platform to inherit from first
const resetToInherit = (platform) => {
    if (platform !== firstPlatform.value) {
        platformContent[platform].caption = '';
        platformContent[platform].captionModified = false;
    }
};

// Active platform tabs (only selected ones)
const activePlatformTabs = computed(() => {
    return platformDefs.filter(p => selectedPlatforms.value.includes(p.id));
});

// Combined media for preview (server + staged)
const allMedia = computed(() => {
    const serverMedia = postsStore.currentPost?.media || [];
    const staged = draft.stagedMedia.value || [];
    return [...serverMedia, ...staged];
});

// Form data for saving (combined)
const formData = computed(() => {
    const platforms = {};
    selectedPlatforms.value.forEach(p => {
        platforms[p] = {
            caption: getEffectiveCaption(p),
            captionModified: platformContent[p].captionModified,
            hashtags: platformContent[p].hashtags,
            video_title: platformContent[p].videoTitle,
            video_description: platformContent[p].videoDescription,
        };
    });

    return {
        title: sharedData.value.title,
        main_caption: platformContent[firstPlatform.value].caption,
        scheduled_at: sharedData.value.scheduled_at,
        platforms: selectedPlatforms.value,
        platform_content: platforms,
    };
});

const fetchData = async () => {
    loading.value = true;

    // Clear current post to prevent showing stale data
    postsStore.clearCurrentPost();

    // Reset all modal states when route changes
    showPlatformSelectModal.value = false;
    showDraftRestoreModal.value = false;
    showAiModal.value = null;
    pendingDraft.value = null;
    templateInProgressInfo.value = null;

    // Reset form data to defaults
    sharedData.value = {
        title: '',
        scheduled_at: route.query.date ? new Date(route.query.date).toISOString() : null,
    };
    selectedPlatforms.value = ['facebook', 'instagram', 'youtube'];
    activePlatformTab.value = 'facebook';

    Object.keys(platformContent).forEach(p => {
        platformContent[p].caption = '';
        platformContent[p].captionModified = false;
        platformContent[p].hashtags = [];
        platformContent[p].videoTitle = '';
        platformContent[p].videoDescription = '';
    });

    try {
        if (isEditing.value) {
            await postsStore.fetchPost(props.postId);
            if (postsStore.currentPost) {
                sharedData.value = {
                    title: postsStore.currentPost.title,
                    scheduled_at: postsStore.currentPost.scheduled_at,
                };
                selectedPlatforms.value = postsStore.currentPost.enabled_platforms || ['facebook', 'instagram', 'youtube'];
                activePlatformTab.value = selectedPlatforms.value[0] || 'facebook';

                // Load platform-specific content
                const platformPosts = postsStore.currentPost.platform_posts || [];
                platformPosts.forEach(pp => {
                    if (platformContent[pp.platform]) {
                        platformContent[pp.platform].caption = pp.platform_caption || postsStore.currentPost.main_caption || '';
                        platformContent[pp.platform].captionModified = !!pp.platform_caption;
                        platformContent[pp.platform].hashtags = pp.hashtags || [];
                        platformContent[pp.platform].videoTitle = pp.video_title || '';
                        platformContent[pp.platform].videoDescription = pp.video_description || '';
                    }
                });

                // If no platform posts yet, set first platform caption from main
                if (platformPosts.length === 0) {
                    platformContent[firstPlatform.value].caption = postsStore.currentPost.main_caption || '';
                }
            }
            draft.loadStagedMedia();
        } else {
            // New post - show platform selection modal
            const savedDraft = draft.loadDraft();
            if (savedDraft) {
                pendingDraft.value = savedDraft;
                showDraftRestoreModal.value = true;
            } else {
                showPlatformSelectModal.value = true;
            }
            draft.loadStagedMedia();
        }

        // Check for template in progress
        const savedTemplateSession = draft.loadTemplateInProgress();
        if (savedTemplateSession) {
            templateInProgressInfo.value = savedTemplateSession;
        }

        await approvalStore.fetchTokens();
    } catch (error) {
        console.error('Failed to fetch data:', error);
        if (isEditing.value) {
            router.push({ name: 'calendar' });
        }
    } finally {
        loading.value = false;
    }
};

const handlePlatformSelect = (platforms) => {
    selectedPlatforms.value = platforms;
    activePlatformTab.value = platforms[0];
    showPlatformSelectModal.value = false;
};

const restoreDraft = () => {
    if (pendingDraft.value) {
        sharedData.value = {
            title: pendingDraft.value.title || '',
            scheduled_at: pendingDraft.value.scheduled_at || null,
        };
        selectedPlatforms.value = pendingDraft.value.platforms || ['facebook', 'instagram', 'youtube'];
        activePlatformTab.value = selectedPlatforms.value[0];

        // Restore platform content if available
        if (pendingDraft.value.platform_content) {
            Object.keys(pendingDraft.value.platform_content).forEach(p => {
                if (platformContent[p]) {
                    platformContent[p].caption = pendingDraft.value.platform_content[p].caption || '';
                    platformContent[p].captionModified = pendingDraft.value.platform_content[p].captionModified || false;
                    platformContent[p].hashtags = pendingDraft.value.platform_content[p].hashtags || [];
                }
            });
        } else if (pendingDraft.value.main_caption) {
            platformContent[firstPlatform.value].caption = pendingDraft.value.main_caption;
        }
    }
    showDraftRestoreModal.value = false;
    pendingDraft.value = null;
};

const discardDraft = () => {
    draft.clearDraft();
    showDraftRestoreModal.value = false;
    pendingDraft.value = null;
    showPlatformSelectModal.value = true;
};

// Click outside to close dropdown
const handleClickOutside = (e) => {
    if (showSaveDropdown.value && !e.target.closest('.save-dropdown')) {
        showSaveDropdown.value = false;
    }
};

onMounted(() => {
    fetchData();
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

watch(() => props.postId, fetchData);

// Auto-save on changes
watch(
    [sharedData, platformContent, selectedPlatforms],
    () => {
        if (!loading.value) {
            draft.autoSave(formData.value);
        }
    },
    { deep: true }
);

const handleSave = async (publishToPlatform = null) => {
    saving.value = true;
    showSaveDropdown.value = false;
    try {
        let post;
        if (isEditing.value) {
            post = await postsStore.updatePost(props.postId, formData.value);
        } else {
            post = await postsStore.createPost(formData.value);
        }

        // Upload any staged media
        const stagedFiles = draft.getStagedFilesForUpload();
        for (const { file } of stagedFiles) {
            try {
                await postsStore.uploadMedia(post.id, file);
            } catch (error) {
                console.error('Failed to upload staged file:', error);
                toast.error(t('posts.errors.mediaUploadFailed'));
            }
        }

        // Clear draft after successful save
        draft.clearDraft();

        // Publish to platform if requested
        if (publishToPlatform) {
            publishing.value = true;
            try {
                await postsStore.publishPost(post.id, publishToPlatform);
                toast.success(t('posts.save.publishSuccess', { platform: publishToPlatform }));
            } catch (error) {
                console.error('Failed to publish post:', error);
                toast.error(t('posts.save.publishError'));
            } finally {
                publishing.value = false;
            }
        } else {
            toast.success(t('posts.saveSuccess'));
        }

        // Redirect to edit page if was new post
        if (!isEditing.value) {
            router.push({ name: 'post.edit', params: { postId: post.id } });
        } else {
            await postsStore.fetchPost(post.id);
        }
    } catch (error) {
        console.error('Failed to save post:', error);

        // Handle validation errors
        if (error.response?.status === 422) {
            const errors = error.response.data.errors;
            if (errors) {
                // Get first error message - use server message directly (already translated by Laravel)
                const firstFieldErrors = Object.values(errors)[0];
                const errorMessage = Array.isArray(firstFieldErrors) ? firstFieldErrors[0] : firstFieldErrors;
                toast.error(errorMessage, 5000);
            } else if (error.response.data.message) {
                toast.error(error.response.data.message, 5000);
            } else {
                toast.error(t('posts.errors.validationFailed'), 5000);
            }
        } else {
            toast.error(t('posts.errors.saveFailed'), 4000);
        }
    } finally {
        saving.value = false;
    }
};

const handleRequestApproval = async () => {
    if (!selectedTokenId.value) return;

    try {
        await postsStore.requestApproval(props.postId, selectedTokenId.value);
        showApprovalModal.value = false;
        await postsStore.fetchPost(props.postId);
    } catch (error) {
        console.error('Failed to request approval:', error);
    }
};

const handleDelete = async () => {
    if (!confirm(t('posts.deleteConfirm'))) return;

    try {
        await postsStore.deletePost(props.postId);
        draft.clearDraft();
        router.push({ name: 'calendar' });
    } catch (error) {
        console.error('Failed to delete post:', error);
    }
};

const handleDuplicate = async () => {
    try {
        const newPost = await postsStore.duplicatePost(props.postId);
        router.push({ name: 'post.edit', params: { postId: newPost.id } });
    } catch (error) {
        console.error('Failed to duplicate post:', error);
    }
};

// Handle staged media
const handleStageMedia = async (files) => {
    for (const file of files) {
        await draft.stageMediaFile(file);
    }
};

const handleRemoveStagedMedia = (mediaId) => {
    draft.removeStagedMedia(mediaId);
};

const handleReorderStagedMedia = (fromIndex, toIndex) => {
    draft.reorderStagedMedia(fromIndex, toIndex);
};

// Handle template selection
const handleOpenTemplates = () => {
    showTemplatePickerModal.value = true;
};

// Handle stock photo selection
const handleOpenStockPhotos = () => {
    showStockPhotoPicker.value = true;
};

const handleSelectStockPhoto = async (photo) => {
    showStockPhotoPicker.value = false;
    // Fetch the image and add as staged media
    try {
        const response = await fetch(photo.download_url || photo.url);
        const blob = await response.blob();
        const filename = `stock-${photo.source}-${photo.id}.jpg`;
        const file = new File([blob], filename, { type: blob.type });
        await draft.stageMediaFile(file);
    } catch (error) {
        console.error('Failed to add stock photo:', error);
        toast.error(t('stockPhotos.error') || 'Failed to add photo');
    }
};

const handleTemplateSelect = (template) => {
    selectedTemplateForEdit.value = template;
    resumeTemplateId.value = null; // New template, not resuming
    showTemplatePickerModal.value = false;
    showTemplateEditorModal.value = true;
};

const handleAddTemplateToPost = async (file) => {
    showTemplateEditorModal.value = false;
    selectedTemplateForEdit.value = null;
    resumeTemplateId.value = null;
    templateInProgressInfo.value = null;
    // Clear the template in progress since we added it
    draft.clearTemplateInProgress();
    // Add the exported file as staged media
    await draft.stageMediaFile(file);
};

const handleCloseTemplateEditor = () => {
    showTemplateEditorModal.value = false;
    selectedTemplateForEdit.value = null;
    resumeTemplateId.value = null;
};

const handleSaveTemplateForLater = (sessionData) => {
    draft.saveTemplateInProgress(sessionData);
    templateInProgressInfo.value = sessionData;
    handleCloseTemplateEditor();
};

// Handle template preview modal
const handleOpenTemplatePreview = () => {
    showTemplatePreviewModal.value = true;
};

const handleTemplatePreviewSelect = async (preview) => {
    showTemplatePreviewModal.value = false;
    // Fetch the generated preview image and add as staged media
    try {
        const response = await fetch(preview.preview_url);
        const blob = await response.blob();
        const filename = `preview-${preview.id}-${Date.now()}.png`;
        const file = new File([blob], filename, { type: 'image/png' });
        await draft.stageMediaFile(file);
    } catch (error) {
        console.error('Failed to add preview image:', error);
        toast.error(t('posts.template_preview.add_failed'));
    }
};

const handleTemplatePreviewEdit = (preview) => {
    showTemplatePreviewModal.value = false;
    // Navigate to the template editor with the selected template
    if (preview?.id) {
        router.push({ name: 'template.editor', params: { templateId: preview.id } });
    }
};

const handleResumeTemplate = () => {
    if (templateInProgressInfo.value) {
        // Create a minimal template object for the modal
        selectedTemplateForEdit.value = {
            id: templateInProgressInfo.value.originalTemplateId,
            name: templateInProgressInfo.value.originalTemplateName,
            is_library: templateInProgressInfo.value.isLibrary,
        };
        resumeTemplateId.value = templateInProgressInfo.value.templateId;
        showTemplateEditorModal.value = true;
    }
};

const handleDiscardTemplateInProgress = async () => {
    if (templateInProgressInfo.value?.templateId) {
        // Delete the temporary template from server
        try {
            const axios = (await import('axios')).default;
            await axios.delete(`/api/v1/templates/${templateInProgressInfo.value.templateId}`);
        } catch (e) {
            console.warn('Failed to cleanup template:', e);
        }
    }
    draft.clearTemplateInProgress();
    templateInProgressInfo.value = null;
};

// Handle AI generated content
const handleAiGenerated = (platform, result) => {
    platformContent[platform].captionModified = true;

    if (platform === 'youtube') {
        if (result.title) {
            platformContent[platform].videoTitle = result.title;
        }
        if (result.description) {
            platformContent[platform].videoDescription = result.description;
            platformContent[platform].caption = result.description;
        }
    } else {
        if (result.caption) {
            platformContent[platform].caption = result.caption;
        }
    }

    // Always set hashtags for Instagram if available (regardless of which platform was generated)
    if (result.hashtags?.length && selectedPlatforms.value.includes('instagram')) {
        // Only set if Instagram hashtags are empty or this is the first platform
        if (platformContent.instagram.hashtags.length === 0 || platform === firstPlatform.value) {
            platformContent.instagram.hashtags = result.hashtags;
        }
    }

    showAiModal.value = null;
};

// Sections
const sections = computed(() => [
    { id: 'content', label: t('posts.sections.content') },
    { id: 'media', label: t('posts.sections.media') },
]);

// Schedule handling
const formatDateForInput = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return d.toISOString().slice(0, 16);
};

const handleScheduleChange = (e) => {
    const value = e.target.value;
    sharedData.value.scheduled_at = value ? new Date(value).toISOString() : null;
};

const clearSchedule = () => {
    sharedData.value.scheduled_at = null;
};

// Check if save is possible
const canSave = computed(() => {
    return sharedData.value.title && platformContent[firstPlatform.value].caption;
});

// Format last saved time
const lastSavedText = computed(() => {
    if (!draft.lastSaved.value) return null;
    const now = new Date();
    const diff = Math.floor((now - draft.lastSaved.value) / 1000);

    if (diff < 5) return t('posts.draft.justNow');
    if (diff < 60) return t('posts.draft.secondsAgo', { seconds: diff });
    if (diff < 3600) return t('posts.draft.minutesAgo', { minutes: Math.floor(diff / 60) });
    return draft.lastSaved.value.toLocaleTimeString();
});
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <RouterLink
                        :to="{ name: 'calendar' }"
                        class="text-gray-500 hover:text-gray-700"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </RouterLink>
                    <h1 class="text-xl font-semibold text-gray-900">
                        {{ isEditing ? t('posts.edit') : t('posts.create') }}
                    </h1>

                    <!-- Draft indicator -->
                    <div v-if="draft.hasDraft.value && lastSavedText" class="flex items-center text-sm text-gray-500">
                        <svg class="w-4 h-4 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        {{ t('posts.draft.saved') }} {{ lastSavedText }}
                    </div>
                </div>

                <!-- Platform tabs (right side) -->
                <div class="flex items-center space-x-4">
                    <div class="flex items-center bg-gray-100 rounded-lg p-1">
                        <button
                            v-for="platform in activePlatformTabs"
                            :key="platform.id"
                            @click="activePlatformTab = platform.id"
                            class="relative px-3 py-1.5 rounded-md text-sm font-medium transition-all"
                            :class="activePlatformTab === platform.id
                                ? 'bg-white shadow-sm ' + platform.textColor
                                : 'text-gray-500 hover:text-gray-700'"
                        >
                            <div class="flex items-center space-x-1.5">
                                <span
                                    class="w-5 h-5 rounded flex items-center justify-center text-white text-xs font-bold"
                                    :class="platform.bgColor"
                                >
                                    {{ platform.label[0] }}
                                </span>
                                <span class="hidden sm:inline">{{ platform.shortLabel }}</span>
                            </div>
                            <!-- Modified indicator -->
                            <span
                                v-if="platformContent[platform.id].captionModified && platform.id !== firstPlatform"
                                class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-orange-400"
                            ></span>
                        </button>
                    </div>

                    <div class="flex items-center space-x-2">
                        <template v-if="isEditing && postsStore.currentPost">
                            <Button
                                v-if="postsStore.currentPost.can_delete"
                                variant="danger"
                                size="sm"
                                @click="handleDelete"
                            >
                                {{ t('common.delete') }}
                            </Button>
                            <Button variant="secondary" size="sm" @click="handleDuplicate">
                                {{ t('posts.duplicate') }}
                            </Button>
                        </template>

                        <!-- Save dropdown -->
                        <div class="relative save-dropdown">
                            <div class="flex">
                                <Button
                                    :loading="saving || publishing"
                                    :disabled="!canSave"
                                    @click="handleSave()"
                                    class="rounded-r-none"
                                >
                                    {{ publishing ? t('posts.save.publishing') : t('posts.save.save') }}
                                </Button>
                                <button
                                    @click="showSaveDropdown = !showSaveDropdown"
                                    :disabled="!canSave || saving || publishing"
                                    class="px-2 bg-blue-600 hover:bg-blue-700 text-white rounded-r-lg border-l border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Dropdown menu -->
                            <div
                                v-if="showSaveDropdown"
                                class="absolute right-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 z-50"
                            >
                                <div class="py-1">
                                    <button
                                        @click="handleSave()"
                                        class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center"
                                    >
                                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                        </svg>
                                        {{ t('posts.save.save') }}
                                    </button>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <p class="px-4 py-1 text-xs text-gray-400 uppercase font-medium">
                                        {{ t('posts.save.saveAndPublish') }}
                                    </p>
                                    <button
                                        v-for="platform in activePlatformTabs"
                                        :key="platform.id"
                                        @click="handleSave(platform.id)"
                                        class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center"
                                    >
                                        <span
                                            class="w-5 h-5 rounded flex items-center justify-center text-white text-xs font-bold mr-2"
                                            :class="platform.bgColor"
                                        >
                                            {{ platform.label[0] }}
                                        </span>
                                        {{ t('posts.save.publishTo', { platform: platform.label }) }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section tabs -->
        <div class="bg-white border-b border-gray-200 px-6">
            <nav class="flex space-x-8">
                <button
                    v-for="section in sections"
                    :key="section.id"
                    @click="activeSection = section.id"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                    :class="activeSection === section.id
                        ? 'border-blue-500 text-blue-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 cursor-pointer'"
                >
                    {{ section.label }}
                    <span v-if="section.id === 'media' && draft.stagedMedia.value.length > 0"
                          class="ml-2 px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded-full">
                        {{ draft.stagedMedia.value.length }}
                    </span>
                </button>
            </nav>
        </div>

        <!-- Content -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <LoadingSpinner size="lg" />
        </div>
        <div v-else class="flex h-[calc(100vh-130px)]">
            <!-- Left panel: Content -->
            <div class="w-1/2 overflow-y-auto p-6 space-y-6">
                <!-- Content section -->
                <div v-show="activeSection === 'content'">
                    <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-6">
                        <!-- Title (shared) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ t('posts.title') }}
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="sharedData.title"
                                type="text"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                :placeholder="t('posts.titlePlaceholder')"
                            />
                        </div>

                        <!-- Platform content header with AI button -->
                        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                            <div class="flex items-center space-x-2">
                                <span
                                    class="w-6 h-6 rounded flex items-center justify-center text-white text-xs font-bold"
                                    :class="platformDefs.find(p => p.id === activePlatformTab)?.bgColor"
                                >
                                    {{ platformDefs.find(p => p.id === activePlatformTab)?.label[0] }}
                                </span>
                                <span class="text-sm font-medium text-gray-700">
                                    {{ platformDefs.find(p => p.id === activePlatformTab)?.label }}
                                </span>
                                <span
                                    v-if="!platformContent[activePlatformTab].captionModified && activePlatformTab !== firstPlatform"
                                    class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded"
                                >
                                    {{ t('posts.inheritedFromFirst') }}
                                </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button
                                    v-if="platformContent[activePlatformTab].captionModified && activePlatformTab !== firstPlatform"
                                    @click="resetToInherit(activePlatformTab)"
                                    class="text-xs text-gray-500 hover:text-gray-700"
                                >
                                    {{ t('posts.resetToInherit') }}
                                </button>
                                <button
                                    @click="showAiModal = activePlatformTab"
                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white rounded-lg transition-all"
                                    :class="platformDefs.find(p => p.id === activePlatformTab)?.bgColor"
                                >
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                    </svg>
                                    {{ t('posts.ai.generate') }}
                                </button>
                            </div>
                        </div>

                        <!-- Caption editor -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ activePlatformTab === 'youtube' ? t('youtube.videoDescription') : t('posts.mainCaption') }}
                                <span class="text-red-500">*</span>
                            </label>
                            <RichTextEditor
                                :model-value="getEffectiveCaption(activePlatformTab)"
                                @update:model-value="updatePlatformCaption(activePlatformTab, $event)"
                                :placeholder="t('posts.mainCaptionPlaceholder')"
                                :rows="6"
                                :max-length="activePlatformTab === 'instagram' ? 2200 : undefined"
                            />
                        </div>

                        <!-- YouTube specific: Video Title -->
                        <div v-if="activePlatformTab === 'youtube'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ t('youtube.videoTitle') }}
                                <span class="text-gray-400 font-normal">({{ t('youtube.titleLimit') }})</span>
                            </label>
                            <input
                                v-model="platformContent.youtube.videoTitle"
                                @input="platformContent.youtube.captionModified = true"
                                type="text"
                                maxlength="100"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                :placeholder="t('youtube.videoTitlePlaceholder')"
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                {{ platformContent.youtube.videoTitle?.length || 0 }} / 100
                            </p>
                        </div>

                        <!-- Instagram specific: Hashtags -->
                        <div v-if="activePlatformTab === 'instagram'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ t('instagram.hashtags') }}
                            </label>
                            <div v-if="platformContent.instagram.hashtags?.length" class="flex flex-wrap gap-2 mb-3">
                                <span
                                    v-for="(tag, index) in platformContent.instagram.hashtags"
                                    :key="index"
                                    class="inline-flex items-center px-2 py-1 rounded-full text-sm bg-pink-100 text-pink-700"
                                >
                                    {{ tag }}
                                    <button
                                        @click="platformContent.instagram.hashtags.splice(index, 1)"
                                        class="ml-1 text-pink-500 hover:text-pink-700"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </span>
                            </div>
                            <div class="flex space-x-2">
                                <input
                                    type="text"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500"
                                    :placeholder="t('instagram.addHashtag')"
                                    @keyup.enter="(e) => {
                                        let tag = e.target.value.trim();
                                        if (tag) {
                                            if (!tag.startsWith('#')) tag = '#' + tag;
                                            if (!platformContent.instagram.hashtags.includes(tag)) {
                                                platformContent.instagram.hashtags.push(tag);
                                            }
                                            e.target.value = '';
                                        }
                                    }"
                                />
                            </div>
                        </div>

                        <!-- Schedule (shared) -->
                        <div class="pt-4 border-t border-gray-100">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ t('posts.scheduledAt') }}
                            </label>
                            <div class="flex items-center space-x-2">
                                <input
                                    type="datetime-local"
                                    :value="formatDateForInput(sharedData.scheduled_at)"
                                    @input="handleScheduleChange"
                                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                                <button
                                    v-if="sharedData.scheduled_at"
                                    @click="clearSchedule"
                                    class="p-2 text-gray-400 hover:text-gray-600"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Media section -->
                <div v-show="activeSection === 'media'">
                    <!-- Template in progress banner -->
                    <div
                        v-if="templateInProgressInfo"
                        class="mb-4 bg-purple-50 border border-purple-200 rounded-lg p-4"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-purple-900">
                                        {{ t('posts.media.templateInProgress') }}
                                    </p>
                                    <p class="text-xs text-purple-600">
                                        {{ templateInProgressInfo.originalTemplateName }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button
                                    @click="handleDiscardTemplateInProgress"
                                    class="px-3 py-1.5 text-sm text-purple-600 hover:text-purple-800 hover:bg-purple-100 rounded-lg transition-colors"
                                >
                                    {{ t('posts.media.discardTemplate') }}
                                </button>
                                <button
                                    @click="handleResumeTemplate"
                                    class="px-3 py-1.5 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors"
                                >
                                    {{ t('posts.media.resumeEditing') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            {{ t('posts.media.title') }}
                        </h3>

                        <StagedMediaGallery
                            v-if="!isEditing"
                            :media="draft.stagedMedia.value"
                            @upload="handleStageMedia"
                            @remove="handleRemoveStagedMedia"
                            @reorder="handleReorderStagedMedia"
                            @open-templates="handleOpenTemplates"
                            @open-stock-photos="handleOpenStockPhotos"
                        />

                        <template v-else>
                            <PostMediaGallery
                                :post-id="postsStore.currentPost?.id"
                                :media="postsStore.currentPost?.media || []"
                            />

                            <!-- Add from templates buttons -->
                            <div class="mt-4 pt-4 border-t border-gray-200 space-y-2">
                                <button
                                    @click="handleOpenTemplates"
                                    class="w-full flex items-center justify-center space-x-2 px-4 py-3 border-2 border-dashed border-gray-300 hover:border-purple-400 rounded-lg text-gray-600 hover:text-purple-600 transition-colors"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                                    </svg>
                                    <span class="font-medium">{{ t('posts.media.fromTemplates') }}</span>
                                </button>
                                <button
                                    @click="handleOpenTemplatePreview"
                                    class="w-full flex items-center justify-center space-x-2 px-4 py-3 border-2 border-dashed border-gray-300 hover:border-green-400 rounded-lg text-gray-600 hover:text-green-600 transition-colors"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <span class="font-medium">{{ t('posts.template_preview.preview_button') }}</span>
                                </button>
                            </div>

                            <div v-if="draft.stagedMedia.value.length > 0" class="mt-6 pt-6 border-t border-gray-200">
                                <h4 class="text-sm font-medium text-gray-700 mb-3">
                                    {{ t('posts.media.pendingUpload') }}
                                </h4>
                                <StagedMediaGallery
                                    :media="draft.stagedMedia.value"
                                    @upload="handleStageMedia"
                                    @remove="handleRemoveStagedMedia"
                                    @reorder="handleReorderStagedMedia"
                                    :show-upload-area="false"
                                />
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Right panel: Preview -->
            <div class="w-1/2 bg-gray-100 border-l border-gray-200 overflow-y-auto p-6">
                <PreviewPanel
                    :title="sharedData.title"
                    :caption="getEffectiveCaption(activePlatformTab)"
                    :media="allMedia"
                    :active-platform="activePlatformTab"
                    :selected-platforms="selectedPlatforms"
                    :hashtags="platformContent[activePlatformTab].hashtags"
                    :video-title="platformContent[activePlatformTab].videoTitle"
                />
            </div>
        </div>

        <!-- Platform Select Modal -->
        <teleport to="body">
            <PlatformSelectModal
                v-if="showPlatformSelectModal"
                @close="router.push({ name: 'calendar' })"
                @confirm="handlePlatformSelect"
            />
        </teleport>

        <!-- AI Generate Modal -->
        <teleport to="body">
            <AiPlatformGenerateModal
                v-if="showAiModal"
                :platform="showAiModal"
                @close="showAiModal = null"
                @generated="handleAiGenerated(showAiModal, $event)"
            />
        </teleport>

        <!-- Approval Modal -->
        <teleport to="body">
            <div
                v-if="showApprovalModal"
                class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                @click.self="showApprovalModal = false"
            >
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                    <div class="p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">
                            {{ t('approval.requestTitle') }}
                        </h2>
                        <p class="text-sm text-gray-600 mb-4">
                            {{ t('approval.requestDescription') }}
                        </p>
                        <select
                            v-model="selectedTokenId"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">{{ t('approval.selectToken') }}</option>
                            <option
                                v-for="token in approvalStore.activeTokens"
                                :key="token.id"
                                :value="token.id"
                            >
                                {{ token.client_name }}
                            </option>
                        </select>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3 rounded-b-lg">
                        <Button variant="secondary" @click="showApprovalModal = false">
                            {{ t('common.cancel') }}
                        </Button>
                        <Button
                            :disabled="!selectedTokenId"
                            @click="handleRequestApproval"
                        >
                            {{ t('approval.send') }}
                        </Button>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- Draft Restore Modal -->
        <teleport to="body">
            <div
                v-if="showDraftRestoreModal"
                class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            >
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <h2 class="text-lg font-semibold text-gray-900">
                                {{ t('posts.draft.foundTitle') }}
                            </h2>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            {{ t('posts.draft.foundDescription') }}
                        </p>
                        <div v-if="pendingDraft" class="bg-gray-50 rounded-lg p-4 mb-4">
                            <p class="text-sm font-medium text-gray-900">{{ pendingDraft.title || t('posts.draft.untitled') }}</p>
                            <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ pendingDraft.main_caption }}</p>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3 rounded-b-lg">
                        <Button variant="secondary" @click="discardDraft">
                            {{ t('posts.draft.discard') }}
                        </Button>
                        <Button @click="restoreDraft">
                            {{ t('posts.draft.restore') }}
                        </Button>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- Template Picker Modal -->
        <teleport to="body">
            <TemplatePickerModal
                v-if="showTemplatePickerModal"
                @close="showTemplatePickerModal = false"
                @select="handleTemplateSelect"
            />
        </teleport>

        <!-- Template Editor Modal -->
        <teleport to="body">
            <TemplateEditorModal
                v-if="showTemplateEditorModal && selectedTemplateForEdit"
                :template="selectedTemplateForEdit"
                :is-library="selectedTemplateForEdit.is_library"
                :resume-template-id="resumeTemplateId"
                @close="handleCloseTemplateEditor"
                @add-to-post="handleAddTemplateToPost"
                @save-for-later="handleSaveTemplateForLater"
            />
        </teleport>

        <!-- Stock Photo Picker -->
        <teleport to="body">
            <StockPhotoPicker
                v-if="showStockPhotoPicker"
                @close="showStockPhotoPicker = false"
                @select="handleSelectStockPhoto"
            />
        </teleport>

        <!-- Template Preview Modal -->
        <teleport to="body">
            <TemplatePreviewModal
                v-if="showTemplatePreviewModal"
                @close="showTemplatePreviewModal = false"
                @select="handleTemplatePreviewSelect"
                @edit="handleTemplatePreviewEdit"
            />
        </teleport>

        <!-- Toast notifications -->
        <Toast />
    </div>
</template>
