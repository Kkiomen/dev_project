<script setup>
import { ref, computed, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import ProposalStatusBadge from './ProposalStatusBadge.vue';
import ProposalKeywordBadge from './ProposalKeywordBadge.vue';

const props = defineProps({
    proposal: { type: Object, required: true },
    generatingId: { type: String, default: null },
});

const emit = defineEmits(['update-field', 'edit', 'delete', 'generate-post']);

const isGenerating = computed(() => props.generatingId === props.proposal.id);
const { t } = useI18n();

const editingField = ref(null);
const editingValue = ref('');
const editInput = ref(null);
const keywordInput = ref('');

function startEditing(field, value) {
    editingField.value = field;
    editingValue.value = value || '';
    nextTick(() => {
        if (editInput.value) {
            editInput.value.focus();
        }
    });
}

function saveEditing() {
    if (editingField.value) {
        emit('update-field', {
            proposalId: props.proposal.id,
            field: editingField.value,
            value: editingValue.value,
        });
        editingField.value = null;
    }
}

function cancelEditing() {
    editingField.value = null;
}

function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        saveEditing();
    } else if (e.key === 'Escape') {
        cancelEditing();
    }
}

function saveDateEdit(e) {
    emit('update-field', {
        proposalId: props.proposal.id,
        field: 'scheduled_date',
        value: e.target.value,
    });
    editingField.value = null;
}

function addKeyword() {
    const kw = keywordInput.value.trim();
    if (kw && !(props.proposal.keywords || []).includes(kw)) {
        const updated = [...(props.proposal.keywords || []), kw];
        emit('update-field', {
            proposalId: props.proposal.id,
            field: 'keywords',
            value: updated,
        });
        keywordInput.value = '';
    }
}

function removeKeyword(kw) {
    const updated = (props.proposal.keywords || []).filter(k => k !== kw);
    emit('update-field', {
        proposalId: props.proposal.id,
        field: 'keywords',
        value: updated,
    });
}

function handleKeywordKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addKeyword();
    }
}

function formatDate(dateStr, timeStr) {
    if (!dateStr) return '';
    const [y, m, d] = dateStr.split('-');
    const formatted = `${d}/${m}/${y}`;
    return timeStr ? `${formatted} ${timeStr}` : formatted;
}

function saveTimeEdit(e) {
    emit('update-field', {
        proposalId: props.proposal.id,
        field: 'scheduled_time',
        value: e.target.value || null,
    });
    editingField.value = null;
}
</script>

<template>
    <tr class="border-b border-gray-100 hover:bg-gray-50">
        <!-- Date & Time -->
        <td class="px-4 py-3 text-sm whitespace-nowrap">
            <template v-if="editingField === 'scheduled_date'">
                <div class="flex items-center gap-1">
                    <input
                        type="date"
                        :value="proposal.scheduled_date"
                        @change="saveDateEdit"
                        @blur="cancelEditing"
                        class="w-36 rounded border border-gray-300 px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500"
                        ref="editInput"
                    />
                    <input
                        type="time"
                        :value="proposal.scheduled_time || ''"
                        @change="saveTimeEdit"
                        class="w-24 rounded border border-gray-300 px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </template>
            <template v-else>
                <span
                    class="cursor-pointer hover:text-blue-600"
                    @click="startEditing('scheduled_date', proposal.scheduled_date)"
                >
                    {{ formatDate(proposal.scheduled_date, proposal.scheduled_time) }}
                </span>
            </template>
        </td>

        <!-- Title -->
        <td class="px-4 py-3 text-sm">
            <template v-if="editingField === 'title'">
                <input
                    ref="editInput"
                    v-model="editingValue"
                    type="text"
                    maxlength="255"
                    class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500"
                    @keydown="handleKeydown"
                    @blur="saveEditing"
                />
            </template>
            <template v-else>
                <span
                    class="cursor-pointer hover:text-blue-600"
                    @click="startEditing('title', proposal.title)"
                    :title="t('postAutomation.proposals.clickToEdit')"
                >
                    {{ proposal.title || t('postAutomation.proposals.clickToEdit') }}
                </span>
            </template>
        </td>

        <!-- Keywords -->
        <td class="px-4 py-3 text-sm">
            <div class="flex flex-wrap gap-1 items-center">
                <ProposalKeywordBadge
                    v-for="kw in (proposal.keywords || [])"
                    :key="kw"
                    :keyword="kw"
                    removable
                    @remove="removeKeyword"
                />
                <div class="inline-flex items-center gap-1">
                    <input
                        v-model="keywordInput"
                        type="text"
                        maxlength="100"
                        :placeholder="(proposal.keywords || []).length ? '+' : t('postAutomation.proposals.noKeywords')"
                        class="w-24 rounded border border-transparent px-1.5 py-0.5 text-xs hover:border-gray-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        @keydown="handleKeywordKeydown"
                    />
                </div>
            </div>
        </td>

        <!-- Notes -->
        <td class="px-4 py-3 text-sm max-w-xs">
            <template v-if="editingField === 'notes'">
                <textarea
                    ref="editInput"
                    v-model="editingValue"
                    rows="2"
                    maxlength="5000"
                    class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500 resize-none"
                    @keydown="handleKeydown"
                    @blur="saveEditing"
                />
            </template>
            <template v-else>
                <span
                    class="cursor-pointer hover:text-blue-600 truncate block"
                    @click="startEditing('notes', proposal.notes)"
                    :title="proposal.notes || t('postAutomation.proposals.clickToEdit')"
                >
                    {{ proposal.notes || t('postAutomation.proposals.noNotes') }}
                </span>
            </template>
        </td>

        <!-- Status -->
        <td class="px-4 py-3 text-sm">
            <ProposalStatusBadge :status="proposal.status" />
        </td>

        <!-- Actions -->
        <td class="px-4 py-3 text-sm">
            <div class="flex items-center gap-2">
                <button
                    v-if="proposal.status === 'pending'"
                    @click="emit('generate-post', proposal.id)"
                    :disabled="isGenerating"
                    class="text-gray-500 hover:text-green-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    :title="isGenerating ? t('postAutomation.proposals.actions.generating') : t('postAutomation.proposals.actions.generatePost')"
                >
                    <svg v-if="isGenerating" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>
                </button>
                <button
                    @click="emit('edit', proposal)"
                    class="text-gray-500 hover:text-blue-600"
                    :title="t('postAutomation.proposals.actions.edit')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </button>
                <button
                    @click="emit('delete', proposal.id)"
                    class="text-gray-500 hover:text-red-600"
                    :title="t('postAutomation.proposals.actions.delete')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        </td>
    </tr>
</template>
