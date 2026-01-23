import { defineStore } from 'pinia';
import axios from 'axios';

export const useFieldsStore = defineStore('fields', {
    state: () => ({
        fields: [],
        loading: false,
        error: null,
    }),

    getters: {
        getFieldById: (state) => (id) => state.fields.find(f => f.id === id),
        primaryField: (state) => state.fields.find(f => f.is_primary),
        selectFields: (state) => state.fields.filter(f => f.type === 'select' || f.type === 'multi_select'),
    },

    actions: {
        setFields(fields) {
            this.fields = fields;
        },

        async fetchFields(tableId) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/v1/tables/${tableId}/fields`);
                this.fields = response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch fields';
            } finally {
                this.loading = false;
            }
        },

        async createField(tableId, data) {
            try {
                const response = await axios.post(`/api/v1/tables/${tableId}/fields`, data);
                const newField = response.data.data;
                this.fields.push(newField);
                return newField;
            } catch (error) {
                throw error;
            }
        },

        async updateField(id, data) {
            try {
                const response = await axios.put(`/api/v1/fields/${id}`, data);
                const updatedField = response.data.data;
                const index = this.fields.findIndex(f => f.id === id);
                if (index !== -1) {
                    this.fields[index] = updatedField;
                }
                return updatedField;
            } catch (error) {
                throw error;
            }
        },

        async deleteField(id) {
            try {
                await axios.delete(`/api/v1/fields/${id}`);
                this.fields = this.fields.filter(f => f.id !== id);
            } catch (error) {
                throw error;
            }
        },

        async reorderField(id, position) {
            try {
                await axios.post(`/api/v1/fields/${id}/reorder`, { position });
                // Reorder locally
                const field = this.fields.find(f => f.id === id);
                if (field) {
                    const currentIndex = this.fields.indexOf(field);
                    this.fields.splice(currentIndex, 1);
                    this.fields.splice(position, 0, field);
                }
            } catch (error) {
                throw error;
            }
        },

        async addChoice(fieldId, data) {
            try {
                const response = await axios.post(`/api/v1/fields/${fieldId}/choices`, data);
                const updatedField = response.data.data;
                const index = this.fields.findIndex(f => f.id === fieldId);
                if (index !== -1) {
                    this.fields[index] = updatedField;
                }
                return updatedField;
            } catch (error) {
                throw error;
            }
        },
    },
});
