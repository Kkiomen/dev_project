<script setup>
import { ref, computed, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    slots: { type: Array, required: true },
    planId: { type: [String, Number], default: null },
    pillars: { type: Array, default: () => [] },
});

const emit = defineEmits(['updated', 'add', 'addBetween']);

const { t, locale } = useI18n();
const managerStore = useManagerStore();
const toast = useToast();
const { confirm } = useConfirm();

const platforms = ['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube'];
const contentTypes = ['post', 'carousel', 'video', 'reel', 'story', 'article', 'thread', 'poll'];

const platformColors = {
    instagram: 'bg-gradient-to-r from-purple-500 to-pink-500 text-white',
    facebook: 'bg-blue-600 text-white',
    tiktok: 'bg-gray-100 text-gray-900',
    linkedin: 'bg-sky-700 text-white',
    x: 'bg-gray-400 text-gray-900',
    youtube: 'bg-red-600 text-white',
};

const getStatusColor = (status) => {
    switch (status) {
        case 'generating': return 'bg-amber-400 animate-pulse';
        case 'content_ready': return 'bg-blue-400';
        case 'media_ready': return 'bg-purple-400';
        case 'approved': return 'bg-emerald-400';
        case 'published': return 'bg-green-400';
        case 'skipped': return 'bg-yellow-400';
        default: return 'bg-gray-500';
    }
};

const getStatusLabel = (status) => {
    return t(`manager.contentList.status.${status}`, status);
};

// --- Inline editing ---
const editingSlotId = ref(null);
const editingField = ref(null);
const editingValue = ref('');
const saving = ref(false);
const hoveredInsertIndex = ref(null);

const sortedSlots = computed(() => {
    return [...props.slots].sort((a, b) => {
        const dateA = a.scheduled_date || '';
        const dateB = b.scheduled_date || '';
        if (dateA !== dateB) return dateA.localeCompare(dateB);
        return (a.scheduled_time || '').localeCompare(b.scheduled_time || '');
    });
});

function startEditing(slotId, field, currentValue) {
    editingSlotId.value = slotId;
    editingField.value = field;
    editingValue.value = currentValue || '';

    if (field === 'topic' || field === 'description') {
        nextTick(() => {
            const el = document.querySelector('.js-autoresize');
            if (el) autoResize(el);
        });
    }
}

function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

function cancelEditing() {
    editingSlotId.value = null;
    editingField.value = null;
    editingValue.value = '';
}

function isEditing(slotId, field) {
    return editingSlotId.value === slotId && editingField.value === field;
}

async function saveEditing() {
    if (!editingSlotId.value || !editingField.value || saving.value) return;

    const slotId = editingSlotId.value;
    const field = editingField.value;
    const value = editingValue.value;

    const slot = props.slots.find(s => s.id === slotId);
    if (slot && (slot[field] || '') === value) {
        cancelEditing();
        return;
    }

    saving.value = true;
    try {
        await managerStore.updatePlanSlot(props.planId, slotId, { [field]: value });
        toast.success(t('manager.contentList.saved'));
        emit('updated');
    } catch {
        toast.error(t('common.error'));
    } finally {
        saving.value = false;
        cancelEditing();
    }
}

async function handleDelete(slotId) {
    const confirmed = await confirm({
        title: t('common.deleteConfirmTitle'),
        message: t('manager.contentList.deleteConfirm'),
        confirmText: t('common.delete'),
        variant: 'danger',
    });
    if (!confirmed) return;

    try {
        await managerStore.removePlanSlot(props.planId, slotId);
        toast.success(t('manager.contentList.deleted'));
        emit('updated');
    } catch {
        toast.error(t('common.error'));
    }
}

function handleInsertBetween(index) {
    const sorted = sortedSlots.value;
    const afterSlot = sorted[index];
    const beforeSlot = sorted[index + 1];
    const dateStr = afterSlot?.scheduled_date || new Date().toISOString().split('T')[0];
    emit('addBetween', { dateStr, afterIndex: index });
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString(locale.value, { weekday: 'short', month: 'short', day: 'numeric' });
}

function formatTime(time) {
    if (!time) return '';
    return time.substring(0, 5);
}
</script>

<template>
    <div class="rounded-xl bg-gray-900 border border-gray-800 overflow-hidden">
        <!-- Desktop table (hidden on mobile) -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left">
                <thead class="sticky top-0 z-10 bg-gray-900 border-b border-gray-800">
                    <tr>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-8">#</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('manager.contentList.colDate') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('manager.contentList.colTime') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('manager.contentList.colPlatform') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('manager.contentList.colType') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('manager.contentList.colPillar') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider min-w-[180px]">{{ t('manager.contentList.colTopic') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider min-w-[160px]">{{ t('manager.contentList.colDescription') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(slot, index) in sortedSlots" :key="slot.id">
                        <tr class="hover:bg-gray-800/30 transition-colors border-b border-gray-800/50">
                            <!-- # + Status dot -->
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full shrink-0" :class="getStatusColor(slot.status)" :title="getStatusLabel(slot.status)"></span>
                                    <span class="text-xs text-gray-500">{{ index + 1 }}</span>
                                </div>
                            </td>

                            <!-- Date -->
                            <td class="px-3 py-2.5" @click.stop="startEditing(slot.id, 'scheduled_date', slot.scheduled_date)">
                                <input
                                    v-if="isEditing(slot.id, 'scheduled_date')"
                                    type="date"
                                    v-model="editingValue"
                                    @blur="saveEditing()"
                                    @keydown.enter="saveEditing()"
                                    @keydown.escape="cancelEditing()"
                                    class="w-full rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    autofocus
                                />
                                <span v-else class="text-xs text-gray-300 cursor-pointer hover:text-white transition-colors">
                                    {{ formatDate(slot.scheduled_date) }}
                                </span>
                            </td>

                            <!-- Time -->
                            <td class="px-3 py-2.5" @click.stop="startEditing(slot.id, 'scheduled_time', slot.scheduled_time)">
                                <input
                                    v-if="isEditing(slot.id, 'scheduled_time')"
                                    type="time"
                                    v-model="editingValue"
                                    @blur="saveEditing()"
                                    @keydown.enter="saveEditing()"
                                    @keydown.escape="cancelEditing()"
                                    class="w-full rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    autofocus
                                />
                                <span v-else class="text-xs text-gray-400 cursor-pointer hover:text-white transition-colors">
                                    {{ formatTime(slot.scheduled_time) || '—' }}
                                </span>
                            </td>

                            <!-- Platform with icon -->
                            <td class="px-3 py-2.5" @click.stop="startEditing(slot.id, 'platform', slot.platform)">
                                <select
                                    v-if="isEditing(slot.id, 'platform')"
                                    v-model="editingValue"
                                    @change="saveEditing()"
                                    @blur="saveEditing()"
                                    @keydown.escape="cancelEditing()"
                                    class="rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    autofocus
                                >
                                    <option v-for="p in platforms" :key="p" :value="p">{{ p }}</option>
                                </select>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider cursor-pointer"
                                    :class="platformColors[slot.platform] || 'bg-gray-600 text-white'"
                                >
                                    <!-- Instagram -->
                                    <svg v-if="slot.platform === 'instagram'" class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                                    </svg>
                                    <!-- Facebook -->
                                    <svg v-else-if="slot.platform === 'facebook'" class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                    </svg>
                                    <!-- TikTok -->
                                    <svg v-else-if="slot.platform === 'tiktok'" class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                                    </svg>
                                    <!-- LinkedIn -->
                                    <svg v-else-if="slot.platform === 'linkedin'" class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                                    </svg>
                                    <!-- X (Twitter) -->
                                    <svg v-else-if="slot.platform === 'x'" class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                    </svg>
                                    <!-- YouTube -->
                                    <svg v-else-if="slot.platform === 'youtube'" class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                    </svg>
                                    {{ slot.platform }}
                                </span>
                            </td>

                            <!-- Content Type -->
                            <td class="px-3 py-2.5" @click.stop="startEditing(slot.id, 'content_type', slot.content_type)">
                                <select
                                    v-if="isEditing(slot.id, 'content_type')"
                                    v-model="editingValue"
                                    @change="saveEditing()"
                                    @blur="saveEditing()"
                                    @keydown.escape="cancelEditing()"
                                    class="rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    autofocus
                                >
                                    <option v-for="ct in contentTypes" :key="ct" :value="ct">{{ ct }}</option>
                                </select>
                                <span v-else class="text-xs text-gray-300 capitalize cursor-pointer hover:text-white transition-colors">
                                    {{ slot.content_type }}
                                </span>
                            </td>

                            <!-- Pillar -->
                            <td class="px-3 py-2.5" @click.stop="startEditing(slot.id, 'content_pillar', slot.content_pillar)">
                                <select
                                    v-if="isEditing(slot.id, 'content_pillar')"
                                    v-model="editingValue"
                                    @change="saveEditing()"
                                    @blur="saveEditing()"
                                    @keydown.escape="cancelEditing()"
                                    class="rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    autofocus
                                >
                                    <option value="">—</option>
                                    <option v-for="p in pillars" :key="p" :value="p">{{ p }}</option>
                                </select>
                                <span v-else class="text-xs text-gray-400 cursor-pointer hover:text-white transition-colors">
                                    {{ slot.content_pillar || '—' }}
                                </span>
                            </td>

                            <!-- Topic -->
                            <td class="px-3 py-2.5 align-top" @click.stop="startEditing(slot.id, 'topic', slot.topic)">
                                <textarea
                                    v-if="isEditing(slot.id, 'topic')"
                                    v-model="editingValue"
                                    @blur="saveEditing()"
                                    @keydown.ctrl.enter="saveEditing()"
                                    @keydown.escape="cancelEditing()"
                                    @input="autoResize($event.target)"
                                    rows="2"
                                    class="js-autoresize w-full min-w-[180px] rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500 resize-none overflow-hidden"
                                    autofocus
                                />
                                <span v-else class="text-xs cursor-pointer hover:text-white transition-colors line-clamp-2" :class="slot.topic ? 'text-gray-300' : 'text-gray-600 italic'">
                                    {{ slot.topic || t('manager.contentList.noTopic') }}
                                </span>
                            </td>

                            <!-- Description -->
                            <td class="px-3 py-2.5 align-top" @click.stop="startEditing(slot.id, 'description', slot.description)">
                                <textarea
                                    v-if="isEditing(slot.id, 'description')"
                                    v-model="editingValue"
                                    @blur="saveEditing()"
                                    @keydown.ctrl.enter="saveEditing()"
                                    @keydown.escape="cancelEditing()"
                                    @input="autoResize($event.target)"
                                    rows="2"
                                    class="js-autoresize w-full min-w-[160px] rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500 resize-none overflow-hidden"
                                    autofocus
                                />
                                <span v-else class="text-xs text-gray-500 line-clamp-2 max-w-[200px] block cursor-pointer hover:text-gray-300 transition-colors">
                                    {{ slot.description || '—' }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="px-3 py-2.5">
                                <button
                                    @click.stop="handleDelete(slot.id)"
                                    class="p-1 rounded text-gray-600 hover:text-red-400 hover:bg-red-400/10 transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <!-- Insert between rows button -->
                        <tr
                            v-if="index < sortedSlots.length - 1"
                            class="group/insert"
                            @mouseenter="hoveredInsertIndex = index"
                            @mouseleave="hoveredInsertIndex = null"
                        >
                            <td colspan="9" class="p-0 relative h-0">
                                <div class="absolute inset-x-0 -top-px flex items-center justify-center z-[5]">
                                    <button
                                        @click.stop="handleInsertBetween(index)"
                                        class="flex items-center gap-1.5 px-3 py-0.5 rounded-full text-[10px] font-medium bg-gray-800 border border-gray-700 text-gray-500 hover:text-indigo-400 hover:border-indigo-500/50 hover:bg-indigo-500/10 transition-all opacity-0 group-hover/insert:opacity-100"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        {{ t('manager.contentList.insertHere') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards (visible on mobile only) -->
        <div class="sm:hidden divide-y divide-gray-800">
            <template v-for="(slot, index) in sortedSlots" :key="slot.id">
                <div class="p-4 space-y-3">
                    <!-- Header: # + platform badge + delete button -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full shrink-0" :class="getStatusColor(slot.status)" :title="getStatusLabel(slot.status)"></span>
                            <span class="text-xs text-gray-500">#{{ index + 1 }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider"
                                :class="platformColors[slot.platform] || 'bg-gray-600 text-white'"
                                @click.stop="startEditing(slot.id, 'platform', slot.platform)"
                            >
                                <!-- Instagram -->
                                <svg v-if="slot.platform === 'instagram'" class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                                </svg>
                                <!-- Facebook -->
                                <svg v-else-if="slot.platform === 'facebook'" class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                                <!-- TikTok -->
                                <svg v-else-if="slot.platform === 'tiktok'" class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                                </svg>
                                <!-- LinkedIn -->
                                <svg v-else-if="slot.platform === 'linkedin'" class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                                </svg>
                                <!-- X -->
                                <svg v-else-if="slot.platform === 'x'" class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                </svg>
                                <!-- YouTube -->
                                <svg v-else-if="slot.platform === 'youtube'" class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                </svg>
                                {{ slot.platform }}
                            </span>
                        </div>
                        <button
                            @click.stop="handleDelete(slot.id)"
                            class="p-1.5 rounded text-gray-600 hover:text-red-400 hover:bg-red-400/10 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>

                    <!-- Editable fields -->
                    <div class="space-y-2">
                        <!-- Date -->
                        <div class="flex items-center gap-3" @click.stop="startEditing(slot.id, 'scheduled_date', slot.scheduled_date)">
                            <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0">{{ t('manager.contentList.colDate') }}</span>
                            <input
                                v-if="isEditing(slot.id, 'scheduled_date')"
                                type="date"
                                v-model="editingValue"
                                @blur="saveEditing()"
                                @keydown.enter="saveEditing()"
                                @keydown.escape="cancelEditing()"
                                class="flex-1 rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                autofocus
                            />
                            <span v-else class="text-xs text-gray-300">{{ formatDate(slot.scheduled_date) }}</span>
                        </div>

                        <!-- Time -->
                        <div class="flex items-center gap-3" @click.stop="startEditing(slot.id, 'scheduled_time', slot.scheduled_time)">
                            <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0">{{ t('manager.contentList.colTime') }}</span>
                            <input
                                v-if="isEditing(slot.id, 'scheduled_time')"
                                type="time"
                                v-model="editingValue"
                                @blur="saveEditing()"
                                @keydown.enter="saveEditing()"
                                @keydown.escape="cancelEditing()"
                                class="flex-1 rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                autofocus
                            />
                            <span v-else class="text-xs text-gray-400">{{ formatTime(slot.scheduled_time) || '—' }}</span>
                        </div>

                        <!-- Platform (select) -->
                        <div class="flex items-center gap-3" v-if="isEditing(slot.id, 'platform')">
                            <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0">{{ t('manager.contentList.colPlatform') }}</span>
                            <select
                                v-model="editingValue"
                                @change="saveEditing()"
                                @blur="saveEditing()"
                                @keydown.escape="cancelEditing()"
                                class="flex-1 rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                autofocus
                            >
                                <option v-for="p in platforms" :key="p" :value="p">{{ p }}</option>
                            </select>
                        </div>

                        <!-- Content Type -->
                        <div class="flex items-center gap-3" @click.stop="startEditing(slot.id, 'content_type', slot.content_type)">
                            <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0">{{ t('manager.contentList.colType') }}</span>
                            <select
                                v-if="isEditing(slot.id, 'content_type')"
                                v-model="editingValue"
                                @change="saveEditing()"
                                @blur="saveEditing()"
                                @keydown.escape="cancelEditing()"
                                class="flex-1 rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                autofocus
                            >
                                <option v-for="ct in contentTypes" :key="ct" :value="ct">{{ ct }}</option>
                            </select>
                            <span v-else class="text-xs text-gray-300 capitalize">{{ slot.content_type }}</span>
                        </div>

                        <!-- Pillar -->
                        <div class="flex items-center gap-3" @click.stop="startEditing(slot.id, 'content_pillar', slot.content_pillar)">
                            <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0">{{ t('manager.contentList.colPillar') }}</span>
                            <select
                                v-if="isEditing(slot.id, 'content_pillar')"
                                v-model="editingValue"
                                @change="saveEditing()"
                                @blur="saveEditing()"
                                @keydown.escape="cancelEditing()"
                                class="flex-1 rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                autofocus
                            >
                                <option value="">—</option>
                                <option v-for="p in pillars" :key="p" :value="p">{{ p }}</option>
                            </select>
                            <span v-else class="text-xs text-gray-400">{{ slot.content_pillar || '—' }}</span>
                        </div>

                        <!-- Topic -->
                        <div class="flex items-start gap-3" @click.stop="startEditing(slot.id, 'topic', slot.topic)">
                            <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0 mt-0.5">{{ t('manager.contentList.colTopic') }}</span>
                            <textarea
                                v-if="isEditing(slot.id, 'topic')"
                                v-model="editingValue"
                                @blur="saveEditing()"
                                @keydown.ctrl.enter="saveEditing()"
                                @keydown.escape="cancelEditing()"
                                @input="autoResize($event.target)"
                                rows="2"
                                class="js-autoresize flex-1 rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500 resize-none overflow-hidden"
                                autofocus
                            />
                            <span v-else class="text-xs" :class="slot.topic ? 'text-gray-200' : 'text-gray-600 italic'">
                                {{ slot.topic || t('manager.contentList.noTopic') }}
                            </span>
                        </div>

                        <!-- Description -->
                        <div class="flex items-start gap-3" @click.stop="startEditing(slot.id, 'description', slot.description)">
                            <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0 mt-0.5">{{ t('manager.contentList.colDescription') }}</span>
                            <textarea
                                v-if="isEditing(slot.id, 'description')"
                                v-model="editingValue"
                                @blur="saveEditing()"
                                @keydown.ctrl.enter="saveEditing()"
                                @keydown.escape="cancelEditing()"
                                @input="autoResize($event.target)"
                                rows="2"
                                class="js-autoresize flex-1 rounded border border-indigo-500 bg-gray-800 px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500 resize-none overflow-hidden"
                                autofocus
                            />
                            <span v-else class="text-xs text-gray-500">{{ slot.description || '—' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Mobile insert between button -->
                <div
                    v-if="index < sortedSlots.length - 1"
                    class="flex items-center justify-center py-1 bg-gray-900"
                >
                    <button
                        @click.stop="handleInsertBetween(index)"
                        class="flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-medium text-gray-600 hover:text-indigo-400 hover:bg-indigo-500/10 transition-all"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ t('manager.contentList.insertHere') }}
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
