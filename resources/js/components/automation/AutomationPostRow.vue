<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useConfirm } from '@/composables/useConfirm';
import PostStatusBadge from '@/components/posts/PostStatusBadge.vue';
import AutomationRowActions from './AutomationRowActions.vue';
import DateTimeInput from '@/components/common/DateTimeInput.vue';

const props = defineProps({
    post: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    expanded: { type: Boolean, default: false },
    generatingText: { type: Boolean, default: false },
    generatingImageDescription: { type: Boolean, default: false },
    generatingImage: { type: Boolean, default: false },
    publishing: { type: Boolean, default: false },
    platformColors: { type: Object, required: true },
});

const emit = defineEmits([
    'toggle-select',
    'toggle-expand',
    'generate-text',
    'generate-image-description',
    'generate-image',
    'approve',
    'publish',
    'preview',
    'edit',
    'update-field',
    'toggle-platform',
    'add-tag',
    'remove-tag',
    'upload-media',
    'delete-media',
    'reschedule',
]);

const { t } = useI18n();
const { confirm } = useConfirm();

// Inline editing
const editingField = ref(null);
const editingValue = ref('');
const showTagInput = ref(null);
const newTagValue = ref('');
const uploading = ref(false);
const uploadProgress = ref(0);
const dragOver = ref(false);

function handleFileSelect(event) {
    const file = event.target.files?.[0];
    if (file) {
        emit('upload-media', { postId: props.post.id, file });
    }
    event.target.value = '';
}

function handleDrop(event) {
    event.preventDefault();
    dragOver.value = false;
    const file = event.dataTransfer.files?.[0];
    if (file) {
        emit('upload-media', { postId: props.post.id, file });
    }
}

async function confirmDeleteMedia(mediaId) {
    const confirmed = await confirm({
        title: t('common.deleteConfirmTitle'),
        message: t('postAutomation.row.deleteConfirm'),
        confirmText: t('common.delete'),
        variant: 'danger',
    });
    if (confirmed) {
        emit('delete-media', { postId: props.post.id, mediaId });
    }
}

// Schedule editing
const editingSchedule = ref(false);
const scheduleValue = ref('');

function startEditingSchedule() {
    scheduleValue.value = props.post.scheduled_at || '';
    editingSchedule.value = true;
}

function onScheduleChange(value) {
    scheduleValue.value = value;
    emit('reschedule', { postId: props.post.id, scheduledAt: value });
    editingSchedule.value = false;
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

function startEditing(field, currentValue) {
    editingField.value = field;
    editingValue.value = currentValue || '';
}

function saveEditing() {
    if (!editingField.value) return;
    emit('update-field', { postId: props.post.id, field: editingField.value, value: editingValue.value });
    editingField.value = null;
    editingValue.value = '';
}

function cancelEditing() {
    editingField.value = null;
    editingValue.value = '';
}

const enabledPlatforms = computed(() => {
    return (props.post.platform_posts || []).filter(pp => pp.enabled);
});

const tagsPerPlatform = computed(() => {
    return (props.post.platform_posts || [])
        .filter(pp => pp.enabled)
        .map(pp => ({ platform: pp.platform, platform_label: pp.platform_label, hashtags: pp.hashtags || [] }));
});

function startAddTag(platform) {
    showTagInput.value = platform;
    newTagValue.value = '';
}

function submitTag(platform) {
    const tag = (newTagValue.value || '').trim().replace(/^#/, '');
    if (tag) {
        emit('add-tag', { postId: props.post.id, platform, tag });
    }
    showTagInput.value = null;
    newTagValue.value = '';
}
</script>

<template>
    <!-- Collapsed Row -->
    <tr
        class="group hover:bg-gray-50 transition-colors cursor-pointer"
        :class="{ 'bg-blue-50/40': selected }"
        @click="emit('toggle-expand')"
    >
        <!-- Checkbox -->
        <td class="px-3 py-3 w-10" @click.stop>
            <input
                type="checkbox"
                :checked="selected"
                @change="emit('toggle-select')"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
        </td>

        <!-- Post summary: thumbnail + title + excerpt -->
        <td class="px-3 py-3">
            <div class="flex items-center gap-3 min-w-0">
                <div v-if="post.first_media_url" class="shrink-0">
                    <img
                        :src="post.first_media_url"
                        :alt="post.title"
                        class="w-8 h-8 rounded object-cover border border-gray-200"
                    />
                </div>
                <div v-else class="shrink-0 w-8 h-8 rounded bg-gray-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm font-medium text-gray-900 truncate">
                            {{ post.title || '—' }}
                        </span>
                        <!-- Edit affordance icon -->
                        <svg class="w-3.5 h-3.5 text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </div>
                    <p class="text-xs text-gray-500 truncate mt-0.5">
                        {{ truncate(post.main_caption, 60) || '—' }}
                    </p>
                </div>
            </div>
        </td>

        <!-- Status -->
        <td class="px-3 py-3">
            <PostStatusBadge :status="post.status" />
        </td>

        <!-- Platforms (dots only) -->
        <td class="px-3 py-3">
            <div class="flex items-center gap-1">
                <span
                    v-for="pp in post.platform_posts"
                    :key="pp.platform"
                    class="w-2.5 h-2.5 rounded-full shrink-0"
                    :class="pp.enabled ? (platformColors[pp.platform]?.dot || 'bg-gray-400') : 'bg-gray-200'"
                    :title="pp.platform_label"
                />
                <span v-if="!post.platform_posts?.length" class="text-gray-300 text-xs">—</span>
            </div>
        </td>

        <!-- Scheduled -->
        <td class="px-3 py-3">
            <span v-if="formatDate(post.scheduled_at)" class="text-sm text-gray-600 whitespace-nowrap">
                {{ formatDate(post.scheduled_at) }}
            </span>
            <span v-else class="text-gray-300 text-sm">—</span>
        </td>

        <!-- Actions -->
        <td class="px-3 py-3 text-right" @click.stop>
            <AutomationRowActions
                :post="post"
                :generating-text="generatingText"
                :generating-image-description="generatingImageDescription"
                :generating-image="generatingImage"
                :publishing="publishing"
                @generate-text="emit('generate-text')"
                @generate-image-description="emit('generate-image-description')"
                @generate-image="emit('generate-image')"
                @approve="emit('approve')"
                @publish="emit('publish')"
                @preview="emit('preview')"
                @edit="emit('edit')"
            />
        </td>
    </tr>

    <!-- Expanded Row -->
    <tr v-if="expanded">
        <td colspan="6" class="px-0 py-0">
            <div class="bg-gray-50 border-t border-b border-gray-200 px-6 py-5">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column: Text prompt + Content -->
                    <div class="space-y-4">
                        <!-- Text prompt (AI input) -->
                        <div>
                            <label class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1 block">
                                {{ t('postAutomation.row.textPrompt') }}
                            </label>
                            <div v-if="editingField === 'text_prompt'">
                                <textarea
                                    v-model="editingValue"
                                    @blur="saveEditing()"
                                    @keydown.escape="cancelEditing()"
                                    rows="3"
                                    class="w-full rounded-lg border border-blue-400 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-y"
                                    autofocus
                                />
                            </div>
                            <div
                                v-else
                                @click.stop="startEditing('text_prompt', post.text_prompt)"
                                class="group/edit text-sm text-gray-500 italic cursor-pointer hover:text-blue-600 flex items-start gap-1.5 min-h-[60px]"
                            >
                                <span>{{ post.text_prompt || t('postAutomation.row.noTextPrompt') }}</span>
                                <svg class="w-3.5 h-3.5 text-gray-300 opacity-0 group-hover/edit:opacity-100 transition-opacity shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </div>
                        </div>

                        <!-- Content (AI output) -->
                        <div>
                            <label class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1 block">
                                {{ t('postAutomation.row.content') }}
                            </label>
                            <div v-if="editingField === 'main_caption'">
                                <textarea
                                    v-model="editingValue"
                                    @blur="saveEditing()"
                                    @keydown.escape="cancelEditing()"
                                    rows="5"
                                    class="w-full rounded-lg border border-blue-400 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-y"
                                    autofocus
                                />
                            </div>
                            <div
                                v-else
                                @click.stop="startEditing('main_caption', post.main_caption)"
                                class="group/edit text-sm text-gray-600 cursor-pointer hover:text-blue-600 whitespace-pre-wrap flex items-start gap-1.5"
                            >
                                <span>{{ post.main_caption || t('postAutomation.row.noContent') }}</span>
                                <svg class="w-3.5 h-3.5 text-gray-300 opacity-0 group-hover/edit:opacity-100 transition-opacity shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Middle Column: Image prompt + Image -->
                    <div class="space-y-4">
                        <!-- Image prompt (AI input) -->
                        <div>
                            <label class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1 block">
                                {{ t('postAutomation.row.imagePrompt') }}
                            </label>
                            <div v-if="editingField === 'image_prompt'">
                                <textarea
                                    v-model="editingValue"
                                    @blur="saveEditing()"
                                    @keydown.escape="cancelEditing()"
                                    rows="3"
                                    class="w-full rounded-lg border border-blue-400 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-y"
                                    autofocus
                                />
                            </div>
                            <div
                                v-else
                                @click.stop="startEditing('image_prompt', post.image_prompt)"
                                class="group/edit text-sm text-gray-500 italic cursor-pointer hover:text-blue-600 flex items-start gap-1.5 min-h-[60px]"
                            >
                                <span>{{ post.image_prompt || t('postAutomation.row.noImagePrompt') }}</span>
                                <svg class="w-3.5 h-3.5 text-gray-300 opacity-0 group-hover/edit:opacity-100 transition-opacity shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </div>
                        </div>

                        <!-- Image preview + upload -->
                        <div>
                            <label class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2 block">
                                {{ t('postAutomation.table.media') }}
                            </label>
                            <div class="flex items-start gap-3">
                                <!-- Existing image -->
                                <div v-if="post.first_media_url" class="relative shrink-0">
                                    <img
                                        :src="post.first_media_url"
                                        :alt="post.title"
                                        class="w-32 h-32 object-cover rounded-lg border border-gray-200"
                                    />
                                    <span
                                        v-if="post.media_count > 1"
                                        class="absolute -top-1.5 -right-1.5 bg-gray-700 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"
                                    >
                                        {{ post.media_count }}
                                    </span>
                                    <button
                                        @click.stop="confirmDeleteMedia(post.first_media_id)"
                                        v-if="post.first_media_id"
                                        class="absolute -top-1.5 -left-1.5 w-5 h-5 rounded-full bg-red-600 text-white flex items-center justify-center hover:bg-red-500 transition-colors shadow-sm"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <!-- Upload zone (when no image) -->
                                <label
                                    v-else
                                    :for="`upload-${post.id}`"
                                    class="flex flex-col items-center justify-center w-32 h-32 rounded-lg border-2 border-dashed cursor-pointer transition-colors shrink-0"
                                    :class="dragOver ? 'border-blue-400 bg-blue-50' : 'border-gray-300 hover:border-gray-400 bg-gray-50'"
                                    @dragover.prevent="dragOver = true"
                                    @dragleave="dragOver = false"
                                    @drop.stop="handleDrop"
                                    @click.stop
                                >
                                    <svg class="w-6 h-6 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <span class="text-[10px] text-gray-500 text-center leading-tight px-1">
                                        {{ t('postAutomation.row.uploadHint') }}
                                    </span>
                                    <input
                                        :id="`upload-${post.id}`"
                                        type="file"
                                        accept="image/jpeg,image/png,image/gif,image/webp"
                                        class="hidden"
                                        @change.stop="handleFileSelect"
                                    />
                                </label>
                            </div>
                            <!-- Upload more button (when image exists) -->
                            <label
                                v-if="post.first_media_url"
                                :for="`upload-more-${post.id}`"
                                class="mt-2 inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700 cursor-pointer"
                                @click.stop
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ t('postAutomation.row.uploadImage') }}
                                <input
                                    :id="`upload-more-${post.id}`"
                                    type="file"
                                    accept="image/jpeg,image/png,image/gif,image/webp"
                                    class="hidden"
                                    @change.stop="handleFileSelect"
                                />
                            </label>
                        </div>
                    </div>

                    <!-- Right Column: Tags, Date -->
                    <div class="space-y-4">
                        <!-- Tags -->
                        <div>
                            <label class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2 block">
                                {{ t('postAutomation.row.tags') }}
                            </label>
                            <div class="space-y-2">
                                <div
                                    v-for="ppData in tagsPerPlatform"
                                    :key="ppData.platform"
                                    class="flex items-center gap-2 flex-wrap"
                                >
                                    <button
                                        @click.stop="emit('toggle-platform', { postId: post.id, platform: ppData.platform })"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium transition-colors"
                                        :class="`${platformColors[ppData.platform]?.bg || 'bg-gray-100'} ${platformColors[ppData.platform]?.text || 'text-gray-700'}`"
                                    >
                                        <span
                                            class="w-2 h-2 rounded-full"
                                            :class="platformColors[ppData.platform]?.dot || 'bg-gray-400'"
                                        />
                                        {{ ppData.platform_label }}
                                    </button>
                                    <span
                                        v-for="tag in ppData.hashtags"
                                        :key="tag"
                                        class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-700"
                                    >
                                        #{{ tag }}
                                        <button
                                            @click.stop="emit('remove-tag', { postId: post.id, platform: ppData.platform, tag })"
                                            class="text-gray-400 hover:text-red-500 ml-0.5"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </span>
                                    <!-- Add tag -->
                                    <div v-if="showTagInput === ppData.platform" class="inline-flex" @click.stop>
                                        <input
                                            v-model="newTagValue"
                                            @keydown.enter.prevent="submitTag(ppData.platform)"
                                            @keydown.escape="showTagInput = null"
                                            @blur="submitTag(ppData.platform)"
                                            :placeholder="t('postAutomation.table.tagPlaceholder')"
                                            class="w-20 px-1.5 py-0.5 text-[11px] border border-blue-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            autofocus
                                        />
                                    </div>
                                    <button
                                        v-else
                                        @click.stop="startAddTag(ppData.platform)"
                                        class="text-gray-400 hover:text-blue-600 transition-colors"
                                        :title="t('postAutomation.table.addTag')"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    </button>
                                </div>
                                <div v-if="!tagsPerPlatform.length" class="text-xs text-gray-400">
                                    {{ t('postAutomation.table.noPlatforms') }}
                                </div>
                            </div>
                        </div>

                        <!-- Schedule -->
                        <div>
                            <label class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5 block">
                                {{ t('postAutomation.row.scheduledAt') }}
                            </label>
                            <div v-if="editingSchedule" @click.stop>
                                <DateTimeInput
                                    :model-value="scheduleValue"
                                    @update:model-value="onScheduleChange"
                                />
                            </div>
                            <div
                                v-else
                                @click.stop="startEditingSchedule"
                                class="group/edit inline-flex items-center gap-1.5 text-sm cursor-pointer hover:text-blue-600 transition-colors"
                                :class="post.scheduled_at ? 'text-gray-700' : 'text-gray-400'"
                            >
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span>{{ formatDate(post.scheduled_at) || t('postAutomation.row.notScheduled') }}</span>
                                <svg class="w-3.5 h-3.5 text-gray-300 opacity-0 group-hover/edit:opacity-100 transition-opacity shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expanded row actions -->
                <div class="flex flex-wrap items-center gap-2 mt-5 pt-4 border-t border-gray-200">
                    <button
                        @click.stop="emit('generate-text')"
                        :disabled="generatingText"
                        class="px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 disabled:opacity-50 transition-colors"
                    >
                        {{ generatingText ? t('postAutomation.actions.generatingText') : t('postAutomation.actions.generateText') }}
                    </button>
                    <button
                        @click.stop="emit('generate-image-description')"
                        :disabled="generatingImageDescription"
                        class="px-3 py-1.5 text-xs font-medium text-teal-700 bg-teal-50 rounded-lg hover:bg-teal-100 disabled:opacity-50 transition-colors"
                    >
                        {{ generatingImageDescription ? t('postAutomation.actions.generatingImageDescription') : t('postAutomation.actions.generateImageDescription') }}
                    </button>
                    <button
                        @click.stop="emit('generate-image')"
                        :disabled="generatingImage"
                        class="px-3 py-1.5 text-xs font-medium text-purple-700 bg-purple-50 rounded-lg hover:bg-purple-100 disabled:opacity-50 transition-colors"
                    >
                        {{ generatingImage ? t('postAutomation.actions.generatingImage') : t('postAutomation.actions.generateImage') }}
                    </button>
                    <button
                        v-if="post.status === 'draft' || post.status === 'pending_approval'"
                        @click.stop="emit('approve')"
                        class="px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 rounded-lg hover:bg-green-100 transition-colors"
                    >
                        {{ t('postAutomation.actions.approve') }}
                    </button>
                    <button
                        v-if="post.status === 'approved' || post.status === 'scheduled'"
                        @click.stop="emit('publish')"
                        :disabled="publishing"
                        class="px-3 py-1.5 text-xs font-medium text-orange-700 bg-orange-50 rounded-lg hover:bg-orange-100 disabled:opacity-50 transition-colors"
                    >
                        {{ publishing ? t('postAutomation.actions.publishing') : t('postAutomation.actions.publish') }}
                    </button>
                    <button
                        @click.stop="emit('preview')"
                        class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        {{ t('postAutomation.actions.preview') }}
                    </button>
                    <button
                        @click.stop="emit('edit')"
                        class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        {{ t('postAutomation.actions.edit') }}
                    </button>
                </div>
            </div>
        </td>
    </tr>
</template>
