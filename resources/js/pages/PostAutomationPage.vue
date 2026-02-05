<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { usePostsStore } from '@/stores/posts';
import { useBrandsStore } from '@/stores/brands';
import { useToast } from '@/composables/useToast';

import AutomationStatsBar from '@/components/automation/AutomationStatsBar.vue';
import AutomationStatusTabs from '@/components/automation/AutomationStatusTabs.vue';
import AutomationToolbar from '@/components/automation/AutomationToolbar.vue';
import AutomationPostTable from '@/components/automation/AutomationPostTable.vue';
import AutomationPostCard from '@/components/automation/AutomationPostCard.vue';
import AutomationBulkBar from '@/components/automation/AutomationBulkBar.vue';
import AutomationPagination from '@/components/automation/AutomationPagination.vue';
import AutomationEmptyState from '@/components/automation/AutomationEmptyState.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import WebhookSettingsModal from '@/components/automation/WebhookSettingsModal.vue';
import PostPreviewModal from '@/components/automation/PostPreviewModal.vue';
import SystemPromptModal from '@/components/automation/SystemPromptModal.vue';

const { t } = useI18n();
const router = useRouter();
const postsStore = usePostsStore();
const brandsStore = useBrandsStore();
const toast = useToast();

// State
const search = ref('');
const statusFilter = ref('');
const selectedIds = ref([]);
const showWebhookSettings = ref(false);
const showTextPrompt = ref(false);
const showImagePrompt = ref(false);
const showPreview = ref(false);
const previewPost = ref(null);
const bulkGeneratingText = ref(false);
const bulkGeneratingImage = ref(false);
const stats = ref({});
const statsLoading = ref(false);

const platformColors = {
    facebook: { bg: 'bg-blue-100', text: 'text-blue-700', dot: 'bg-blue-500' },
    instagram: { bg: 'bg-pink-100', text: 'text-pink-700', dot: 'bg-pink-500' },
    youtube: { bg: 'bg-red-100', text: 'text-red-700', dot: 'bg-red-500' },
};

// Computed
const posts = computed(() => postsStore.automationPosts);
const pagination = computed(() => postsStore.automationPagination);
const loading = computed(() => postsStore.loading);

// Data fetching
function fetchPosts(page = 1) {
    const params = { page, per_page: 20 };
    if (search.value) params.search = search.value;
    if (statusFilter.value) params.status = statusFilter.value;
    if (brandsStore.currentBrand?.id) params.brand_id = brandsStore.currentBrand.id;
    postsStore.fetchAutomationPosts(params).catch(() => {
        toast.error(t('postAutomation.errors.fetchFailed'));
    });
}

async function fetchStats() {
    if (!brandsStore.currentBrand?.id) return;
    statsLoading.value = true;
    try {
        stats.value = await brandsStore.fetchAutomationStats(brandsStore.currentBrand.id);
    } catch {
        // Stats are non-critical, silently fail
    } finally {
        statsLoading.value = false;
    }
}

function refresh() {
    fetchPosts(pagination.value.currentPage);
    fetchStats();
}

// Selection
function toggleSelect(id) {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(idx, 1);
    }
}

function toggleSelectAll() {
    if (selectedIds.value.length === posts.value.length) {
        selectedIds.value = [];
    } else {
        selectedIds.value = posts.value.map(p => p.id);
    }
}

function clearSelection() {
    selectedIds.value = [];
}

// Status filter from stats or tabs
function filterByStatus(status) {
    statusFilter.value = status;
}

// Error extraction
function extractError(err, fallbackKey) {
    const serverError = err?.response?.data?.error || err?.response?.data?.message;
    if (serverError) {
        return `${t(fallbackKey)}: ${serverError}`;
    }
    return t(fallbackKey);
}

// Single post actions
async function generateText(postId) {
    try {
        await postsStore.generatePostText(postId);
        toast.success(t('postAutomation.success.textGenerated'));
        fetchStats();
    } catch (err) {
        toast.error(extractError(err, 'postAutomation.errors.generateFailed'), 5000);
    }
}

async function generateImagePrompt(postId) {
    try {
        await postsStore.generatePostImagePrompt(postId);
        toast.success(t('postAutomation.success.imageGenerated'));
    } catch (err) {
        toast.error(extractError(err, 'postAutomation.errors.imageFailed'), 5000);
    }
}

async function approvePost(postId) {
    try {
        await postsStore.approvePost(postId);
        postsStore.updateAutomationPost(postId, {
            status: 'approved',
            status_label: t('posts.status.approved'),
        });
        toast.success(t('postAutomation.success.approved'));
        fetchStats();
    } catch (err) {
        toast.error(extractError(err, 'posts.errors.saveFailed'), 5000);
    }
}

async function publishPost(postId) {
    try {
        await postsStore.webhookPublishPost(postId);
        toast.success(t('postAutomation.success.published'));
        fetchStats();
    } catch (err) {
        toast.error(extractError(err, 'postAutomation.errors.publishFailed'), 5000);
    }
}

function openPreview(post) {
    previewPost.value = post;
    showPreview.value = true;
}

function openEditor(postId) {
    router.push({ name: 'post.edit', params: { postId } });
}

// Inline editing
async function updateField({ postId, field, value }) {
    try {
        await postsStore.updatePost(postId, { [field]: value });
        postsStore.updateAutomationPost(postId, { [field]: value });
    } catch {
        toast.error(t('posts.errors.saveFailed'));
    }
}

// Platform toggle
async function togglePlatform({ postId, platform }) {
    const post = posts.value.find(p => p.id === postId);
    const pp = post?.platform_posts?.find(p => p.platform === platform);
    if (!pp) return;
    try {
        await postsStore.updateAutomationPlatformPost(postId, platform, { enabled: !pp.enabled });
    } catch {
        toast.error(t('posts.errors.saveFailed'));
    }
}

// Tags
async function addTag({ postId, platform, tag }) {
    const post = posts.value.find(p => p.id === postId);
    const pp = post?.platform_posts?.find(p => p.platform === platform);
    if (!pp) return;
    const current = pp.hashtags || [];
    if (current.includes(tag)) return;
    try {
        await postsStore.updateAutomationPlatformPost(postId, platform, {
            hashtags: [...current, tag],
        });
    } catch {
        toast.error(t('posts.errors.saveFailed'));
    }
}

async function removeTag({ postId, platform, tag }) {
    const post = posts.value.find(p => p.id === postId);
    const pp = post?.platform_posts?.find(p => p.platform === platform);
    if (!pp) return;
    const updated = (pp.hashtags || []).filter(t => t !== tag);
    try {
        await postsStore.updateAutomationPlatformPost(postId, platform, {
            hashtags: updated,
        });
    } catch {
        toast.error(t('posts.errors.saveFailed'));
    }
}

// Media upload
async function uploadMedia({ postId, file }) {
    if (!file.type.startsWith('image/')) return;
    if (file.size > 10 * 1024 * 1024) {
        toast.error(t('postAutomation.row.uploadFailed'));
        return;
    }
    try {
        await postsStore.uploadMedia(postId, file);
        toast.success(t('postAutomation.row.uploadSuccess'));
        // Refresh the single post to update media URLs in automation list
        fetchPosts(pagination.value.currentPage);
    } catch {
        toast.error(t('postAutomation.row.uploadFailed'));
    }
}

async function deleteMedia({ postId, mediaId }) {
    try {
        await postsStore.deleteMedia(mediaId);
        fetchPosts(pagination.value.currentPage);
    } catch {
        toast.error(t('postAutomation.row.uploadFailed'));
    }
}

// Reschedule
async function reschedulePost({ postId, scheduledAt }) {
    try {
        await postsStore.reschedulePost(postId, scheduledAt);
        postsStore.updateAutomationPost(postId, { scheduled_at: scheduledAt });
        toast.success(t('postAutomation.row.scheduleChanged'));
    } catch {
        toast.error(t('posts.errors.saveFailed'));
    }
}

// Bulk actions
async function bulkGenerateText() {
    if (!selectedIds.value.length) return;
    bulkGeneratingText.value = true;
    try {
        const result = await postsStore.bulkGenerateText(selectedIds.value);
        toast.success(t('postAutomation.success.bulkTextGenerated', {
            success: result.success,
            total: selectedIds.value.length,
        }));
        selectedIds.value = [];
        fetchPosts(pagination.value.currentPage);
        fetchStats();
    } catch {
        toast.error(t('postAutomation.errors.generateFailed'));
    } finally {
        bulkGeneratingText.value = false;
    }
}

async function bulkGenerateImage() {
    if (!selectedIds.value.length) return;
    bulkGeneratingImage.value = true;
    try {
        const result = await postsStore.bulkGenerateImagePrompt(selectedIds.value);
        toast.success(t('postAutomation.success.bulkImageGenerated', {
            success: result.success,
            total: selectedIds.value.length,
        }));
        selectedIds.value = [];
        fetchPosts(pagination.value.currentPage);
    } catch {
        toast.error(t('postAutomation.errors.imageFailed'));
    } finally {
        bulkGeneratingImage.value = false;
    }
}

async function bulkApprove() {
    if (!selectedIds.value.length) return;
    try {
        await postsStore.batchApprove(selectedIds.value);
        toast.success(t('postAutomation.success.approved'));
        selectedIds.value = [];
        fetchPosts(pagination.value.currentPage);
        fetchStats();
    } catch {
        toast.error(t('posts.errors.saveFailed'));
    }
}

// Page change
function onPageChange(page) {
    fetchPosts(page);
}

// Watchers
let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchPosts(1), 300);
});

watch(statusFilter, () => fetchPosts(1));

watch(() => brandsStore.currentBrand?.id, () => {
    fetchPosts(1);
    fetchStats();
});

onMounted(() => {
    fetchPosts();
    fetchStats();
});
</script>

<template>
    <div class="w-full px-4 sm:px-6 py-6 sm:py-8">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">
                {{ t('postAutomation.title') }}
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ t('postAutomation.subtitle') }}
            </p>
        </div>

        <!-- Stats Bar -->
        <AutomationStatsBar
            :stats="stats"
            :loading="statsLoading"
            @filter="filterByStatus"
        />

        <!-- Status Tabs -->
        <AutomationStatusTabs
            :active-status="statusFilter"
            :stats="stats"
            @update:active-status="filterByStatus"
        />

        <!-- Toolbar -->
        <AutomationToolbar
            v-model="search"
            @settings="showWebhookSettings = true"
            @text-prompt="showTextPrompt = true"
            @image-prompt="showImagePrompt = true"
            @refresh="refresh"
        />

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-20">
            <LoadingSpinner size="lg" />
        </div>

        <!-- Empty State -->
        <AutomationEmptyState v-else-if="!posts.length" />

        <!-- Content -->
        <template v-else>
            <!-- Desktop Table -->
            <AutomationPostTable
                :posts="posts"
                :selected-ids="selectedIds"
                :generating-text="postsStore.generatingText"
                :generating-image="postsStore.generatingImage"
                :webhook-publishing="postsStore.webhookPublishing"
                :platform-colors="platformColors"
                @toggle-select="toggleSelect"
                @toggle-select-all="toggleSelectAll"
                @generate-text="generateText"
                @generate-image="generateImagePrompt"
                @approve="approvePost"
                @publish="publishPost"
                @preview="openPreview"
                @edit="openEditor"
                @update-field="updateField"
                @toggle-platform="togglePlatform"
                @add-tag="addTag"
                @remove-tag="removeTag"
                @upload-media="uploadMedia"
                @delete-media="deleteMedia"
                @reschedule="reschedulePost"
            />

            <!-- Mobile Cards -->
            <div class="lg:hidden space-y-3">
                <AutomationPostCard
                    v-for="post in posts"
                    :key="post.id"
                    :post="post"
                    :selected="selectedIds.includes(post.id)"
                    :generating-text="!!postsStore.generatingText[post.id]"
                    :generating-image="!!postsStore.generatingImage[post.id]"
                    :publishing="!!postsStore.webhookPublishing[post.id]"
                    :platform-colors="platformColors"
                    @toggle-select="toggleSelect(post.id)"
                    @generate-text="generateText(post.id)"
                    @generate-image="generateImagePrompt(post.id)"
                    @approve="approvePost(post.id)"
                    @publish="publishPost(post.id)"
                    @preview="openPreview(post)"
                    @edit="openEditor(post.id)"
                    @update-field="updateField"
                    @toggle-platform="togglePlatform"
                    @add-tag="addTag"
                    @remove-tag="removeTag"
                    @upload-media="uploadMedia"
                    @delete-media="deleteMedia"
                    @reschedule="reschedulePost"
                />
            </div>

            <!-- Pagination -->
            <AutomationPagination
                :current-page="pagination.currentPage"
                :last-page="pagination.lastPage"
                :total="pagination.total"
                @update:current-page="onPageChange"
            />
        </template>

        <!-- Floating Bulk Actions Bar -->
        <AutomationBulkBar
            :count="selectedIds.length"
            :bulk-generating-text="bulkGeneratingText"
            :bulk-generating-image="bulkGeneratingImage"
            @bulk-generate-text="bulkGenerateText"
            @bulk-generate-image="bulkGenerateImage"
            @bulk-approve="bulkApprove"
            @clear="clearSelection"
        />

        <!-- Modals -->
        <WebhookSettingsModal
            :show="showWebhookSettings"
            @close="showWebhookSettings = false"
        />
        <SystemPromptModal
            :show="showTextPrompt"
            type="text"
            @close="showTextPrompt = false"
        />
        <SystemPromptModal
            :show="showImagePrompt"
            type="image"
            @close="showImagePrompt = false"
        />
        <PostPreviewModal
            :show="showPreview"
            :post="previewPost"
            @close="showPreview = false"
        />
    </div>
</template>
