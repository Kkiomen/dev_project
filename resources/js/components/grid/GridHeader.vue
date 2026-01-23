<script setup>
import { ref, nextTick, computed } from 'vue';
import { useFiltersStore } from '@/stores/filters';

const props = defineProps({
    fields: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['open-field-menu', 'start-resize', 'add-field', 'rename-field', 'sort']);

const filtersStore = useFiltersStore();

// Get sort direction for a field
const getSortDirection = (fieldId) => {
    const sort = filtersStore.getSortForField(fieldId);
    return sort?.direction || null;
};

// Handle sort click
const handleSortClick = (field) => {
    filtersStore.toggleSort(field.id);
    emit('sort', field.id);
};

// Inline editing state
const editingFieldId = ref(null);
const editingName = ref('');
const inputRef = ref(null);

const getFieldIcon = (type) => {
    const icons = {
        text: 'Aa',
        number: '#',
        date: '📅',
        datetime: '🕐',
        checkbox: '☑️',
        select: '▼',
        multi_select: '≡',
        attachment: '📎',
        url: '🔗',
        json: '{ }',
    };
    return icons[type] || '?';
};

const startEditing = async (field) => {
    editingFieldId.value = field.id;
    editingName.value = field.name;
    await nextTick();
    inputRef.value?.focus();
    inputRef.value?.select();
};

const saveEditing = () => {
    if (!editingFieldId.value) return;

    const newName = editingName.value.trim();
    if (newName && newName !== props.fields.find(f => f.id === editingFieldId.value)?.name) {
        emit('rename-field', editingFieldId.value, newName);
    }
    cancelEditing();
};

const cancelEditing = () => {
    editingFieldId.value = null;
    editingName.value = '';
};

const handleKeydown = (event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        saveEditing();
    } else if (event.key === 'Escape') {
        event.preventDefault();
        cancelEditing();
    }
};
</script>

<template>
    <thead class="sticky top-0 z-10 bg-gray-50">
        <tr>
            <!-- Row number column -->
            <th class="w-12 min-w-[48px] px-2 py-2 text-xs font-medium text-gray-500 border-b border-r border-gray-200 bg-gray-50">
                #
            </th>

            <!-- Field columns -->
            <th
                v-for="field in fields"
                :key="field.id"
                class="relative px-3 py-2 text-left text-xs font-medium text-gray-700 border-b border-r border-gray-200 bg-gray-50 group select-none"
                :style="{ width: field.width + 'px', minWidth: field.width + 'px' }"
            >
                <div
                    class="flex items-center space-x-2 pr-12 cursor-pointer"
                    @click="handleSortClick(field)"
                >
                    <span class="text-gray-400">{{ getFieldIcon(field.type) }}</span>

                    <!-- Editing mode -->
                    <input
                        v-if="editingFieldId === field.id"
                        ref="inputRef"
                        v-model="editingName"
                        type="text"
                        class="flex-1 min-w-0 px-1 py-0.5 text-xs font-medium border border-blue-500 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                        @keydown="handleKeydown"
                        @blur="saveEditing"
                        @click.stop
                    />

                    <!-- Display mode -->
                    <span
                        v-else
                        class="truncate hover:text-blue-600"
                        @dblclick.stop="startEditing(field)"
                    >
                        {{ field.name }}
                    </span>

                    <span v-if="field.is_primary" class="text-blue-500 text-xs">*</span>

                    <!-- Sort indicator -->
                    <span v-if="getSortDirection(field.id)" class="text-blue-500">
                        <svg v-if="getSortDirection(field.id) === 'asc'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                        </svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </span>
                </div>

                <!-- Field menu button -->
                <button
                    v-if="editingFieldId !== field.id"
                    @click.stop="emit('open-field-menu', $event, field)"
                    class="absolute right-1 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                    </svg>
                </button>

                <!-- Resize handle -->
                <div
                    class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize hover:bg-blue-500 transition-colors"
                    @mousedown.stop.prevent="emit('start-resize', $event, field)"
                ></div>
            </th>

            <!-- Add field column -->
            <th class="w-10 min-w-[40px] px-2 py-2 border-b border-gray-200 bg-gray-50">
                <button
                    @click="emit('add-field')"
                    class="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
            </th>
        </tr>
    </thead>
</template>
