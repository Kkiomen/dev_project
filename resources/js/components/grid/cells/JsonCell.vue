<script setup>
import { ref, computed, watch } from 'vue';
import JsonEditorModal from './JsonEditorModal.vue';

const props = defineProps({
    value: {
        type: [Object, Array, String],
        default: null,
    },
    editing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:value', 'save', 'cancel']);

const showModal = ref(false);

// Auto-open modal when editing starts
watch(() => props.editing, (isEditing) => {
    if (isEditing) {
        showModal.value = true;
    }
});

const parseValue = (val) => {
    if (val === null || val === undefined) return null;
    if (typeof val === 'string') {
        try {
            return JSON.parse(val);
        } catch {
            return val;
        }
    }
    return val;
};

const displayValue = computed(() => {
    const data = parseValue(props.value);
    if (data === null || data === undefined) return null;

    if (Array.isArray(data)) {
        return `[Array: ${data.length} elementów]`;
    }

    if (typeof data === 'object') {
        const keys = Object.keys(data);
        if (keys.length === 0) return '{}';
        const preview = keys.slice(0, 2).map(k => {
            const v = data[k];
            const vStr = typeof v === 'string' ? `"${v}"` : JSON.stringify(v);
            return `${k}: ${vStr.length > 15 ? vStr.substring(0, 15) + '...' : vStr}`;
        }).join(', ');
        return `{${preview}${keys.length > 2 ? ', ...' : ''}}`;
    }

    return String(data);
});

const handleSave = (newValue) => {
    emit('update:value', newValue);
    showModal.value = false;
    emit('save');
};

const handleClose = () => {
    showModal.value = false;
    emit('cancel');
};
</script>

<template>
    <div class="min-h-[32px] px-2 py-1">
        <!-- Preview - clicking handled by parent GridCell -->
        <div class="text-sm font-mono truncate">
            <span v-if="!displayValue" class="text-gray-400 italic">
                {{ editing ? '' : 'kliknij 2x aby edytować' }}
            </span>
            <span v-else class="text-gray-600" :title="JSON.stringify(parseValue(value), null, 2)">
                {{ displayValue }}
            </span>
        </div>

        <!-- JSON Editor Modal -->
        <JsonEditorModal
            :show="showModal"
            :value="value"
            @close="handleClose"
            @save="handleSave"
        />
    </div>
</template>
