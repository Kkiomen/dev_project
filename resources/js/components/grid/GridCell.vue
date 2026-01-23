<script setup>
import { computed } from 'vue';
import TextCell from './cells/TextCell.vue';
import NumberCell from './cells/NumberCell.vue';
import DateCell from './cells/DateCell.vue';
import DatetimeCell from './cells/DatetimeCell.vue';
import CheckboxCell from './cells/CheckboxCell.vue';
import SelectCell from './cells/SelectCell.vue';
import MultiSelectCell from './cells/MultiSelectCell.vue';
import AttachmentCell from './cells/AttachmentCell.vue';
import UrlCell from './cells/UrlCell.vue';
import JsonCell from './cells/JsonCell.vue';

const props = defineProps({
    field: {
        type: Object,
        required: true,
    },
    value: {
        default: null,
    },
    rowId: {
        type: String,
        required: true,
    },
    isActive: {
        type: Boolean,
        default: false,
    },
    isEditing: {
        type: Boolean,
        default: false,
    },
    editingValue: {
        default: null,
    },
    uploading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'activate',
    'edit',
    'update:editingValue',
    'save',
    'cancel',
    'toggle-checkbox',
    'toggle-multiselect',
    'upload',
    'remove-attachment',
]);

const cellComponent = computed(() => {
    const components = {
        text: TextCell,
        number: NumberCell,
        date: DateCell,
        datetime: DatetimeCell,
        checkbox: CheckboxCell,
        select: SelectCell,
        multi_select: MultiSelectCell,
        attachment: AttachmentCell,
        url: UrlCell,
        json: JsonCell,
    };
    return components[props.field.type] || TextCell;
});

const cellProps = computed(() => {
    const baseProps = {
        value: props.isEditing ? props.editingValue : props.value,
        editing: props.isEditing,
    };

    switch (props.field.type) {
        case 'number':
            return {
                ...baseProps,
                precision: props.field.options?.precision ?? 2,
                step: props.field.options?.step ?? 1,
            };
        case 'select':
            return {
                ...baseProps,
                value: props.value,
                choices: props.field.options?.choices || [],
                editingValue: props.editingValue || '',
            };
        case 'multi_select':
            return {
                ...baseProps,
                value: props.value,
                choices: props.field.options?.choices || [],
                editingValue: props.editingValue,
            };
        case 'attachment':
            return { ...baseProps, uploading: props.uploading };
        default:
            return baseProps;
    }
});
</script>

<template>
    <td
        class="px-1 py-0.5 border-r border-b border-gray-200 relative cursor-pointer"
        :class="{
            'ring-2 ring-blue-500 ring-inset bg-blue-50/50': isActive,
        }"
        :style="{ width: field.width + 'px', minWidth: field.width + 'px' }"
        @click="emit('activate')"
        @dblclick="emit('edit')"
    >
        <component
            :is="cellComponent"
            v-bind="cellProps"
            @update:value="emit('update:editingValue', $event)"
            @save="emit('save')"
            @cancel="emit('cancel')"
            @toggle="field.type === 'checkbox' ? emit('toggle-checkbox') : emit('toggle-multiselect', $event)"
            @upload="emit('upload', $event)"
            @remove="emit('remove-attachment', $event)"
        />
    </td>
</template>
