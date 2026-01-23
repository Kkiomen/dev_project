<script setup>
import { computed } from 'vue';
import { useFiltersStore } from '@/stores/filters';
import FilterCondition from './FilterCondition.vue';

const props = defineProps({
    fields: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['apply', 'close']);

const filtersStore = useFiltersStore();

// Computed
const conditions = computed(() => filtersStore.conditions);
const conjunction = computed(() => filtersStore.conjunction);
const hasConditions = computed(() => conditions.value.length > 0);

// Filter fields that support filtering (exclude attachment)
const filterableFields = computed(() => {
    return props.fields.filter(f => f.type !== 'attachment');
});

// Methods
const addCondition = () => {
    if (filterableFields.value.length === 0) return;
    const firstField = filterableFields.value[0];
    filtersStore.addCondition(firstField.id, firstField.type);
};

const updateCondition = (conditionId, updates) => {
    filtersStore.updateCondition(conditionId, updates);
};

const removeCondition = (conditionId) => {
    filtersStore.removeCondition(conditionId);
};

const setConjunction = (value) => {
    filtersStore.setConjunction(value);
};

const clearAll = () => {
    filtersStore.clearFilters();
};

const applyFilters = () => {
    emit('apply');
};

const close = () => {
    emit('close');
};
</script>

<template>
    <div class="bg-white border border-gray-200 rounded-lg shadow-lg w-[500px] max-w-full">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Filtry</h3>
            <button
                type="button"
                @click="close"
                class="p-1 text-gray-400 hover:text-gray-600 rounded transition-colors"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Conjunction selector -->
        <div v-if="hasConditions" class="px-4 py-2 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-600">Dopasuj:</span>
                <select
                    :value="conjunction"
                    @change="setConjunction($event.target.value)"
                    class="text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500"
                >
                    <option value="and">Wszystkie warunki (AND)</option>
                    <option value="or">Dowolny warunek (OR)</option>
                </select>
            </div>
        </div>

        <!-- Conditions list -->
        <div class="p-4 space-y-2 max-h-80 overflow-y-auto">
            <FilterCondition
                v-for="condition in conditions"
                :key="condition.id"
                :condition="condition"
                :fields="filterableFields"
                @update="(updates) => updateCondition(condition.id, updates)"
                @remove="removeCondition(condition.id)"
            />

            <!-- Empty state -->
            <div v-if="!hasConditions" class="text-center py-6">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                <p class="text-sm text-gray-500 mb-2">{{ t('filter.noFilters') }}</p>
                <p class="text-xs text-gray-400">{{ t('filter.addConditionDescription') }}</p>
            </div>

            <!-- Add condition button -->
            <button
                type="button"
                @click="addCondition"
                class="w-full py-2 text-sm text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-md transition-colors flex items-center justify-center gap-1"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ t('filter.addCondition') }}
            </button>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-gray-200 bg-gray-50 rounded-b-lg">
            <button
                type="button"
                @click="clearAll"
                class="text-sm text-gray-600 hover:text-gray-800 transition-colors"
                :disabled="!hasConditions"
                :class="{ 'opacity-50 cursor-not-allowed': !hasConditions }"
            >
                {{ t('filter.clearAll') }}
            </button>
            <button
                type="button"
                @click="applyFilters"
                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors"
            >
                {{ t('filter.applyFilters') }}
            </button>
        </div>
    </div>
</template>
