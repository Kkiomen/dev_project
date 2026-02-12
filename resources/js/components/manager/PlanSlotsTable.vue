<script setup>
import { ref, computed, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    slots: { type: Array, required: true },
    planId: { type: [String, Number], default: null },
    pillars: { type: Array, default: () => [] },
});

const emit = defineEmits(['updated']);

const { t } = useI18n();
const managerStore = useManagerStore();
const toast = useToast();

const platforms = ['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube'];
const contentTypes = ['post', 'carousel', 'video', 'reel', 'story', 'article', 'thread', 'poll'];

const platformColors = {
    instagram: 'bg-pink-500 text-white',
    facebook: 'bg-blue-500 text-white',
    tiktok: 'bg-gray-100 text-gray-900',
    linkedin: 'bg-sky-600 text-white',
    x: 'bg-gray-400 text-gray-900',
    youtube: 'bg-red-500 text-white',
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

// --- Inline editing ---
const editingSlotId = ref(null);
const editingField = ref(null);
const editingValue = ref('');
const saving = ref(false);

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

    // Find original slot to check if value actually changed
    const slot = props.slots.find(s => s.id === slotId);
    if (slot && (slot[field] || '') === value) {
        cancelEditing();
        return;
    }

    saving.value = true;
    try {
        await managerStore.updatePlanSlot(props.planId, slotId, { [field]: value });
        toast.success(t('manager.calendar.listView.saved'));
        emit('updated');
    } catch {
        toast.error(t('common.error'));
    } finally {
        saving.value = false;
        cancelEditing();
    }
}

async function handleDelete(slotId) {
    if (!confirm(t('manager.calendar.listView.confirmDelete'))) return;

    try {
        await managerStore.removePlanSlot(props.planId, slotId);
        toast.success(t('manager.calendar.listView.deleted'));
        emit('updated');
    } catch {
        toast.error(t('common.error'));
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
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
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('manager.calendar.listView.date') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('manager.calendar.listView.time') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('manager.calendar.listView.platform') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('manager.calendar.listView.type') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('manager.calendar.listView.pillar') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider min-w-[180px]">{{ t('manager.calendar.listView.topic') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider min-w-[160px]">{{ t('manager.calendar.listView.description') }}</th>
                        <th class="px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-12"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                    <tr
                        v-for="(slot, index) in sortedSlots"
                        :key="slot.id"
                        class="hover:bg-gray-800/30 transition-colors"
                    >
                        <!-- # + Status dot -->
                        <td class="px-3 py-2.5">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full shrink-0" :class="getStatusColor(slot.status)"></span>
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

                        <!-- Platform -->
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
                                class="inline-block px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider cursor-pointer"
                                :class="platformColors[slot.platform] || 'bg-gray-600 text-white'"
                            >
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
                                {{ slot.topic || t('manager.calendar.listView.noTopic') }}
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
                </tbody>
            </table>
        </div>

        <!-- Mobile cards (visible on mobile only) -->
        <div class="sm:hidden divide-y divide-gray-800">
            <div
                v-for="(slot, index) in sortedSlots"
                :key="slot.id"
                class="p-4 space-y-3"
            >
                <!-- Header: # + platform badge + delete button -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full shrink-0" :class="getStatusColor(slot.status)"></span>
                        <span class="text-xs text-gray-500">#{{ index + 1 }}</span>
                        <span
                            class="inline-block px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider"
                            :class="platformColors[slot.platform] || 'bg-gray-600 text-white'"
                            @click.stop="startEditing(slot.id, 'platform', slot.platform)"
                        >
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

                <!-- Editable fields as label: value rows -->
                <div class="space-y-2">
                    <!-- Date -->
                    <div class="flex items-center gap-3" @click.stop="startEditing(slot.id, 'scheduled_date', slot.scheduled_date)">
                        <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0">{{ t('manager.calendar.listView.date') }}</span>
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
                        <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0">{{ t('manager.calendar.listView.time') }}</span>
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
                        <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0">{{ t('manager.calendar.listView.platform') }}</span>
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
                        <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0">{{ t('manager.calendar.listView.type') }}</span>
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
                        <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0">{{ t('manager.calendar.listView.pillar') }}</span>
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
                        <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0 mt-0.5">{{ t('manager.calendar.listView.topic') }}</span>
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
                            {{ slot.topic || t('manager.calendar.listView.noTopic') }}
                        </span>
                    </div>

                    <!-- Description -->
                    <div class="flex items-start gap-3" @click.stop="startEditing(slot.id, 'description', slot.description)">
                        <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider w-16 shrink-0 mt-0.5">{{ t('manager.calendar.listView.description') }}</span>
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
        </div>
    </div>
</template>
