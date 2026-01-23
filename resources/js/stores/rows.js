import { defineStore } from 'pinia';
import axios from 'axios';
import { useFiltersStore } from './filters';

export const useRowsStore = defineStore('rows', {
    state: () => ({
        rows: [],
        loading: false,
        error: null,
        pagination: {
            currentPage: 1,
            lastPage: 1,
            perPage: 50,
            total: 0,
        },
    }),

    getters: {
        getRowById: (state) => (id) => state.rows.find(r => r.id === id),
    },

    actions: {
        setRows(rows) {
            this.rows = rows;
        },

        async fetchRows(tableId, page = 1, perPage = 50) {
            this.loading = true;
            this.error = null;

            const filtersStore = useFiltersStore();

            try {
                const params = {
                    page,
                    per_page: perPage,
                };

                // Add filters if present
                if (filtersStore.filtersPayload) {
                    params.filters = JSON.stringify(filtersStore.filtersPayload);
                }

                // Add sort if present
                if (filtersStore.sortPayload) {
                    params.sort = JSON.stringify(filtersStore.sortPayload);
                }

                const response = await axios.get(`/api/v1/tables/${tableId}/rows`, { params });

                this.rows = response.data.data;

                // Update pagination info
                if (response.data.meta) {
                    this.pagination = {
                        currentPage: response.data.meta.current_page,
                        lastPage: response.data.meta.last_page,
                        perPage: response.data.meta.per_page,
                        total: response.data.meta.total,
                    };
                }

                // Mark filters as clean after successful fetch
                filtersStore.markClean();

                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch rows';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createRow(tableId, values = {}) {
            try {
                const response = await axios.post(`/api/v1/tables/${tableId}/rows`, { values });
                const newRow = response.data.data;
                this.rows.push(newRow);
                return newRow;
            } catch (error) {
                throw error;
            }
        },

        async updateRow(id, data) {
            try {
                const response = await axios.put(`/api/v1/rows/${id}`, data);
                const updatedRow = response.data.data;
                const index = this.rows.findIndex(r => r.id === id);
                if (index !== -1) {
                    this.rows[index] = updatedRow;
                }
                return updatedRow;
            } catch (error) {
                throw error;
            }
        },

        async deleteRow(id) {
            try {
                await axios.delete(`/api/v1/rows/${id}`);
                this.rows = this.rows.filter(r => r.id !== id);
            } catch (error) {
                throw error;
            }
        },

        async bulkCreateRows(tableId, rowsData) {
            try {
                const response = await axios.post(`/api/v1/tables/${tableId}/rows/bulk`, {
                    rows: rowsData,
                });
                const newRows = response.data.data;
                this.rows.push(...newRows);
                return newRows;
            } catch (error) {
                throw error;
            }
        },

        async bulkDeleteRows(tableId, ids) {
            try {
                await axios.delete(`/api/v1/tables/${tableId}/rows/bulk`, {
                    data: { ids },
                });
                this.rows = this.rows.filter(r => !ids.includes(r.id));
            } catch (error) {
                throw error;
            }
        },

        async reorderRow(id, position) {
            try {
                await axios.post(`/api/v1/rows/${id}/reorder`, { position });
            } catch (error) {
                throw error;
            }
        },

        // Local update for optimistic UI
        updateRowValue(rowId, fieldId, value) {
            const row = this.rows.find(r => r.id === rowId);
            if (row) {
                if (!row.values) row.values = {};
                row.values[fieldId] = value;
            }
        },
    },
});
