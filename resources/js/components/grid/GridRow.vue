<script setup>
import GridCell from './GridCell.vue';

const props = defineProps({
    row: {
        type: Object,
        required: true,
    },
    rowIndex: {
        type: Number,
        required: true,
    },
    fields: {
        type: Array,
        required: true,
    },
    activeCell: {
        type: Object,
        default: null,
    },
    editingCell: {
        type: Object,
        default: null,
    },
    editingValue: {
        default: null,
    },
    selectedRowId: {
        type: String,
        default: null,
    },
    uploading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'activate-cell',
    'edit-cell',
    'update:editingValue',
    'save-cell',
    'cancel-edit',
    'toggle-checkbox',
    'toggle-multiselect',
    'upload-attachment',
    'remove-attachment',
    'duplicate-row',
    'delete-row',
]);

const getCellValue = (fieldId) => {
    return props.row.values?.[fieldId] ?? null;
};

const isActiveCell = (fieldId) => {
    return props.activeCell?.row === props.row.id && props.activeCell?.field === fieldId;
};

const isEditingCell = (fieldId) => {
    return props.editingCell?.row === props.row.id && props.editingCell?.field === fieldId;
};
</script>

<template>
    <tr
        class="group hover:bg-blue-50/30"
        :class="{ 'bg-blue-50/50': selectedRowId === row.id }"
    >
        <!-- Row number -->
        <td class="px-2 py-1 text-xs text-gray-400 border-r border-b border-gray-200 text-center bg-gray-50/50">
            <div class="flex items-center justify-center space-x-1">
                <span class="group-hover:hidden">{{ rowIndex + 1 }}</span>
                <div class="hidden group-hover:flex items-center space-x-1">
                    <button
                        @click="emit('duplicate-row', row.id)"
                        title="Duplikuj"
                        class="text-gray-400 hover:text-blue-500"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </button>
                    <button
                        @click="emit('delete-row', row.id)"
                        title="Usun"
                        class="text-gray-400 hover:text-red-500"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </td>

        <!-- Cells -->
        <GridCell
            v-for="field in fields"
            :key="field.id"
            :field="field"
            :value="getCellValue(field.id)"
            :row-id="row.id"
            :is-active="isActiveCell(field.id)"
            :is-editing="isEditingCell(field.id)"
            :editing-value="isEditingCell(field.id) ? editingValue : null"
            :uploading="uploading"
            @activate="emit('activate-cell', row.id, field.id)"
            @edit="emit('edit-cell', row.id, field.id)"
            @update:editingValue="emit('update:editingValue', $event)"
            @save="emit('save-cell')"
            @cancel="emit('cancel-edit')"
            @toggle-checkbox="emit('toggle-checkbox', row.id, field.id, getCellValue(field.id))"
            @toggle-multiselect="emit('toggle-multiselect', $event)"
            @upload="emit('upload-attachment', $event, row.id, field.id)"
            @remove-attachment="emit('remove-attachment', row.id, field.id, $event)"
        />

        <!-- Empty cell for add column -->
        <td class="border-b border-gray-200"></td>
    </tr>
</template>
