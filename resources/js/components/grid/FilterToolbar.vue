<script setup>
import { ref, computed } from 'vue';
import { useFiltersStore } from '@/stores/filters';
import FilterModal from './FilterModal.vue';

const props = defineProps({
    fields: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['apply']);

const filtersStore = useFiltersStore();

// State
const showFilterModal = ref(false);

// Computed
const activeFilterCount = computed(() => filtersStore.activeFilterCount);
const hasActiveFilters = computed(() => filtersStore.hasActiveFilters);
const hasActiveSort = computed(() => filtersStore.hasActiveSort);
const currentSortField = computed(() => {
    if (!filtersStore.sort.length) return null;
    const sortInfo = filtersStore.sort[0];
    const field = props.fields.find(f => f.id === sortInfo.field_id);
    return field ? { field, direction: sortInfo.direction } : null;
});

// Methods
const openFilterModal = () => {
    showFilterModal.value = true;
};

const closeFilterModal = () => {
    showFilterModal.value = false;
};

const applyFilters = () => {
    emit('apply');
};

const clearFilters = () => {
    filtersStore.clearFilters();
    emit('apply');
};

const clearSort = () => {
    filtersStore.clearSort();
    emit('apply');
};

// Expose for parent to open modal with specific field
const openWithField = (field) => {
    filtersStore.addCondition(field.id, field.type);
    showFilterModal.value = true;
};

defineExpose({
    openWithField,
});
</script>

<template>
    <div class="flex items-center gap-3 flex-wrap">
        <!-- Filter button -->
        <button
            type="button"
            @click="openFilterModal"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-lg transition-all duration-200"
            :class="hasActiveFilters
                ? 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 shadow-sm'
                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-gray-400'"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            <span>Filtruj</span>
            <span
                v-if="hasActiveFilters"
                class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-xs font-semibold bg-blue-600 text-white rounded-full"
            >
                {{ activeFilterCount }}
            </span>
        </button>

        <!-- Active filters chips -->
        <div v-if="hasActiveFilters" class="flex items-center gap-2">
            <span class="text-sm text-gray-500">|</span>
            <button
                @click="clearFilters"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100 rounded-full hover:bg-blue-200 transition-colors"
            >
                <span>{{ activeFilterCount }} {{ activeFilterCount === 1 ? 'filtr' : 'filtry' }}</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Sort indicator -->
        <div v-if="hasActiveSort && currentSortField" class="flex items-center gap-2">
            <span v-if="!hasActiveFilters" class="text-sm text-gray-500">|</span>
            <button
                @click="clearSort"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        v-if="currentSortField.direction === 'asc'"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"
                    />
                    <path
                        v-else
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"
                    />
                </svg>
                <span>{{ currentSortField.field.name }}</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Filter Modal -->
    <FilterModal
        :show="showFilterModal"
        :fields="fields"
        @close="closeFilterModal"
        @apply="applyFilters"
    />
</template>
