import { ref, computed } from 'vue';
import { useCellsStore } from '@/stores/cells';
import { useFieldsStore } from '@/stores/fields';

export function useCell() {
    const cellsStore = useCellsStore();
    const fieldsStore = useFieldsStore();

    const editingCell = ref(null);
    const editingValue = ref(null);

    const isEditing = computed(() => editingCell.value !== null);

    const startEdit = (rowId, fieldId, currentValue) => {
        const field = fieldsStore.getFieldById(fieldId);
        if (!field || field.type === 'checkbox' || field.type === 'attachment') {
            return false;
        }

        editingCell.value = { rowId, fieldId };

        if (field.type === 'select') {
            editingValue.value = currentValue?.id || '';
        } else if (field.type === 'multi_select') {
            editingValue.value = (currentValue || []).map(v => v.id || v);
        } else if (field.type === 'date' && currentValue) {
            editingValue.value = currentValue.replace(' ', 'T').substring(0, 16);
        } else {
            editingValue.value = currentValue ?? '';
        }

        return true;
    };

    const cancelEdit = () => {
        editingCell.value = null;
        editingValue.value = null;
    };

    const saveEdit = async () => {
        if (!editingCell.value) return;

        const { rowId, fieldId } = editingCell.value;
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
        } catch (error) {
            console.error('Failed to save cell:', error);
        }
    };

    const toggleCheckbox = async (rowId, fieldId, currentValue) => {
        try {
            await cellsStore.updateCell(rowId, fieldId, !currentValue);
        } catch (error) {
            console.error('Failed to toggle checkbox:', error);
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

    return {
        editingCell,
        editingValue,
        isEditing,
        startEdit,
        cancelEdit,
        saveEdit,
        toggleCheckbox,
        toggleMultiSelectChoice,
    };
}
