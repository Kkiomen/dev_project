<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useConfirm } from '@/composables/useConfirm';
import PostStatusBadge from '@/components/posts/PostStatusBadge.vue';
import DateTimeInput from '@/components/common/DateTimeInput.vue';

const props = defineProps({
    post: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    generatingText: { type: Boolean, default: false },
    generatingImageDescription: { type: Boolean, default: false },
    generatingImage: { type: Boolean, default: false },
    publishing: { type: Boolean, default: false },
    platformColors: { type: Object, required: true },
});

const emit = defineEmits([
    'toggle-select',
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

const expanded = ref(false);
const editingField = ref(null);
const editingValue = ref('');
const showTagInput = ref(null);
const newTagValue = ref('');
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

function truncate(text, length = 60) {
    if (!text) return '';
    return text.length > length ? text.substring(0, length) + '...' : text;
}

function formatDate(dateStr) {
    if (!dateStr) return null;
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return null;
    return d.toLocaleDateString(undefined, { day: '2-digit', month: '2-digit', year: 'numeric' });
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
    <div
        class="bg-white border border-gray-200 rounded-lg overflow-hidden transition-shadow"
        :class="{ 'ring-2 ring-blue-200': selected }"
    >
        <!-- Collapsed header -->
        <div
            class="p-4 cursor-pointer"
            @click="expanded = !expanded"
        >
            <div class="flex items-start gap-3">
                <input
                    type="checkbox"
                    :checked="selected"
                    @change.stop="emit('toggle-select')"
                    @click.stop
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mt-0.5 shrink-0"
                />
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-sm font-medium text-gray-900 line-clamp-1">
                            {{ post.title || '—' }}
                        </span>
                        <PostStatusBadge :status="post.status" />
                    </div>
                    <p class="text-xs text-gray-500 mt-1 line-clamp-1">
                        {{ truncate(post.main_caption) || '—' }}
                    </p>
                    <div class="flex items-center justify-between mt-2">
                        <div class="flex items-center gap-1">
                            <span
                                v-for="pp in post.platform_posts"
                                :key="pp.platform"
                                class="w-2 h-2 rounded-full"
                                :class="pp.enabled ? (platformColors[pp.platform]?.dot || 'bg-gray-400') : 'bg-gray-200'"
                            />
                        </div>
                        <span v-if="formatDate(post.scheduled_at)" class="text-xs text-gray-400">
                            {{ formatDate(post.scheduled_at) }}
                        </span>
                    </div>
                </div>
                <svg
                    class="w-4 h-4 text-gray-400 shrink-0 mt-1 transition-transform"
                    :class="{ 'rotate-180': expanded }"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>

        <!-- Expanded content -->
        <div v-if="expanded" class="px-4 pb-4 space-y-4 border-t border-gray-100 pt-4">
            <!-- 1. Topic -->
            <div>
                <label class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1 block">
                    {{ t('postAutomation.table.topic') }}
                </label>
                <div v-if="editingField === 'title'">
                    <textarea
                        v-model="editingValue"
                        @blur="saveEditing()"
                        @keydown.escape="cancelEditing()"
                        rows="2"
                        class="w-full rounded-lg border border-blue-400 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-y"
                        autofocus
                    />
                </div>
                <div
                    v-else
                    @click.stop="startEditing('title', post.title)"
                    class="text-sm text-gray-900 cursor-pointer"
                >
                    {{ post.title || '—' }}
                </div>
            </div>

            <!-- 2. Text prompt (AI input) -->
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
                    class="text-sm text-gray-500 italic cursor-pointer"
                >
                    {{ post.text_prompt || t('postAutomation.row.noTextPrompt') }}
                </div>
            </div>

            <!-- 3. Content (AI output) -->
            <div>
                <label class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1 block">
                    {{ t('postAutomation.row.content') }}
                </label>
                <div v-if="editingField === 'main_caption'">
                    <textarea
                        v-model="editingValue"
                        @blur="saveEditing()"
                        @keydown.escape="cancelEditing()"
                        rows="4"
                        class="w-full rounded-lg border border-blue-400 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 resize-y"
                        autofocus
                    />
                </div>
                <div
                    v-else
                    @click.stop="startEditing('main_caption', post.main_caption)"
                    class="text-sm text-gray-600 whitespace-pre-wrap cursor-pointer"
                >
                    {{ post.main_caption || t('postAutomation.row.noContent') }}
                </div>
            </div>

            <!-- 4. Image prompt (AI input) -->
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
                    class="text-sm text-gray-500 italic cursor-pointer"
                >
                    {{ post.image_prompt || t('postAutomation.row.noImagePrompt') }}
                </div>
            </div>

            <!-- 5. Image + Upload -->
            <div>
                <label class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1 block">
                    {{ t('postAutomation.table.image') }}
                </label>
                <div v-if="post.first_media_url" class="relative inline-block">
                    <img
                        :src="post.first_media_url"
                        :alt="post.title"
                        class="w-24 h-24 object-cover rounded-lg border border-gray-200"
                    />
                    <span
                        v-if="post.media_count > 1"
                        class="absolute -top-1 -right-1 bg-gray-700 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"
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
                    v-if="!post.first_media_url"
                    :for="`card-upload-${post.id}`"
                    class="flex flex-col items-center justify-center w-24 h-24 rounded-lg border-2 border-dashed cursor-pointer transition-colors"
                    :class="dragOver ? 'border-blue-400 bg-blue-50' : 'border-gray-300 hover:border-gray-400 bg-gray-50'"
                    @dragover.prevent="dragOver = true"
                    @dragleave="dragOver = false"
                    @drop.stop="handleDrop"
                    @click.stop
                >
                    <svg class="w-5 h-5 text-gray-400 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <span class="text-[10px] text-gray-500 text-center leading-tight px-1">
                        {{ t('postAutomation.row.uploadHint') }}
                    </span>
                    <input
                        :id="`card-upload-${post.id}`"
                        type="file"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        class="hidden"
                        @change.stop="handleFileSelect"
                    />
                </label>
                <!-- Add more button -->
                <label
                    v-if="post.first_media_url"
                    :for="`card-upload-more-${post.id}`"
                    class="mt-1.5 inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700 cursor-pointer"
                    @click.stop
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('postAutomation.row.uploadImage') }}
                    <input
                        :id="`card-upload-more-${post.id}`"
                        type="file"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        class="hidden"
                        @change.stop="handleFileSelect"
                    />
                </label>
            </div>

            <!-- 6. Tags -->
            <div v-if="tagsPerPlatform.length" class="space-y-2">
                <label class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1 block">
                    {{ t('postAutomation.row.tags') }}
                </label>
                <div
                    v-for="ppData in tagsPerPlatform"
                    :key="ppData.platform"
                    class="flex items-center gap-1.5 flex-wrap"
                >
                    <button
                        @click.stop="emit('toggle-platform', { postId: post.id, platform: ppData.platform })"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium"
                        :class="`${platformColors[ppData.platform]?.bg || 'bg-gray-100'} ${platformColors[ppData.platform]?.text || 'text-gray-700'}`"
                    >
                        <span class="w-2 h-2 rounded-full" :class="platformColors[ppData.platform]?.dot || 'bg-gray-400'" />
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
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                </div>
            </div>

            <!-- 7. Schedule -->
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
                    class="inline-flex items-center gap-1.5 text-sm cursor-pointer hover:text-blue-600 transition-colors"
                    :class="post.scheduled_at ? 'text-gray-700' : 'text-gray-400'"
                >
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>{{ formatDate(post.scheduled_at) || t('postAutomation.row.notScheduled') }}</span>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-1.5 pt-3 border-t border-gray-100">
                <button
                    @click.stop="emit('generate-text')"
                    :disabled="generatingText"
                    class="px-2.5 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 disabled:opacity-50"
                >
                    {{ generatingText ? t('postAutomation.actions.generatingText') : t('postAutomation.actions.generateText') }}
                </button>
                <button
                    @click.stop="emit('generate-image-description')"
                    :disabled="generatingImageDescription"
                    class="px-2.5 py-1.5 text-xs font-medium text-teal-700 bg-teal-50 rounded-lg hover:bg-teal-100 disabled:opacity-50"
                >
                    {{ generatingImageDescription ? t('postAutomation.actions.generatingImageDescription') : t('postAutomation.actions.generateImageDescription') }}
                </button>
                <button
                    @click.stop="emit('generate-image')"
                    :disabled="generatingImage"
                    class="px-2.5 py-1.5 text-xs font-medium text-purple-700 bg-purple-50 rounded-lg hover:bg-purple-100 disabled:opacity-50"
                >
                    {{ generatingImage ? t('postAutomation.actions.generatingImage') : t('postAutomation.actions.generateImage') }}
                </button>
                <button
                    v-if="post.status === 'draft' || post.status === 'pending_approval'"
                    @click.stop="emit('approve')"
                    class="px-2.5 py-1.5 text-xs font-medium text-green-700 bg-green-50 rounded-lg hover:bg-green-100"
                >
                    {{ t('postAutomation.actions.approve') }}
                </button>
                <button
                    v-if="post.status === 'approved' || post.status === 'scheduled'"
                    @click.stop="emit('publish')"
                    :disabled="publishing"
                    class="px-2.5 py-1.5 text-xs font-medium text-orange-700 bg-orange-50 rounded-lg hover:bg-orange-100 disabled:opacity-50"
                >
                    {{ publishing ? t('postAutomation.actions.publishing') : t('postAutomation.actions.publish') }}
                </button>
                <button
                    @click.stop="emit('preview')"
                    class="px-2.5 py-1.5 text-xs font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100"
                >
                    {{ t('postAutomation.actions.preview') }}
                </button>
                <button
                    @click.stop="emit('edit')"
                    class="px-2.5 py-1.5 text-xs font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100"
                >
                    {{ t('postAutomation.actions.edit') }}
                </button>
            </div>
        </div>
    </div>
</template>
