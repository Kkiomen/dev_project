import { defineStore } from 'pinia';
import { getDefaultOperator } from '@/config/filterOperators';

export const useFiltersStore = defineStore('filters', {
    state: () => ({
        // Filter state
        conditions: [],
        conjunction: 'and',

        // Sort state
        sort: [],

        // UI state
        isFilterPanelOpen: false,
        isDirty: false,
    }),

    getters: {
        /**
         * Check if there are any active filters.
         */
        hasActiveFilters: (state) => state.conditions.length > 0,

        /**
         * Get count of active filter conditions.
         */
        activeFilterCount: (state) => state.conditions.length,

        /**
         * Check if there's any sorting applied.
         */
        hasActiveSort: (state) => state.sort.length > 0,

        /**
         * Get the current sort for a specific field.
         */
        getSortForField: (state) => (fieldId) => {
            return state.sort.find(s => s.field_id === fieldId);
        },

        /**
         * Build the filters object for API request.
         */
        filtersPayload: (state) => {
            if (state.conditions.length === 0) {
                return null;
            }
            return {
                conjunction: state.conjunction,
                conditions: state.conditions.map(c => ({
                    field_id: c.field_id,
                    operator: c.operator,
                    value: c.value,
                })),
            };
        },

        /**
         * Build the sort array for API request.
         */
        sortPayload: (state) => {
            if (state.sort.length === 0) {
                return null;
            }
            return state.sort.map(s => ({
                field_id: s.field_id,
                direction: s.direction,
            }));
        },
    },

    actions: {
        /**
         * Add a new filter condition.
         */
        addCondition(fieldId, fieldType) {
            const condition = {
                id: crypto.randomUUID(),
                field_id: fieldId,
                field_type: fieldType,
                operator: getDefaultOperator(fieldType),
                value: null,
            };
            this.conditions.push(condition);
            this.isDirty = true;
        },

        /**
         * Update a filter condition.
         */
        updateCondition(conditionId, updates) {
            const condition = this.conditions.find(c => c.id === conditionId);
            if (condition) {
                Object.assign(condition, updates);
                this.isDirty = true;
            }
        },

        /**
         * Remove a filter condition.
         */
        removeCondition(conditionId) {
            const index = this.conditions.findIndex(c => c.id === conditionId);
            if (index !== -1) {
                this.conditions.splice(index, 1);
                this.isDirty = true;
            }
        },

        /**
         * Set the conjunction (and/or).
         */
        setConjunction(conjunction) {
            this.conjunction = conjunction;
            this.isDirty = true;
        },

        /**
         * Clear all filter conditions.
         */
        clearFilters() {
            this.conditions = [];
            this.isDirty = true;
        },

        /**
         * Toggle sort for a field.
         * Cycles through: none -> asc -> desc -> none
         */
        toggleSort(fieldId) {
            const existingIndex = this.sort.findIndex(s => s.field_id === fieldId);

            if (existingIndex === -1) {
                // No sort for this field, add asc
                this.sort = [{ field_id: fieldId, direction: 'asc' }];
            } else {
                const current = this.sort[existingIndex];
                if (current.direction === 'asc') {
                    // Switch to desc
                    this.sort[existingIndex].direction = 'desc';
                } else {
                    // Remove sort
                    this.sort.splice(existingIndex, 1);
                }
            }
            this.isDirty = true;
        },

        /**
         * Set sort for a field (replace existing sort).
         */
        setSort(fieldId, direction) {
            if (direction === null) {
                this.sort = this.sort.filter(s => s.field_id !== fieldId);
            } else {
                this.sort = [{ field_id: fieldId, direction }];
            }
            this.isDirty = true;
        },

        /**
         * Clear all sorting.
         */
        clearSort() {
            this.sort = [];
            this.isDirty = true;
        },

        /**
         * Clear all filters and sorting.
         */
        clearAll() {
            this.conditions = [];
            this.sort = [];
            this.conjunction = 'and';
            this.isDirty = true;
        },

        /**
         * Toggle filter panel visibility.
         */
        toggleFilterPanel() {
            this.isFilterPanelOpen = !this.isFilterPanelOpen;
        },

        /**
         * Open filter panel.
         */
        openFilterPanel() {
            this.isFilterPanelOpen = true;
        },

        /**
         * Close filter panel.
         */
        closeFilterPanel() {
            this.isFilterPanelOpen = false;
        },

        /**
         * Mark as not dirty (after applying filters).
         */
        markClean() {
            this.isDirty = false;
        },

        /**
         * Reset the store to initial state.
         */
        reset() {
            this.conditions = [];
            this.conjunction = 'and';
            this.sort = [];
            this.isFilterPanelOpen = false;
            this.isDirty = false;
        },
    },
});
