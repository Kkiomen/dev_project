<script setup>
import { computed } from 'vue';
import KanbanCard from './KanbanCard.vue';

const props = defineProps({
    choice: {
        type: Object,
        default: null, // null for "No status" column
    },
    rows: {
        type: Array,
        default: () => [],
    },
    groupByFieldId: {
        type: String,
        required: true,
    },
    primaryFieldId: {
        type: String,
        required: true,
    },
    secondaryFieldId: {
        type: String,
        default: null,
    },
    dragOverColumn: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['add-card', 'open-card', 'dragstart', 'dragover', 'dragleave', 'drop']);

const columnId = computed(() => props.choice?.id || '');

const columnRows = computed(() => {
    return props.rows.filter(row => {
        const value = row.values?.[props.groupByFieldId];
        if (!props.choice) {
            return !value;
        }
        return value === props.choice.id;
    });
});

const getPrimaryValue = (row) => {
    return row.values?.[props.primaryFieldId] || 'Bez nazwy';
};

const getSecondaryValue = (row) => {
    if (!props.secondaryFieldId) return '';
    return row.values?.[props.secondaryFieldId] || '';
};

const isDragOver = computed(() => props.dragOverColumn === columnId.value);
</script>

<template>
    <div class="w-72 flex-shrink-0 flex flex-col bg-gray-200 rounded-lg">
        <!-- Column header -->
        <div class="px-3 py-2 font-medium border-b border-gray-300 flex items-center space-x-2">
            <span
                v-if="choice"
                class="w-3 h-3 rounded-full"
                :style="{ backgroundColor: choice.color }"
            ></span>
            <span>{{ choice?.name || 'Bez statusu' }}</span>
            <span class="ml-auto text-sm text-gray-500">{{ columnRows.length }}</span>
        </div>

        <!-- Cards container -->
        <div
            class="flex-1 overflow-y-auto p-2 space-y-2 transition-colors"
            :class="{ 'bg-blue-100': isDragOver }"
            :data-column-id="columnId"
            @dragover.prevent="emit('dragover', $event, columnId)"
            @dragleave="emit('dragleave')"
            @drop.prevent="emit('drop', $event, columnId)"
        >
            <KanbanCard
                v-for="row in columnRows"
                :key="row.id"
                :row="row"
                :primary-value="getPrimaryValue(row)"
                :secondary-value="getSecondaryValue(row)"
                @click="emit('open-card', row)"
                @dragstart="emit('dragstart', $event, row)"
            />

            <button
                @click="emit('add-card', columnId)"
                class="w-full py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded"
            >
                + Dodaj karte
            </button>
        </div>
    </div>
</template>
