import { ref, computed, watch, onMounted, onUnmounted, toRef, isRef } from 'vue';

const DRAFT_KEY_PREFIX = 'post_draft_';
const STAGED_MEDIA_KEY_PREFIX = 'post_staged_media_';
const TEMPLATE_IN_PROGRESS_KEY_PREFIX = 'post_template_';
const AUTO_SAVE_INTERVAL = 2000; // 2 seconds

export function usePostDraft(postIdInput = null) {
    // Make postId reactive - accept either a ref or a plain value
    const postIdRef = isRef(postIdInput) ? postIdInput : ref(postIdInput);

    // Compute keys reactively based on postId
    const draftKey = computed(() => postIdRef.value ? `${DRAFT_KEY_PREFIX}${postIdRef.value}` : `${DRAFT_KEY_PREFIX}new`);
    const mediaKey = computed(() => postIdRef.value ? `${STAGED_MEDIA_KEY_PREFIX}${postIdRef.value}` : `${STAGED_MEDIA_KEY_PREFIX}new`);
    const templateKey = computed(() => postIdRef.value ? `${TEMPLATE_IN_PROGRESS_KEY_PREFIX}${postIdRef.value}` : `${TEMPLATE_IN_PROGRESS_KEY_PREFIX}new`);

    const hasDraft = ref(false);
    const lastSaved = ref(null);
    const stagedMedia = ref([]);
    const templateInProgress = ref(null);

    let autoSaveTimeout = null;

    // Reset state when postId changes
    watch(postIdRef, () => {
        hasDraft.value = false;
        lastSaved.value = null;
        stagedMedia.value = [];
        templateInProgress.value = null;
        if (autoSaveTimeout) {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = null;
        }
    });

    // Load draft from localStorage
    const loadDraft = () => {
        try {
            const savedDraft = localStorage.getItem(draftKey.value);
            if (savedDraft) {
                const parsed = JSON.parse(savedDraft);
                hasDraft.value = true;
                lastSaved.value = parsed._savedAt ? new Date(parsed._savedAt) : null;
                delete parsed._savedAt;
                return parsed;
            }
        } catch (error) {
            console.error('Failed to load draft:', error);
        }
        return null;
    };

    // Save draft to localStorage
    const saveDraft = (data) => {
        try {
            const toSave = {
                ...data,
                _savedAt: new Date().toISOString(),
            };
            localStorage.setItem(draftKey.value, JSON.stringify(toSave));
            hasDraft.value = true;
            lastSaved.value = new Date();
        } catch (error) {
            console.error('Failed to save draft:', error);
        }
    };

    // Clear draft from localStorage
    const clearDraft = () => {
        try {
            localStorage.removeItem(draftKey.value);
            localStorage.removeItem(mediaKey.value);
            hasDraft.value = false;
            lastSaved.value = null;
            stagedMedia.value = [];
        } catch (error) {
            console.error('Failed to clear draft:', error);
        }
    };

    // Auto-save with debounce
    const autoSave = (data) => {
        if (autoSaveTimeout) {
            clearTimeout(autoSaveTimeout);
        }
        autoSaveTimeout = setTimeout(() => {
            saveDraft(data);
        }, AUTO_SAVE_INTERVAL);
    };

    // Load staged media from localStorage
    const loadStagedMedia = () => {
        try {
            const saved = localStorage.getItem(mediaKey.value);
            if (saved) {
                stagedMedia.value = JSON.parse(saved);
                return stagedMedia.value;
            }
        } catch (error) {
            console.error('Failed to load staged media:', error);
        }
        return [];
    };

    // Save staged media to localStorage (just metadata, not actual files)
    const saveStagedMedia = (media) => {
        try {
            stagedMedia.value = media;
            localStorage.setItem(mediaKey.value, JSON.stringify(media));
        } catch (error) {
            console.error('Failed to save staged media:', error);
        }
    };

    // Add file to staged media (store as base64 for preview)
    const stageMediaFile = async (file) => {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => {
                const mediaItem = {
                    id: `staged_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
                    filename: file.name,
                    type: file.type.startsWith('video/') ? 'video' : 'image',
                    is_image: file.type.startsWith('image/'),
                    is_video: file.type.startsWith('video/'),
                    size: file.size,
                    mime_type: file.type,
                    preview_url: reader.result, // base64 data URL
                    file: file, // Keep reference to actual file for upload
                    staged: true,
                };

                const newMedia = [...stagedMedia.value, mediaItem];
                saveStagedMedia(newMedia);
                resolve(mediaItem);
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    };

    // Remove staged media
    const removeStagedMedia = (mediaId) => {
        const newMedia = stagedMedia.value.filter(m => m.id !== mediaId);
        saveStagedMedia(newMedia);
    };

    // Reorder staged media
    const reorderStagedMedia = (fromIndex, toIndex) => {
        const newMedia = [...stagedMedia.value];
        const [moved] = newMedia.splice(fromIndex, 1);
        newMedia.splice(toIndex, 0, moved);
        saveStagedMedia(newMedia);
    };

    // Get all media (staged only for new posts, or combined for existing)
    const getAllMedia = (serverMedia = []) => {
        // For new posts, return staged media
        // For existing posts, server media takes precedence
        if (!postIdRef.value) {
            return stagedMedia.value;
        }
        // For existing posts, combine server media with any newly staged media
        return [...serverMedia, ...stagedMedia.value.filter(m => m.staged)];
    };

    // Convert base64 data URL to File object
    const base64ToFile = (dataUrl, filename, mimeType) => {
        const arr = dataUrl.split(',');
        const mime = mimeType || arr[0].match(/:(.*?);/)?.[1] || 'application/octet-stream';
        const bstr = atob(arr[1]);
        let n = bstr.length;
        const u8arr = new Uint8Array(n);
        while (n--) {
            u8arr[n] = bstr.charCodeAt(n);
        }
        return new File([u8arr], filename, { type: mime });
    };

    // Get staged files for upload
    const getStagedFilesForUpload = () => {
        return stagedMedia.value
            .filter(m => m.staged)
            .map(m => {
                // If we have the original File object, use it
                if (m.file instanceof File) {
                    return { id: m.id, file: m.file };
                }
                // Otherwise, reconstruct from base64 preview_url
                if (m.preview_url && m.preview_url.startsWith('data:')) {
                    const file = base64ToFile(m.preview_url, m.filename, m.mime_type);
                    return { id: m.id, file };
                }
                return null;
            })
            .filter(Boolean);
    };

    // Check if there's a draft for new post
    const checkForNewDraft = () => {
        if (!postIdRef.value) {
            return loadDraft();
        }
        return null;
    };

    // Template in progress management
    const loadTemplateInProgress = () => {
        try {
            const saved = localStorage.getItem(templateKey.value);
            if (saved) {
                templateInProgress.value = JSON.parse(saved);
                return templateInProgress.value;
            }
        } catch (error) {
            console.error('Failed to load template in progress:', error);
        }
        return null;
    };

    const saveTemplateInProgress = (data) => {
        try {
            const toSave = {
                templateId: data.templateId,
                originalTemplateId: data.originalTemplateId,
                originalTemplateName: data.originalTemplateName,
                isLibrary: data.isLibrary,
                savedAt: new Date().toISOString(),
            };
            localStorage.setItem(templateKey.value, JSON.stringify(toSave));
            templateInProgress.value = toSave;
        } catch (error) {
            console.error('Failed to save template in progress:', error);
        }
    };

    const clearTemplateInProgress = () => {
        try {
            localStorage.removeItem(templateKey.value);
            templateInProgress.value = null;
        } catch (error) {
            console.error('Failed to clear template in progress:', error);
        }
    };

    // Cleanup on unmount
    onUnmounted(() => {
        if (autoSaveTimeout) {
            clearTimeout(autoSaveTimeout);
        }
    });

    return {
        hasDraft,
        lastSaved,
        stagedMedia,
        templateInProgress,
        loadDraft,
        saveDraft,
        clearDraft,
        autoSave,
        loadStagedMedia,
        saveStagedMedia,
        stageMediaFile,
        removeStagedMedia,
        reorderStagedMedia,
        getAllMedia,
        getStagedFilesForUpload,
        checkForNewDraft,
        loadTemplateInProgress,
        saveTemplateInProgress,
        clearTemplateInProgress,
    };
}

// Helper to get draft info without loading full composable
export function getDraftInfo(postId = null) {
    const draftKey = postId ? `${DRAFT_KEY_PREFIX}${postId}` : `${DRAFT_KEY_PREFIX}new`;
    try {
        const savedDraft = localStorage.getItem(draftKey);
        if (savedDraft) {
            const parsed = JSON.parse(savedDraft);
            return {
                exists: true,
                savedAt: parsed._savedAt ? new Date(parsed._savedAt) : null,
                title: parsed.title || '',
            };
        }
    } catch (error) {
        // Ignore
    }
    return { exists: false };
}
