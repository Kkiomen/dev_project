import { defineStore } from 'pinia';
import axios from 'axios';
import { useRowsStore } from './rows';

export const useCellsStore = defineStore('cells', {
    state: () => ({
        pendingUpdates: new Map(),
        uploading: false,
    }),

    actions: {
        async updateCell(rowId, fieldId, value) {
            const rowsStore = useRowsStore();

            // Optimistic update
            rowsStore.updateRowValue(rowId, fieldId, value);

            try {
                const response = await axios.put(`/api/v1/rows/${rowId}/cells/${fieldId}`, { value });
                return response.data.data;
            } catch (error) {
                // Revert on error (would need original value)
                throw error;
            }
        },

        async bulkUpdateCells(rowId, values) {
            const rowsStore = useRowsStore();
            const row = rowsStore.getRowById(rowId);

            // Optimistic update
            if (row) {
                Object.entries(values).forEach(([fieldId, value]) => {
                    rowsStore.updateRowValue(rowId, fieldId, value);
                });
            }

            try {
                const response = await axios.put(`/api/v1/rows/${rowId}/cells`, { values });
                return response.data.data;
            } catch (error) {
                throw error;
            }
        },

        async uploadAttachment(rowId, fieldId, file) {
            this.uploading = true;
            try {
                // First ensure cell exists
                const cellResponse = await axios.put(`/api/v1/rows/${rowId}/cells/${fieldId}`, {
                    value: [],
                });
                const cellId = cellResponse.data.data.id;

                // Upload file
                const formData = new FormData();
                formData.append('file', file);

                const response = await axios.post(`/api/v1/cells/${cellId}/attachments`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                return response.data.data;
            } catch (error) {
                throw error;
            } finally {
                this.uploading = false;
            }
        },

        async deleteAttachment(attachmentId) {
            try {
                await axios.delete(`/api/v1/attachments/${attachmentId}`);
            } catch (error) {
                throw error;
            }
        },

        async reorderAttachment(attachmentId, position) {
            try {
                await axios.post(`/api/v1/attachments/${attachmentId}/reorder`, { position });
            } catch (error) {
                throw error;
            }
        },
    },
});
