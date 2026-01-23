<script setup>
import { ref, computed } from 'vue';
import { useFieldsStore } from '@/stores/fields';
import { useRowsStore } from '@/stores/rows';
import { useCellsStore } from '@/stores/cells';
import { useFiltersStore } from '@/stores/filters';
import { useKeyboard } from '@/composables/useKeyboard';
import { useResize } from '@/composables/useResize';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import GridHeader from './GridHeader.vue';
import GridRow from './GridRow.vue';
import FieldMenu from './FieldMenu.vue';
import AddFieldModal from './AddFieldModal.vue';
import FilterToolbar from './FilterToolbar.vue';

const toast = useToast();
const { confirm } = useConfirm();

const props = defineProps({
    tableId: {
        type: String,
        required: true,
    },
});

const fieldsStore = useFieldsStore();
const rowsStore = useRowsStore();
const cellsStore = useCellsStore();
const filtersStore = useFiltersStore();

// Fetch rows with current filters/sort
const fetchWithFilters = async () => {
    try {
        await rowsStore.fetchRows(props.tableId);
    } catch (error) {
        console.error('Failed to fetch rows:', error);
        toast.error('Nie udało się pobrać danych');
    }
};

// Handle sort from header click
const handleSort = () => {
    fetchWithFilters();
};

// UI State
const searchQuery = ref('');
const activeCell = ref(null);
const editingCell = ref(null);
const editingValue = ref(null);
const selectedRowId = ref(null);

// Field menu
const fieldMenuOpen = ref(false);
const fieldMenuPosition = ref({ x: 0, y: 0 });
const selectedField = ref(null);

// Field modal
const showFieldModal = ref(false);
const editingFieldId = ref(null);

// Computed
const filteredRows = computed(() => {
    if (!searchQuery.value.trim()) {
        return rowsStore.rows;
    }
    const query = searchQuery.value.toLowerCase();
    return rowsStore.rows.filter(row => {
        return Object.values(row.values || {}).some(value => {
            if (value === null || value === undefined) return false;
            if (typeof value === 'object') {
                return JSON.stringify(value).toLowerCase().includes(query);
            }
            return String(value).toLowerCase().includes(query);
        });
    });
});

// Cell operations
const activateCell = (rowId, fieldId) => {
    activeCell.value = { row: rowId, field: fieldId };
    selectedRowId.value = rowId;
};

const editCell = (rowId, fieldId) => {
    const field = fieldsStore.getFieldById(fieldId);
    if (!field || field.type === 'checkbox' || field.type === 'attachment') return;

    editingCell.value = { row: rowId, field: fieldId };
    const row = rowsStore.getRowById(rowId);
    const value = row?.values?.[fieldId];

    if (field.type === 'select') {
        editingValue.value = value?.id || '';
    } else if (field.type === 'multi_select') {
        editingValue.value = (value || []).map(v => v.id || v);
    } else if (field.type === 'date' && value) {
        // For date type, only use the date portion
        editingValue.value = value.substring(0, 10);
    } else if (field.type === 'datetime' && value) {
        // For datetime type, convert to datetime-local format
        editingValue.value = value.replace(' ', 'T').substring(0, 16);
    } else if (field.type === 'json') {
        // For JSON, keep the original value (object/array/string)
        editingValue.value = value;
    } else {
        editingValue.value = value ?? '';
    }
};

const saveCell = async () => {
    if (!editingCell.value) return;

    const { row: rowId, field: fieldId } = editingCell.value;
    const field = fieldsStore.getFieldById(fieldId);
    let value = editingValue.value;

    if (value === '' || value === null) {
        value = null;
    } else if (field.type === 'number') {
        value = parseFloat(value);
    }

    editingCell.value = null;
    editingValue.value = null;

    try {
        await cellsStore.updateCell(rowId, fieldId, value);

        // For select/multi_select, update local row with full objects
        const row = rowsStore.getRowById(rowId);
        if (row && field.options?.choices) {
            if (field.type === 'select') {
                if (value) {
                    const choice = field.options.choices.find(c => c.id === value);
                    if (choice) {
                        row.values[fieldId] = { id: choice.id, name: choice.name, color: choice.color };
                    }
                } else {
                    row.values[fieldId] = null;
                }
            } else if (field.type === 'multi_select') {
                if (Array.isArray(value) && value.length > 0) {
                    row.values[fieldId] = value.map(id => {
                        const choice = field.options.choices.find(c => c.id === id);
                        return choice ? { id: choice.id, name: choice.name, color: choice.color } : null;
                    }).filter(Boolean);
                } else {
                    row.values[fieldId] = [];
                }
            }
        }

        toast.success('Zapisano');
    } catch (error) {
        console.error('Failed to save cell:', error);
        toast.error('Błąd zapisu');
    }
};

const cancelEdit = () => {
    editingCell.value = null;
    editingValue.value = null;
};

const toggleCheckbox = async (rowId, fieldId, currentValue) => {
    try {
        await cellsStore.updateCell(rowId, fieldId, !currentValue);
        toast.success('Zapisano');
    } catch (error) {
        console.error('Failed to toggle checkbox:', error);
        toast.error('Błąd zapisu');
    }
};

const toggleMultiSelectChoice = (choiceId) => {
    if (!Array.isArray(editingValue.value)) {
        editingValue.value = [];
    }
    const index = editingValue.value.indexOf(choiceId);
    if (index === -1) {
        editingValue.value.push(choiceId);
    } else {
        editingValue.value.splice(index, 1);
    }
};

// Row operations
const addRow = async () => {
    try {
        const newRow = await rowsStore.createRow(props.tableId, {});
        if (fieldsStore.fields.length > 0) {
            editCell(newRow.id, fieldsStore.fields[0].id);
        }
    } catch (error) {
        console.error('Failed to add row:', error);
    }
};

const duplicateRow = async (rowId) => {
    const row = rowsStore.getRowById(rowId);
    if (!row) return;

    const values = {};
    for (const [fieldId, value] of Object.entries(row.values || {})) {
        const field = fieldsStore.getFieldById(fieldId);
        if (field?.type === 'select' && value?.id) {
            values[fieldId] = value.id;
        } else if (field?.type === 'multi_select' && Array.isArray(value)) {
            values[fieldId] = value.map(v => v.id || v);
        } else if (field?.type !== 'attachment') {
            values[fieldId] = value;
        }
    }

    try {
        await rowsStore.createRow(props.tableId, values);
    } catch (error) {
        console.error('Failed to duplicate row:', error);
    }
};

const deleteRow = async (rowId) => {
    const confirmed = await confirm({
        title: 'Usuń wiersz',
        message: 'Czy na pewno chcesz usunąć ten wiersz? Ta operacja jest nieodwracalna.',
        confirmText: 'Usuń',
        variant: 'danger',
    });
    if (!confirmed) return;

    try {
        await rowsStore.deleteRow(rowId);
        if (selectedRowId.value === rowId) {
            selectedRowId.value = null;
            activeCell.value = null;
        }
        toast.success('Wiersz usunięty');
    } catch (error) {
        console.error('Failed to delete row:', error);
        toast.error('Nie udało się usunąć wiersza');
    }
};

// Attachment operations
const uploadAttachment = async (event, rowId, fieldId) => {
    const files = event.target.files;
    if (!files.length) return;

    for (const file of files) {
        try {
            const attachment = await cellsStore.uploadAttachment(rowId, fieldId, file);
            const row = rowsStore.getRowById(rowId);
            if (row) {
                if (!row.values[fieldId]) row.values[fieldId] = [];
                row.values[fieldId] = [...row.values[fieldId], attachment];
            }
        } catch (error) {
            console.error('Failed to upload:', error);
            toast.error('Nie udało się przesłać pliku');
        }
    }
    event.target.value = '';
};

const removeAttachment = async (rowId, fieldId, attachmentId) => {
    const confirmed = await confirm({
        title: 'Usuń załącznik',
        message: 'Czy na pewno chcesz usunąć ten załącznik?',
        confirmText: 'Usuń',
        variant: 'danger',
    });
    if (!confirmed) return;

    try {
        await cellsStore.deleteAttachment(attachmentId);
        const row = rowsStore.getRowById(rowId);
        if (row?.values?.[fieldId]) {
            row.values[fieldId] = row.values[fieldId].filter(a => a.id !== attachmentId);
        }
        toast.success('Załącznik usunięty');
    } catch (error) {
        console.error('Failed to remove attachment:', error);
        toast.error('Nie udało się usunąć załącznika');
    }
};

// Field operations
const openFieldMenu = (event, field) => {
    selectedField.value = field;
    fieldMenuPosition.value = { x: event.clientX, y: event.clientY };
    fieldMenuOpen.value = true;
};

const closeFieldMenu = () => {
    fieldMenuOpen.value = false;
};

const openAddFieldModal = () => {
    editingFieldId.value = null;
    showFieldModal.value = true;
};

const openEditFieldModal = () => {
    closeFieldMenu();
    editingFieldId.value = selectedField.value?.id;
    showFieldModal.value = true;
};

const closeFieldModal = () => {
    showFieldModal.value = false;
    editingFieldId.value = null;
};

const saveField = async (payload) => {
    try {
        if (editingFieldId.value) {
            await fieldsStore.updateField(editingFieldId.value, payload);
            toast.success('Pole zaktualizowane');
        } else {
            await fieldsStore.createField(props.tableId, payload);
            toast.success('Pole dodane');
        }
        closeFieldModal();
    } catch (error) {
        console.error('Failed to save field:', error);
        toast.error('Nie udało się zapisać pola');
    }
};

const deleteField = async () => {
    if (selectedField.value?.is_primary) {
        toast.error('Nie można usunąć pola głównego');
        return;
    }

    const confirmed = await confirm({
        title: 'Usuń pole',
        message: `Czy na pewno chcesz usunąć pole "${selectedField.value?.name}"? Wszystkie dane w tym polu zostaną utracone.`,
        confirmText: 'Usuń',
        variant: 'danger',
    });

    if (!confirmed) {
        closeFieldMenu();
        return;
    }

    try {
        await fieldsStore.deleteField(selectedField.value.id);
        toast.success('Pole usunięte');
    } catch (error) {
        console.error('Failed to delete field:', error);
        toast.error('Nie udało się usunąć pola');
    }
    closeFieldMenu();
};

const moveFieldLeft = async () => {
    const index = fieldsStore.fields.indexOf(selectedField.value);
    if (index <= 0) return;

    try {
        await fieldsStore.reorderField(selectedField.value.id, index - 1);
    } catch (error) {
        console.error('Failed to move field:', error);
    }
    closeFieldMenu();
};

const moveFieldRight = async () => {
    const index = fieldsStore.fields.indexOf(selectedField.value);
    if (index >= fieldsStore.fields.length - 1) return;

    try {
        await fieldsStore.reorderField(selectedField.value.id, index + 1);
    } catch (error) {
        console.error('Failed to move field:', error);
    }
    closeFieldMenu();
};

const renameField = async (fieldId, newName) => {
    try {
        await fieldsStore.updateField(fieldId, { name: newName });
        toast.success('Nazwa zmieniona');
    } catch (error) {
        console.error('Failed to rename field:', error);
        toast.error('Nie udało się zmienić nazwy pola');
    }
};

// Column resize
const { startResize } = useResize(async (target, width) => {
    try {
        await fieldsStore.updateField(target.id, { width });
    } catch (error) {
        console.error('Failed to save width:', error);
    }
});

const handleStartResize = (event, field) => {
    startResize(event, field, field.width);
};

// Keyboard navigation
useKeyboard({
    enabled: computed(() => !editingCell.value && !showFieldModal.value),
    onArrowUp: () => {
        if (!activeCell.value) return;
        const rowIndex = filteredRows.value.findIndex(r => r.id === activeCell.value.row);
        if (rowIndex > 0) {
            activateCell(filteredRows.value[rowIndex - 1].id, activeCell.value.field);
        }
    },
    onArrowDown: () => {
        if (!activeCell.value) return;
        const rowIndex = filteredRows.value.findIndex(r => r.id === activeCell.value.row);
        if (rowIndex < filteredRows.value.length - 1) {
            activateCell(filteredRows.value[rowIndex + 1].id, activeCell.value.field);
        }
    },
    onArrowLeft: () => {
        if (!activeCell.value) return;
        const fieldIndex = fieldsStore.fields.findIndex(f => f.id === activeCell.value.field);
        if (fieldIndex > 0) {
            activateCell(activeCell.value.row, fieldsStore.fields[fieldIndex - 1].id);
        }
    },
    onArrowRight: () => {
        if (!activeCell.value) return;
        const fieldIndex = fieldsStore.fields.findIndex(f => f.id === activeCell.value.field);
        if (fieldIndex < fieldsStore.fields.length - 1) {
            activateCell(activeCell.value.row, fieldsStore.fields[fieldIndex + 1].id);
        }
    },
    onEnter: () => {
        if (activeCell.value) {
            editCell(activeCell.value.row, activeCell.value.field);
        }
    },
    onEscape: () => {
        cancelEdit();
    },
    onDelete: () => {
        if (!activeCell.value) return;
        const field = fieldsStore.getFieldById(activeCell.value.field);
        if (field?.type !== 'attachment') {
            cellsStore.updateCell(activeCell.value.row, activeCell.value.field, null);
        }
    },
});

// Expose for parent
defineExpose({
    addRow,
    searchQuery,
    fetchWithFilters,
});
</script>

<template>
    <div class="flex-1 flex flex-col overflow-hidden bg-white">
        <!-- Filter Toolbar -->
        <div class="flex-shrink-0 px-4 py-2 border-b border-gray-200 bg-white">
            <FilterToolbar
                :fields="fieldsStore.fields"
                @apply="fetchWithFilters"
            />
        </div>

        <!-- Table -->
        <div class="flex-1 overflow-auto">
            <table class="w-full border-collapse min-w-max">
                <GridHeader
                    :fields="fieldsStore.fields"
                    @open-field-menu="openFieldMenu"
                    @start-resize="handleStartResize"
                    @add-field="openAddFieldModal"
                    @rename-field="renameField"
                    @sort="handleSort"
                />

            <tbody>
                <GridRow
                    v-for="(row, rowIndex) in filteredRows"
                    :key="row.id"
                    :row="row"
                    :row-index="rowIndex"
                    :fields="fieldsStore.fields"
                    :active-cell="activeCell"
                    :editing-cell="editingCell"
                    :editing-value="editingValue"
                    :selected-row-id="selectedRowId"
                    :uploading="cellsStore.uploading"
                    @activate-cell="activateCell"
                    @edit-cell="editCell"
                    @update:editingValue="editingValue = $event"
                    @save-cell="saveCell"
                    @cancel-edit="cancelEdit"
                    @toggle-checkbox="toggleCheckbox"
                    @toggle-multiselect="toggleMultiSelectChoice"
                    @upload-attachment="uploadAttachment"
                    @remove-attachment="removeAttachment"
                    @duplicate-row="duplicateRow"
                    @delete-row="deleteRow"
                />

                <!-- Add row placeholder -->
                <tr class="hover:bg-gray-50 cursor-pointer" @click="addRow">
                    <td class="px-2 py-2 text-xs text-gray-400 border-r border-gray-200 text-center">+</td>
                    <td :colspan="fieldsStore.fields.length + 1" class="px-2 py-2 text-sm text-gray-400">
                        Kliknij aby dodać nowy wiersz...
                    </td>
                </tr>
            </tbody>
            </table>
        </div>
    </div>

    <!-- Field Menu -->
    <FieldMenu
        :show="fieldMenuOpen"
        :field="selectedField"
        :position="fieldMenuPosition"
        :is-first="fieldsStore.fields.indexOf(selectedField) === 0"
        :is-last="fieldsStore.fields.indexOf(selectedField) === fieldsStore.fields.length - 1"
        @close="closeFieldMenu"
        @rename="openEditFieldModal"
        @edit-type="openEditFieldModal"
        @manage-options="openEditFieldModal"
        @move-left="moveFieldLeft"
        @move-right="moveFieldRight"
        @delete="deleteField"
    />

    <!-- Add/Edit Field Modal -->
    <AddFieldModal
        :show="showFieldModal"
        :editing-field="editingFieldId ? fieldsStore.getFieldById(editingFieldId) : null"
        @close="closeFieldModal"
        @save="saveField"
    />
</template>
