<script setup>
import { ref, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoardsStore } from '@/stores/boards';
import { useToast } from '@/composables/useToast';
import BoardCard from './BoardCard.vue';
import BoardCardModal from './BoardCardModal.vue';
import Button from '@/components/common/Button.vue';
import Dropdown from '@/components/common/Dropdown.vue';

const props = defineProps({
    column: { type: Object, required: true },
    boardId: { type: String, required: true },
});

const emit = defineEmits(['delete', 'move-card']);

const { t } = useI18n();
const boardsStore = useBoardsStore();
const toast = useToast();

const addingCard = ref(false);
const newCardTitle = ref('');
const savingCard = ref(false);
const editingName = ref(false);
const columnName = ref(props.column.name);
const selectedCard = ref(null);
const showCardModal = ref(false);
const newCardInput = ref(null);

// Drag & drop state
const dragOverColumn = ref(false);

const handleAddCard = async () => {
    if (!newCardTitle.value.trim()) return;

    savingCard.value = true;
    try {
        await boardsStore.createCard(props.column.id, { title: newCardTitle.value.trim() });
        newCardTitle.value = '';
        toast.success(t('boards.cardCreated'));
    } catch (error) {
        console.error('Failed to create card:', error);
    } finally {
        savingCard.value = false;
    }
};

const startAddCard = () => {
    addingCard.value = true;
    nextTick(() => {
        newCardInput.value?.focus();
    });
};

const handleUpdateName = async () => {
    if (!columnName.value.trim() || columnName.value === props.column.name) {
        editingName.value = false;
        columnName.value = props.column.name;
        return;
    }
    try {
        await boardsStore.updateColumn(props.column.id, { name: columnName.value.trim() });
        editingName.value = false;
        toast.success(t('boards.columnUpdated'));
    } catch (error) {
        console.error('Failed to update column:', error);
        columnName.value = props.column.name;
        editingName.value = false;
    }
};

const openCardDetail = (card) => {
    selectedCard.value = card;
    showCardModal.value = true;
};

// Drag & drop handlers
const handleDragOver = (e) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    dragOverColumn.value = true;
};

const handleDragLeave = () => {
    dragOverColumn.value = false;
};

const handleDrop = (e) => {
    e.preventDefault();
    dragOverColumn.value = false;

    const cardId = e.dataTransfer.getData('text/card-id');
    const fromColumnId = e.dataTransfer.getData('text/column-id');

    if (!cardId || !fromColumnId) return;

    const cards = props.column.cards || [];
    const newPosition = cards.length;

    emit('move-card', cardId, fromColumnId, props.column.id, newPosition);
};

const handleCardDrop = (cardId, fromColumnId, position) => {
    emit('move-card', cardId, fromColumnId, props.column.id, position);
};

const cardsCount = () => (props.column.cards || []).length;
const isOverLimit = () => props.column.card_limit && cardsCount() >= props.column.card_limit;
</script>

<template>
    <div
        class="flex-shrink-0 w-72 sm:w-[304px] bg-white/80 backdrop-blur-sm rounded-xl border border-gray-200/80 flex flex-col max-h-full shadow-sm transition-all duration-200"
        :class="{
            'ring-2 ring-blue-400/50 border-blue-300 bg-blue-50/30': dragOverColumn,
            'shadow-md': dragOverColumn,
        }"
        @dragover="handleDragOver"
        @dragleave="handleDragLeave"
        @drop="handleDrop"
    >
        <!-- Column Header -->
        <div class="px-3.5 py-3 flex items-center justify-between flex-shrink-0 border-b border-gray-100">
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <div
                    class="w-2.5 h-2.5 rounded-full flex-shrink-0 ring-2 ring-offset-1"
                    :style="{
                        backgroundColor: column.color || '#6B7280',
                        '--tw-ring-color': (column.color || '#6B7280') + '40',
                    }"
                />
                <template v-if="editingName">
                    <input
                        v-model="columnName"
                        type="text"
                        class="flex-1 rounded-lg border-gray-300 text-sm font-semibold py-1 px-2 focus:border-blue-500 focus:ring-blue-500"
                        @keyup.enter="handleUpdateName"
                        @keyup.escape="editingName = false; columnName = column.name"
                        @blur="handleUpdateName"
                    />
                </template>
                <template v-else>
                    <h3
                        class="text-sm font-semibold text-gray-800 truncate cursor-pointer hover:text-gray-900 transition-colors"
                        @dblclick="editingName = true"
                    >
                        {{ column.name }}
                    </h3>
                    <span
                        class="text-xs font-medium px-1.5 py-0.5 rounded-full flex-shrink-0"
                        :class="isOverLimit() ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500'"
                    >
                        {{ cardsCount() }}<template v-if="column.card_limit">/{{ column.card_limit }}</template>
                    </span>
                </template>
            </div>
            <Dropdown align="right" width="36">
                <template #trigger>
                    <button class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-all">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                        </svg>
                    </button>
                </template>
                <template #content>
                    <button
                        @click="editingName = true"
                        class="flex items-center gap-2 w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        {{ t('boards.editColumn') }}
                    </button>
                    <button
                        @click="$emit('delete')"
                        class="flex items-center gap-2 w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        {{ t('boards.deleteColumn') }}
                    </button>
                </template>
            </Dropdown>
        </div>

        <!-- Cards -->
        <div class="column-cards flex-1 overflow-y-auto px-2.5 py-2.5 space-y-2 min-h-[60px]">
            <BoardCard
                v-for="(card, index) in (column.cards || [])"
                :key="card.id"
                :card="card"
                :column-id="column.id"
                :index="index"
                @click="openCardDetail(card)"
                @drop-at="(cardId, fromColumnId) => handleCardDrop(cardId, fromColumnId, index)"
            />

            <!-- Empty state -->
            <div
                v-if="!addingCard && !(column.cards || []).length"
                class="text-center py-6 text-xs text-gray-400"
            >
                <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {{ t('boards.noCards') }}
            </div>

            <!-- Add Card inline -->
            <div v-if="addingCard" class="bg-white rounded-lg shadow-sm border border-gray-200 p-3">
                <textarea
                    ref="newCardInput"
                    v-model="newCardTitle"
                    :placeholder="t('boards.cardTitlePlaceholder')"
                    rows="2"
                    class="block w-full border-0 p-0 text-sm text-gray-900 placeholder-gray-400 focus:ring-0 resize-none"
                    @keydown.enter.exact.prevent="handleAddCard"
                    @keyup.escape="addingCard = false; newCardTitle = ''"
                />
                <div class="flex gap-2 mt-2.5">
                    <Button size="sm" :loading="savingCard" @click="handleAddCard">
                        {{ t('boards.addCard') }}
                    </Button>
                    <Button size="sm" variant="ghost" @click="addingCard = false; newCardTitle = ''">
                        {{ t('common.cancel') }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Add Card button -->
        <div v-if="!addingCard" class="px-2.5 pb-2.5 flex-shrink-0">
            <button
                @click="startAddCard"
                class="w-full py-2 px-3 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-all duration-150 flex items-center justify-center gap-1.5 group"
            >
                <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ t('boards.addCard') }}
            </button>
        </div>

        <!-- Card Detail Modal -->
        <BoardCardModal
            :show="showCardModal"
            :card="selectedCard"
            @close="showCardModal = false; selectedCard = null"
        />
    </div>
</template>

<style scoped>
.column-cards {
    scrollbar-width: thin;
    scrollbar-color: #e5e7eb transparent;
}
.column-cards::-webkit-scrollbar {
    width: 4px;
}
.column-cards::-webkit-scrollbar-track {
    background: transparent;
}
.column-cards::-webkit-scrollbar-thumb {
    background-color: #e5e7eb;
    border-radius: 2px;
}
.column-cards::-webkit-scrollbar-thumb:hover {
    background-color: #d1d5db;
}
</style>
