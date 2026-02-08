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

const expanded = ref(false);
const editingField = ref(null);
const editingValue = ref('');
const editInput = ref(null);
const keywordInput = ref('');

function startEditing(field, value) {
    editingField.value = field;
    editingValue.value = value || '';
    nextTick(() => {
        if (editInput.value) editInput.value.focus();
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
}
</script>

<template>
    <div class="lg:hidden bg-white rounded-xl border border-gray-200 overflow-hidden">
        <!-- Header (always visible) -->
        <div
            class="flex items-center gap-3 px-4 py-3 cursor-pointer"
            @click="expanded = !expanded"
        >
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-xs text-gray-500">{{ formatDate(proposal.scheduled_date, proposal.scheduled_time) }}</span>
                    <ProposalStatusBadge :status="proposal.status" />
                </div>
                <p class="text-sm font-medium text-gray-900 truncate">{{ proposal.title }}</p>
            </div>
            <svg
                class="w-5 h-5 text-gray-400 transition-transform flex-shrink-0"
                :class="{ 'rotate-180': expanded }"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>

        <!-- Expanded Content -->
        <div v-if="expanded" class="border-t border-gray-100 px-4 py-3 space-y-3">
            <!-- Title (editable) -->
            <div>
                <label class="text-xs font-medium text-gray-500">{{ t('postAutomation.proposals.table.title') }}</label>
                <template v-if="editingField === 'title'">
                    <input
                        ref="editInput"
                        v-model="editingValue"
                        type="text"
                        class="w-full mt-1 rounded border border-gray-300 px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500"
                        @keydown="handleKeydown"
                        @blur="saveEditing"
                    />
                </template>
                <template v-else>
                    <p
                        class="text-sm text-gray-900 mt-1 cursor-pointer hover:text-blue-600"
                        @click="startEditing('title', proposal.title)"
                    >
                        {{ proposal.title }}
                    </p>
                </template>
            </div>

            <!-- Time -->
            <div>
                <label class="text-xs font-medium text-gray-500">{{ t('postAutomation.proposals.form.scheduledTime') }}</label>
                <input
                    type="time"
                    :value="proposal.scheduled_time || ''"
                    @change="saveTimeEdit"
                    class="w-full mt-1 rounded border border-gray-300 px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <!-- Keywords -->
            <div>
                <label class="text-xs font-medium text-gray-500">{{ t('postAutomation.proposals.table.keywords') }}</label>
                <div class="flex flex-wrap gap-1 mt-1">
                    <ProposalKeywordBadge
                        v-for="kw in (proposal.keywords || [])"
                        :key="kw"
                        :keyword="kw"
                        removable
                        @remove="removeKeyword"
                    />
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

            <!-- Notes (editable) -->
            <div>
                <label class="text-xs font-medium text-gray-500">{{ t('postAutomation.proposals.table.notes') }}</label>
                <template v-if="editingField === 'notes'">
                    <textarea
                        ref="editInput"
                        v-model="editingValue"
                        rows="2"
                        class="w-full mt-1 rounded border border-gray-300 px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500 resize-none"
                        @keydown="handleKeydown"
                        @blur="saveEditing"
                    />
                </template>
                <template v-else>
                    <p
                        class="text-sm text-gray-700 mt-1 cursor-pointer hover:text-blue-600"
                        @click="startEditing('notes', proposal.notes)"
                    >
                        {{ proposal.notes || t('postAutomation.proposals.noNotes') }}
                    </p>
                </template>
            </div>

            <!-- Actions -->
            <div class="flex gap-2 pt-1">
                <button
                    v-if="proposal.status === 'pending'"
                    @click="emit('generate-post', proposal.id)"
                    :disabled="isGenerating"
                    class="flex-1 px-3 py-1.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1.5"
                >
                    <svg v-if="isGenerating" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>
                    {{ isGenerating ? t('postAutomation.proposals.actions.generating') : t('postAutomation.proposals.actions.generatePost') }}
                </button>
                <button
                    @click="emit('edit', proposal)"
                    class="flex-1 px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    {{ t('postAutomation.proposals.actions.edit') }}
                </button>
                <button
                    @click="emit('delete', proposal.id)"
                    class="px-3 py-1.5 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50"
                >
                    {{ t('postAutomation.proposals.actions.delete') }}
                </button>
            </div>
        </div>
    </div>
</template>
