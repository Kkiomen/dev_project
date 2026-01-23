import { defineStore } from 'pinia';
import axios from 'axios';

export const useTablesStore = defineStore('tables', {
    state: () => ({
        tables: [],
        currentTable: null,
        loading: false,
        error: null,
    }),

    getters: {
        getTableById: (state) => (id) => state.tables.find(t => t.id === id),
    },

    actions: {
        async fetchTables(baseId) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/v1/bases/${baseId}/tables`);
                this.tables = response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch tables';
            } finally {
                this.loading = false;
            }
        },

        async fetchTable(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/v1/tables/${id}`);
                this.currentTable = response.data.data;
                return this.currentTable;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch table';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createTable(baseId, data) {
            try {
                const response = await axios.post(`/api/v1/bases/${baseId}/tables`, data);
                const newTable = response.data.data;
                this.tables.push(newTable);
                return newTable;
            } catch (error) {
                throw error;
            }
        },

        async updateTable(id, data) {
            try {
                const response = await axios.put(`/api/v1/tables/${id}`, data);
                const updatedTable = response.data.data;
                const index = this.tables.findIndex(t => t.id === id);
                if (index !== -1) {
                    this.tables[index] = { ...this.tables[index], ...updatedTable };
                }
                if (this.currentTable?.id === id) {
                    this.currentTable = { ...this.currentTable, ...updatedTable };
                }
                return updatedTable;
            } catch (error) {
                throw error;
            }
        },

        async deleteTable(id) {
            try {
                await axios.delete(`/api/v1/tables/${id}`);
                this.tables = this.tables.filter(t => t.id !== id);
                if (this.currentTable?.id === id) {
                    this.currentTable = null;
                }
            } catch (error) {
                throw error;
            }
        },

        async reorderTable(id, position) {
            try {
                await axios.post(`/api/v1/tables/${id}/reorder`, { position });
            } catch (error) {
                throw error;
            }
        },
    },
});
