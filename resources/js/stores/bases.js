import { defineStore } from 'pinia';
import axios from 'axios';

export const useBasesStore = defineStore('bases', {
    state: () => ({
        bases: [],
        currentBase: null,
        loading: false,
        error: null,
    }),

    getters: {
        getBaseById: (state) => (id) => state.bases.find(b => b.id === id),
    },

    actions: {
        async fetchBases() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/bases');
                this.bases = response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch bases';
            } finally {
                this.loading = false;
            }
        },

        async fetchBase(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/v1/bases/${id}`);
                this.currentBase = response.data.data;
                return this.currentBase;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch base';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createBase(data) {
            try {
                const response = await axios.post('/api/v1/bases', data);
                const newBase = response.data.data;
                this.bases.unshift(newBase);
                return newBase;
            } catch (error) {
                throw error;
            }
        },

        async updateBase(id, data) {
            try {
                const response = await axios.put(`/api/v1/bases/${id}`, data);
                const updatedBase = response.data.data;
                const index = this.bases.findIndex(b => b.id === id);
                if (index !== -1) {
                    this.bases[index] = { ...this.bases[index], ...updatedBase };
                }
                if (this.currentBase?.id === id) {
                    this.currentBase = { ...this.currentBase, ...updatedBase };
                }
                return updatedBase;
            } catch (error) {
                throw error;
            }
        },

        async deleteBase(id) {
            try {
                await axios.delete(`/api/v1/bases/${id}`);
                this.bases = this.bases.filter(b => b.id !== id);
                if (this.currentBase?.id === id) {
                    this.currentBase = null;
                }
            } catch (error) {
                throw error;
            }
        },
    },
});
