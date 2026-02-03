<script setup>
import { ref, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoardsStore } from '@/stores/boards';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import BoardColumn from './BoardColumn.vue';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    board: { type: Object, required: true },
});

const { t } = useI18n();
const boardsStore = useBoardsStore();
const toast = useToast();
const { confirm } = useConfirm();

const addingColumn = ref(false);
const newColumnName = ref('');
const savingColumn = ref(false);
const newColumnInput = ref(null);

const handleAddColumn = async () => {
    if (!newColumnName.value.trim()) return;

    savingColumn.value = true;
    try {
        await boardsStore.createColumn(props.board.id, { name: newColumnName.value.trim() });
        newColumnName.value = '';
        addingColumn.value = false;
        toast.success(t('boards.columnCreated'));
    } catch (error) {
        console.error('Failed to create column:', error);
    } finally {
        savingColumn.value = false;
    }
};

const startAddColumn = () => {
    addingColumn.value = true;
    nextTick(() => {
        newColumnInput.value?.focus();
    });
};

const handleDeleteColumn = async (column) => {
    const confirmed = await confirm({
        title: t('boards.deleteColumn'),
        message: t('boards.deleteColumnConfirm', { name: column.name }),
        confirmText: t('common.delete'),
        variant: 'danger',
    });

    if (confirmed) {
        try {
            await boardsStore.deleteColumn(column.id);
            toast.success(t('boards.columnDeleted'));
        } catch (error) {
            console.error('Failed to delete column:', error);
        }
    }
};

const handleMoveCard = async (cardId, fromColumnId, toColumnId, newPosition) => {
    boardsStore.moveCardOptimistic(cardId, fromColumnId, toColumnId, newPosition);

    try {
        await boardsStore.moveCard(cardId, toColumnId, newPosition);
    } catch (error) {
        console.error('Failed to move card:', error);
        await boardsStore.fetchBoard(props.board.id);
    }
};
</script>

<template>
    <div class="kanban-scroll flex h-full overflow-x-auto px-4 sm:px-6 py-4 sm:py-5 gap-4 items-start">
        <!-- Columns -->
        <BoardColumn
            v-for="column in board.columns"
            :key="column.id"
            :column="column"
            :board-id="board.id"
            @delete="handleDeleteColumn(column)"
            @move-card="handleMoveCard"
        />

        <!-- Add Column -->
        <div class="flex-shrink-0 w-72 sm:w-[304px]">
            <div v-if="addingColumn" class="bg-white rounded-xl p-3 shadow-sm border border-gray-200">
                <input
                    ref="newColumnInput"
                    v-model="newColumnName"
                    type="text"
                    :placeholder="t('boards.columnNamePlaceholder')"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm mb-2.5"
                    @keyup.enter="handleAddColumn"
                    @keyup.escape="addingColumn = false; newColumnName = ''"
                />
                <div class="flex gap-2">
                    <Button size="sm" :loading="savingColumn" @click="handleAddColumn">
                        {{ t('boards.addColumn') }}
                    </Button>
                    <Button size="sm" variant="ghost" @click="addingColumn = false; newColumnName = ''">
                        {{ t('common.cancel') }}
                    </Button>
                </div>
            </div>
            <button
                v-else
                @click="startAddColumn"
                class="w-full py-3 px-4 rounded-xl border-2 border-dashed border-gray-200 text-gray-400 hover:border-blue-300 hover:text-blue-500 hover:bg-blue-50/30 transition-all duration-200 text-sm font-medium flex items-center justify-center gap-1.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ t('boards.addColumn') }}
            </button>
        </div>

        <!-- Right padding spacer for scroll -->
        <div class="flex-shrink-0 w-2" />
    </div>
</template>

<style scoped>
.kanban-scroll {
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
}
.kanban-scroll::-webkit-scrollbar {
    height: 8px;
}
.kanban-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.kanban-scroll::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
    border-radius: 4px;
}
.kanban-scroll::-webkit-scrollbar-thumb:hover {
    background-color: #9ca3af;
}
</style>
