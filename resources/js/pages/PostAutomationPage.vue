<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { usePostsStore } from '@/stores/posts';
import { useBrandsStore } from '@/stores/brands';
import { useToast } from '@/composables/useToast';
import WebhookSettingsModal from '@/components/automation/WebhookSettingsModal.vue';
import PostPreviewModal from '@/components/automation/PostPreviewModal.vue';

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
const showPreview = ref(false);
const previewPost = ref(null);
const editingField = ref(null); // { postId, field }
const editingValue = ref('');
const bulkGeneratingText = ref(false);
const bulkGeneratingImage = ref(false);
const newTagInput = ref({}); // { [postId-platform]: string }
const showTagInput = ref(null); // 'postId-platform'

const platformColors = {
    facebook: { bg: 'bg-blue-100', text: 'text-blue-700', dot: 'bg-blue-500' },
    instagram: { bg: 'bg-pink-100', text: 'text-pink-700', dot: 'bg-pink-500' },
    youtube: { bg: 'bg-red-100', text: 'text-red-700', dot: 'bg-red-500' },
};

// Computed
const posts = computed(() => postsStore.automationPosts);
const pagination = computed(() => postsStore.automationPagination);
const loading = computed(() => postsStore.loading);
const allSelected = computed(() => posts.value.length > 0 && selectedIds.value.length === posts.value.length);
const someSelected = computed(() => selectedIds.value.length > 0 && selectedIds.value.length < posts.value.length);

const statuses = [
    { value: '', label: t('postAutomation.filters.allStatuses') },
    { value: 'draft', label: t('posts.status.draft') },
    { value: 'pending_approval', label: t('posts.status.pending_approval') },
    { value: 'approved', label: t('posts.status.approved') },
    { value: 'scheduled', label: t('posts.status.scheduled') },
    { value: 'published', label: t('posts.status.published') },
    { value: 'failed', label: t('posts.status.failed') },
];

// Methods
function fetchPosts(page = 1) {
    const params = { page, per_page: 20 };
    if (search.value) params.search = search.value;
    if (statusFilter.value) params.status = statusFilter.value;
    if (brandsStore.currentBrand?.id) params.brand_id = brandsStore.currentBrand.id;
    postsStore.fetchAutomationPosts(params).catch(() => {
        toast.error(t('postAutomation.errors.fetchFailed'));
    });
}

function statusColor(status) {
    const colors = {
        draft: 'bg-gray-100 text-gray-700',
        pending_approval: 'bg-yellow-100 text-yellow-700',
        approved: 'bg-green-100 text-green-700',
        scheduled: 'bg-blue-100 text-blue-700',
        published: 'bg-purple-100 text-purple-700',
        failed: 'bg-red-100 text-red-700',
    };
    return colors[status] || 'bg-gray-100 text-gray-700';
}

function toggleSelectAll() {
    if (allSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = posts.value.map(p => p.id);
    }
}

function toggleSelect(id) {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(idx, 1);
    }
}

// Inline editing
function startEditing(postId, field, currentValue) {
    editingField.value = { postId, field };
    editingValue.value = currentValue || '';
}

async function saveEditing() {
    if (!editingField.value) return;
    const { postId, field } = editingField.value;
    try {
        await postsStore.updatePost(postId, { [field]: editingValue.value });
        postsStore.updateAutomationPost(postId, { [field]: editingValue.value });
    } catch {
        toast.error(t('posts.errors.saveFailed'));
    }
    editingField.value = null;
    editingValue.value = '';
}

function cancelEditing() {
    editingField.value = null;
    editingValue.value = '';
}

function isEditing(postId, field) {
    return editingField.value?.postId === postId && editingField.value?.field === field;
}

// Actions
function extractError(err, fallbackKey) {
    const serverError = err?.response?.data?.error || err?.response?.data?.message;
    if (serverError) {
        return `${t(fallbackKey)}: ${serverError}`;
    }
    return t(fallbackKey);
}

async function generateText(postId) {
    try {
        await postsStore.generatePostText(postId);
        toast.success(t('postAutomation.success.textGenerated'));
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
    } catch (err) {
        toast.error(extractError(err, 'posts.errors.saveFailed'), 5000);
    }
}

async function publishPost(postId) {
    try {
        await postsStore.webhookPublishPost(postId);
        toast.success(t('postAutomation.success.published'));
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
    } catch {
        toast.error(t('posts.errors.saveFailed'));
    }
}

function truncate(text, length = 80) {
    if (!text) return '';
    return text.length > length ? text.substring(0, length) + '...' : text;
}

function formatDate(dateStr) {
    if (!dateStr) return null;
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return null;
    return d.toLocaleDateString(undefined, { day: '2-digit', month: '2-digit', year: 'numeric' })
        + ' ' + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

// Platform toggle
async function togglePlatform(post, platform) {
    const pp = post.platform_posts?.find(p => p.platform === platform);
    if (!pp) return;
    try {
        await postsStore.updateAutomationPlatformPost(post.id, platform, { enabled: !pp.enabled });
    } catch {
        toast.error(t('posts.errors.saveFailed'));
    }
}

// Tags
function getPostTags(post) {
    if (!post.platform_posts) return [];
    const tags = [];
    for (const pp of post.platform_posts) {
        if (pp.enabled && pp.hashtags?.length) {
            for (const tag of pp.hashtags) {
                if (!tags.includes(tag)) tags.push(tag);
            }
        }
    }
    return tags;
}

function getTagsPerPlatform(post) {
    if (!post.platform_posts) return [];
    return post.platform_posts
        .filter(pp => pp.enabled)
        .map(pp => ({ platform: pp.platform, hashtags: pp.hashtags || [] }));
}

function startAddingTag(postId, platform) {
    const key = `${postId}-${platform}`;
    showTagInput.value = key;
    newTagInput.value[key] = '';
}

async function addTag(post, platform) {
    const key = `${post.id}-${platform}`;
    const tag = (newTagInput.value[key] || '').trim().replace(/^#/, '');
    if (!tag) {
        showTagInput.value = null;
        return;
    }
    const pp = post.platform_posts?.find(p => p.platform === platform);
    if (!pp) return;
    const current = pp.hashtags || [];
    if (current.includes(tag)) {
        showTagInput.value = null;
        return;
    }
    try {
        await postsStore.updateAutomationPlatformPost(post.id, platform, {
            hashtags: [...current, tag],
        });
    } catch {
        toast.error(t('posts.errors.saveFailed'));
    }
    showTagInput.value = null;
    newTagInput.value[key] = '';
}

async function removeTag(post, platform, tagToRemove) {
    const pp = post.platform_posts?.find(p => p.platform === platform);
    if (!pp) return;
    const updated = (pp.hashtags || []).filter(t => t !== tagToRemove);
    try {
        await postsStore.updateAutomationPlatformPost(post.id, platform, {
            hashtags: updated,
        });
    } catch {
        toast.error(t('posts.errors.saveFailed'));
    }
}

// Watchers
let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchPosts(1), 300);
});

watch(statusFilter, () => fetchPosts(1));

watch(() => brandsStore.currentBrand?.id, () => fetchPosts(1));

onMounted(() => {
    fetchPosts();
});
</script>

<template>
    <div class="w-full px-4 sm:px-6 py-6 sm:py-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ t('postAutomation.title') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('postAutomation.subtitle') }}
                </p>
            </div>
            <button
                @click="showWebhookSettings = true"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                {{ t('automation.settings') }}
            </button>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <div class="flex-1">
                <input
                    v-model="search"
                    type="text"
                    :placeholder="t('postAutomation.filters.searchPlaceholder')"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                />
            </div>
            <select
                v-model="statusFilter"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            >
                <option v-for="s in statuses" :key="s.value" :value="s.value">
                    {{ s.label }}
                </option>
            </select>
        </div>

        <!-- Bulk Actions Bar -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="selectedIds.length"
                class="mb-4 flex flex-wrap items-center gap-2 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3"
            >
                <span class="text-sm text-blue-700 font-medium">
                    {{ selectedIds.length }} {{ t('common.selected') }}
                </span>
                <div class="flex gap-2 ml-auto">
                    <button
                        @click="bulkGenerateText"
                        :disabled="bulkGeneratingText"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ bulkGeneratingText ? t('postAutomation.actions.generatingText') : t('postAutomation.actions.bulkGenerateText') }}
                    </button>
                    <button
                        @click="bulkGenerateImage"
                        :disabled="bulkGeneratingImage"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 disabled:opacity-50"
                    >
                        {{ bulkGeneratingImage ? t('postAutomation.actions.generatingImage') : t('postAutomation.actions.bulkGenerateImage') }}
                    </button>
                    <button
                        @click="bulkApprove"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded-lg hover:bg-green-700"
                    >
                        {{ t('postAutomation.actions.bulkApprove') }}
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-20">
            <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
        </div>

        <!-- Empty State -->
        <div v-else-if="!posts.length" class="text-center py-20">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="mt-4 text-gray-500 text-sm">
                {{ t('postAutomation.empty') }}
            </p>
        </div>

        <!-- Desktop Table -->
        <div v-else class="hidden lg:block overflow-x-auto">
            <table class="w-full border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-10 px-3 py-3">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                :indeterminate="someSelected"
                                @change="toggleSelectAll"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ t('postAutomation.table.topic') }}
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                            {{ t('postAutomation.table.status') }}
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ t('postAutomation.table.content') }}
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                            {{ t('postAutomation.table.image') }}
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                            {{ t('postAutomation.table.platforms') }}
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-48">
                            {{ t('postAutomation.table.hashtags') }}
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-36">
                            {{ t('postAutomation.table.scheduledAt') }}
                        </th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-64">
                            {{ t('postAutomation.table.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr
                        v-for="post in posts"
                        :key="post.id"
                        class="hover:bg-gray-50 transition-colors"
                    >
                        <!-- Checkbox -->
                        <td class="px-3 py-3">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(post.id)"
                                @change="toggleSelect(post.id)"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                        </td>

                        <!-- Topic (inline editable) -->
                        <td class="px-3 py-3">
                            <div v-if="isEditing(post.id, 'title')">
                                <textarea
                                    v-model="editingValue"
                                    @blur="saveEditing()"
                                    @keydown.escape="cancelEditing()"
                                    rows="2"
                                    class="w-full rounded border border-blue-400 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-y"
                                    autofocus
                                />
                            </div>
                            <div
                                v-else
                                @click="startEditing(post.id, 'title', post.title)"
                                class="text-sm text-gray-900 cursor-pointer hover:text-blue-600 line-clamp-2"
                                :title="post.title"
                            >
                                {{ post.title || '—' }}
                            </div>
                        </td>

                        <!-- Status -->
                        <td class="px-3 py-3">
                            <span
                                class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="statusColor(post.status)"
                            >
                                {{ post.status_label }}
                            </span>
                        </td>

                        <!-- Content (inline editable) -->
                        <td class="px-3 py-3">
                            <div v-if="isEditing(post.id, 'main_caption')">
                                <textarea
                                    v-model="editingValue"
                                    @blur="saveEditing()"
                                    @keydown.escape="cancelEditing()"
                                    rows="4"
                                    class="w-full rounded border border-blue-400 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-y"
                                    autofocus
                                />
                            </div>
                            <div
                                v-else
                                @click="startEditing(post.id, 'main_caption', post.main_caption)"
                                class="text-sm text-gray-600 cursor-pointer hover:text-blue-600 line-clamp-3"
                                :title="post.main_caption"
                            >
                                {{ truncate(post.main_caption, 150) || '—' }}
                            </div>
                        </td>

                        <!-- Image -->
                        <td class="px-3 py-3">
                            <div v-if="post.first_media_url" class="relative group">
                                <img
                                    :src="post.first_media_url"
                                    :alt="post.title"
                                    class="w-16 h-16 object-cover rounded-lg border border-gray-200"
                                />
                                <span v-if="post.media_count > 1" class="absolute -top-1 -right-1 bg-gray-700 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                    {{ post.media_count }}
                                </span>
                            </div>
                            <span v-else class="text-gray-300 text-sm">—</span>
                        </td>

                        <!-- Platforms -->
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="pp in post.platform_posts"
                                    :key="pp.platform"
                                    @click="togglePlatform(post, pp.platform)"
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer"
                                    :class="pp.enabled
                                        ? `${platformColors[pp.platform]?.bg || 'bg-gray-100'} ${platformColors[pp.platform]?.text || 'text-gray-700'}`
                                        : 'bg-gray-50 text-gray-400 line-through'
                                    "
                                    :title="pp.platform_label"
                                >
                                    <span
                                        class="w-2 h-2 rounded-full shrink-0"
                                        :class="pp.enabled ? (platformColors[pp.platform]?.dot || 'bg-gray-400') : 'bg-gray-300'"
                                    />
                                    {{ pp.platform_label }}
                                </button>
                                <span v-if="!post.platform_posts?.length" class="text-gray-300 text-xs">
                                    {{ t('postAutomation.table.noPlatforms') }}
                                </span>
                            </div>
                        </td>

                        <!-- Tags -->
                        <td class="px-3 py-3">
                            <div class="space-y-1.5">
                                <template v-for="ppData in getTagsPerPlatform(post)" :key="ppData.platform">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span
                                            class="text-[10px] font-semibold uppercase shrink-0"
                                            :class="platformColors[ppData.platform]?.text || 'text-gray-500'"
                                        >
                                            {{ ppData.platform.charAt(0).toUpperCase() }}
                                        </span>
                                        <span
                                            v-for="tag in ppData.hashtags"
                                            :key="tag"
                                            class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-700"
                                        >
                                            #{{ tag }}
                                            <button
                                                @click="removeTag(post, ppData.platform, tag)"
                                                class="text-gray-400 hover:text-red-500 ml-0.5"
                                            >
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </span>
                                        <!-- Add tag input -->
                                        <div v-if="showTagInput === `${post.id}-${ppData.platform}`" class="inline-flex">
                                            <input
                                                v-model="newTagInput[`${post.id}-${ppData.platform}`]"
                                                @keydown.enter.prevent="addTag(post, ppData.platform)"
                                                @keydown.escape="showTagInput = null"
                                                @blur="addTag(post, ppData.platform)"
                                                :placeholder="t('postAutomation.table.tagPlaceholder')"
                                                class="w-20 px-1.5 py-0.5 text-[11px] border border-blue-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                autofocus
                                            />
                                        </div>
                                        <button
                                            v-else
                                            @click="startAddingTag(post.id, ppData.platform)"
                                            class="text-gray-400 hover:text-blue-600 transition-colors"
                                            :title="t('postAutomation.table.addTag')"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        </button>
                                    </div>
                                </template>
                                <span v-if="!getTagsPerPlatform(post).length" class="text-gray-300 text-xs">
                                    {{ t('postAutomation.table.noTags') }}
                                </span>
                            </div>
                        </td>

                        <!-- Scheduled At -->
                        <td class="px-3 py-3">
                            <span v-if="formatDate(post.scheduled_at)" class="text-sm text-gray-600 whitespace-nowrap">
                                {{ formatDate(post.scheduled_at) }}
                            </span>
                            <span v-else class="text-gray-300 text-sm">—</span>
                        </td>

                        <!-- Actions -->
                        <td class="px-3 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <button
                                    @click="generateText(post.id)"
                                    :disabled="postsStore.generatingText[post.id]"
                                    class="px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded hover:bg-blue-100 disabled:opacity-50 whitespace-nowrap"
                                    :title="t('postAutomation.actions.generateText')"
                                >
                                    {{ postsStore.generatingText[post.id] ? '...' : t('postAutomation.actions.generateText') }}
                                </button>
                                <button
                                    @click="generateImagePrompt(post.id)"
                                    :disabled="postsStore.generatingImage[post.id]"
                                    class="px-2 py-1 text-xs font-medium text-purple-700 bg-purple-50 rounded hover:bg-purple-100 disabled:opacity-50 whitespace-nowrap"
                                    :title="t('postAutomation.actions.generateImage')"
                                >
                                    {{ postsStore.generatingImage[post.id] ? '...' : t('postAutomation.actions.generateImage') }}
                                </button>
                                <button
                                    v-if="post.status === 'draft' || post.status === 'pending_approval'"
                                    @click="approvePost(post.id)"
                                    class="px-2 py-1 text-xs font-medium text-green-700 bg-green-50 rounded hover:bg-green-100 whitespace-nowrap"
                                >
                                    {{ t('postAutomation.actions.approve') }}
                                </button>
                                <button
                                    v-if="post.status === 'approved' || post.status === 'scheduled'"
                                    @click="publishPost(post.id)"
                                    :disabled="postsStore.webhookPublishing[post.id]"
                                    class="px-2 py-1 text-xs font-medium text-orange-700 bg-orange-50 rounded hover:bg-orange-100 disabled:opacity-50 whitespace-nowrap"
                                >
                                    {{ postsStore.webhookPublishing[post.id] ? '...' : t('postAutomation.actions.publish') }}
                                </button>
                                <button
                                    @click="openPreview(post)"
                                    class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-50 rounded hover:bg-gray-100"
                                    :title="t('postAutomation.actions.preview')"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                                <button
                                    @click="openEditor(post.id)"
                                    class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-50 rounded hover:bg-gray-100"
                                    :title="t('postAutomation.actions.edit')"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div v-if="!loading && posts.length" class="lg:hidden space-y-3">
            <div
                v-for="post in posts"
                :key="post.id"
                class="bg-white border border-gray-200 rounded-lg p-4 space-y-3"
            >
                <!-- Header row -->
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <input
                            type="checkbox"
                            :checked="selectedIds.includes(post.id)"
                            @change="toggleSelect(post.id)"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 flex-shrink-0"
                        />
                        <div
                            @click="startEditing(post.id, 'title', post.title)"
                            class="text-sm font-medium text-gray-900 truncate cursor-pointer"
                        >
                            {{ post.title || '—' }}
                        </div>
                    </div>
                    <span
                        class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0"
                        :class="statusColor(post.status)"
                    >
                        {{ post.status_label }}
                    </span>
                </div>

                <!-- Inline edit for title on mobile -->
                <div v-if="isEditing(post.id, 'title')">
                    <textarea
                        v-model="editingValue"
                        @blur="saveEditing()"
                        @keydown.escape="cancelEditing()"
                        rows="2"
                        class="w-full rounded border border-blue-400 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-y"
                        autofocus
                    />
                </div>

                <!-- Content -->
                <div v-if="post.main_caption" class="text-sm text-gray-600 line-clamp-3">
                    {{ post.main_caption }}
                </div>

                <!-- Image -->
                <div v-if="post.first_media_url" class="relative inline-block">
                    <img
                        :src="post.first_media_url"
                        :alt="post.title"
                        class="w-20 h-20 object-cover rounded-lg border border-gray-200"
                    />
                    <span v-if="post.media_count > 1" class="absolute -top-1 -right-1 bg-gray-700 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        {{ post.media_count }}
                    </span>
                </div>

                <!-- Platforms -->
                <div v-if="post.platform_posts?.length" class="flex flex-wrap gap-1.5">
                    <button
                        v-for="pp in post.platform_posts"
                        :key="pp.platform"
                        @click="togglePlatform(post, pp.platform)"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium transition-colors"
                        :class="pp.enabled
                            ? `${platformColors[pp.platform]?.bg || 'bg-gray-100'} ${platformColors[pp.platform]?.text || 'text-gray-700'}`
                            : 'bg-gray-50 text-gray-400 line-through'
                        "
                    >
                        <span
                            class="w-2 h-2 rounded-full shrink-0"
                            :class="pp.enabled ? (platformColors[pp.platform]?.dot || 'bg-gray-400') : 'bg-gray-300'"
                        />
                        {{ pp.platform_label }}
                    </button>
                </div>

                <!-- Tags -->
                <div v-if="getTagsPerPlatform(post).length" class="space-y-1.5">
                    <template v-for="ppData in getTagsPerPlatform(post)" :key="ppData.platform">
                        <div class="flex flex-wrap items-center gap-1">
                            <span
                                class="text-[10px] font-semibold uppercase shrink-0"
                                :class="platformColors[ppData.platform]?.text || 'text-gray-500'"
                            >
                                {{ ppData.platform.charAt(0).toUpperCase() }}
                            </span>
                            <span
                                v-for="tag in ppData.hashtags"
                                :key="tag"
                                class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-700"
                            >
                                #{{ tag }}
                                <button
                                    @click="removeTag(post, ppData.platform, tag)"
                                    class="text-gray-400 hover:text-red-500 ml-0.5"
                                >
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </span>
                            <div v-if="showTagInput === `${post.id}-${ppData.platform}`" class="inline-flex">
                                <input
                                    v-model="newTagInput[`${post.id}-${ppData.platform}`]"
                                    @keydown.enter.prevent="addTag(post, ppData.platform)"
                                    @keydown.escape="showTagInput = null"
                                    @blur="addTag(post, ppData.platform)"
                                    :placeholder="t('postAutomation.table.tagPlaceholder')"
                                    class="w-20 px-1.5 py-0.5 text-[11px] border border-blue-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    autofocus
                                />
                            </div>
                            <button
                                v-else
                                @click="startAddingTag(post.id, ppData.platform)"
                                class="text-gray-400 hover:text-blue-600 transition-colors"
                                :title="t('postAutomation.table.addTag')"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Scheduled At -->
                <div v-if="formatDate(post.scheduled_at)" class="flex items-center gap-1.5 text-sm text-gray-500">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    {{ formatDate(post.scheduled_at) }}
                </div>

                <!-- Actions -->
                <div class="flex flex-wrap gap-1.5 pt-2 border-t border-gray-100">
                    <button
                        @click="generateText(post.id)"
                        :disabled="postsStore.generatingText[post.id]"
                        class="px-2.5 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 disabled:opacity-50"
                    >
                        {{ postsStore.generatingText[post.id] ? t('postAutomation.actions.generatingText') : t('postAutomation.actions.generateText') }}
                    </button>
                    <button
                        @click="generateImagePrompt(post.id)"
                        :disabled="postsStore.generatingImage[post.id]"
                        class="px-2.5 py-1.5 text-xs font-medium text-purple-700 bg-purple-50 rounded-lg hover:bg-purple-100 disabled:opacity-50"
                    >
                        {{ postsStore.generatingImage[post.id] ? t('postAutomation.actions.generatingImage') : t('postAutomation.actions.generateImage') }}
                    </button>
                    <button
                        v-if="post.status === 'draft' || post.status === 'pending_approval'"
                        @click="approvePost(post.id)"
                        class="px-2.5 py-1.5 text-xs font-medium text-green-700 bg-green-50 rounded-lg hover:bg-green-100"
                    >
                        {{ t('postAutomation.actions.approve') }}
                    </button>
                    <button
                        v-if="post.status === 'approved' || post.status === 'scheduled'"
                        @click="publishPost(post.id)"
                        :disabled="postsStore.webhookPublishing[post.id]"
                        class="px-2.5 py-1.5 text-xs font-medium text-orange-700 bg-orange-50 rounded-lg hover:bg-orange-100 disabled:opacity-50"
                    >
                        {{ postsStore.webhookPublishing[post.id] ? t('postAutomation.actions.publishing') : t('postAutomation.actions.publish') }}
                    </button>
                    <button
                        @click="openPreview(post)"
                        class="px-2.5 py-1.5 text-xs font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100"
                    >
                        {{ t('postAutomation.actions.preview') }}
                    </button>
                    <button
                        @click="openEditor(post.id)"
                        class="px-2.5 py-1.5 text-xs font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100"
                    >
                        {{ t('postAutomation.actions.edit') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.lastPage > 1" class="mt-6 flex items-center justify-between">
            <p class="text-sm text-gray-500">
                {{ pagination.total }} {{ t('posts.title').toLowerCase() }}
            </p>
            <div class="flex gap-1">
                <button
                    v-for="page in pagination.lastPage"
                    :key="page"
                    @click="fetchPosts(page)"
                    :class="[
                        'px-3 py-1.5 text-sm rounded-lg',
                        page === pagination.currentPage
                            ? 'bg-blue-600 text-white'
                            : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50',
                    ]"
                >
                    {{ page }}
                </button>
            </div>
        </div>

        <!-- Modals -->
        <WebhookSettingsModal
            :show="showWebhookSettings"
            @close="showWebhookSettings = false"
        />
        <PostPreviewModal
            :show="showPreview"
            :post="previewPost"
            @close="showPreview = false"
        />
    </div>
</template>
