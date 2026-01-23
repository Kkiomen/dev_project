<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useFieldsStore } from '@/stores/fields';
import { useRowsStore } from '@/stores/rows';
import { useCellsStore } from '@/stores/cells';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import KanbanColumn from './KanbanColumn.vue';
import CardModal from './CardModal.vue';

const { t } = useI18n();
const toast = useToast();
const { confirm } = useConfirm();

const props = defineProps({
    tableId: {
        type: String,
        required: true,
    },
    groupByFieldId: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['change-group-by']);

const fieldsStore = useFieldsStore();
const rowsStore = useRowsStore();
const cellsStore = useCellsStore();

// UI State
const showCardModal = ref(false);
const selectedCard = ref(null);
const draggedRow = ref(null);
const dragOverColumn = ref(null);

// Computed
const groupByField = computed(() => fieldsStore.getFieldById(props.groupByFieldId));

const primaryField = computed(() => fieldsStore.primaryField || fieldsStore.fields[0]);

const secondaryField = computed(() => {
    return fieldsStore.fields.find(f =>
        f.id !== primaryField.value?.id &&
        f.id !== props.groupByFieldId &&
        (f.type === 'text' || f.type === 'number')
    );
});

const choices = computed(() => groupByField.value?.options?.choices || []);

// Card operations
const openCard = (row) => {
    selectedCard.value = row;
    showCardModal.value = true;
};

const addCard = async (choiceId) => {
    const values = {};
    if (choiceId && props.groupByFieldId) {
        values[props.groupByFieldId] = choiceId;
    }

    try {
        const newRow = await rowsStore.createRow(props.tableId, values);
        openCard(newRow);
    } catch (error) {
        console.error('Failed to add card:', error);
    }
};

const updateCardField = async (fieldId, value) => {
    if (!selectedCard.value) return;

    // Update local state
    if (!selectedCard.value.values) selectedCard.value.values = {};
    selectedCard.value.values[fieldId] = value;

    // Update in store
    rowsStore.updateRowValue(selectedCard.value.id, fieldId, value);

    try {
        await cellsStore.updateCell(selectedCard.value.id, fieldId, value);
    } catch (error) {
        console.error('Failed to update field:', error);
    }
};

const deleteCard = async () => {
    if (!selectedCard.value) return;

    const confirmed = await confirm({
        title: t('kanban.deleteCard'),
        message: t('kanban.deleteCardMessage'),
        confirmText: t('common.delete'),
        variant: 'danger',
    });

    if (!confirmed) return;

    try {
        await rowsStore.deleteRow(selectedCard.value.id);
        showCardModal.value = false;
        selectedCard.value = null;
        toast.success(t('kanban.cardDeleted'));
    } catch (error) {
        console.error('Failed to delete card:', error);
        toast.error(t('kanban.deleteCardError'));
    }
};

// Drag & drop
const onDragStart = (event, row) => {
    draggedRow.value = row;
    event.dataTransfer.effectAllowed = 'move';
};

const onDragOver = (event, columnId) => {
    dragOverColumn.value = columnId;
};

const onDragLeave = () => {
    dragOverColumn.value = null;
};

const onDrop = async (event, columnId) => {
    dragOverColumn.value = null;

    if (!draggedRow.value || !props.groupByFieldId) return;

    const value = columnId || null;

    // Update local state
    if (!draggedRow.value.values) draggedRow.value.values = {};
    draggedRow.value.values[props.groupByFieldId] = value;

    try {
        await cellsStore.updateCell(draggedRow.value.id, props.groupByFieldId, value);
    } catch (error) {
        console.error('Failed to move card:', error);
    }

    draggedRow.value = null;
};
</script>

<template>
    <div class="flex-1 overflow-x-auto bg-gray-100 p-4">
        <div class="flex space-x-4 h-full min-w-max">
            <!-- No status column -->
            <KanbanColumn
                :choice="null"
                :rows="rowsStore.rows"
                :group-by-field-id="groupByFieldId"
                :primary-field-id="primaryField?.id"
                :secondary-field-id="secondaryField?.id"
                :drag-over-column="dragOverColumn"
                @add-card="addCard"
                @open-card="openCard"
                @dragstart="onDragStart"
                @dragover="onDragOver"
                @dragleave="onDragLeave"
                @drop="onDrop"
            />

            <!-- Status columns -->
            <KanbanColumn
                v-for="choice in choices"
                :key="choice.id"
                :choice="choice"
                :rows="rowsStore.rows"
                :group-by-field-id="groupByFieldId"
                :primary-field-id="primaryField?.id"
                :secondary-field-id="secondaryField?.id"
                :drag-over-column="dragOverColumn"
                @add-card="addCard"
                @open-card="openCard"
                @dragstart="onDragStart"
                @dragover="onDragOver"
                @dragleave="onDragLeave"
                @drop="onDrop"
            />
        </div>
    </div>

    <!-- Card Modal -->
    <CardModal
        :show="showCardModal"
        :card="selectedCard"
        :fields="fieldsStore.fields"
        @close="showCardModal = false"
        @update-field="updateCardField"
        @delete="deleteCard"
    />
</template>
