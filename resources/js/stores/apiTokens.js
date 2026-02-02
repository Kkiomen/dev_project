import { defineStore } from 'pinia';
import axios from 'axios';

export const useApiTokensStore = defineStore('apiTokens', {
    state: () => ({
        tokens: [],
        loading: false,
        error: null,
    }),

    getters: {
        activeTokens: (state) => state.tokens.filter(t => !t.expires_at || new Date(t.expires_at) > new Date()),
        expiredTokens: (state) => state.tokens.filter(t => t.expires_at && new Date(t.expires_at) <= new Date()),
    },

    actions: {
        async fetchTokens() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/api-tokens');
                this.tokens = response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch tokens';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createToken(data) {
            try {
                const response = await axios.post('/api/v1/api-tokens', data);
                const newToken = response.data.data;
                this.tokens.unshift({
                    id: newToken.id,
                    name: newToken.name,
                    abilities: newToken.abilities,
                    expires_at: newToken.expires_at,
                    created_at: newToken.created_at,
                    last_used_at: null,
                });
                return newToken;
            } catch (error) {
                throw error;
            }
        },

        async revokeToken(id) {
            try {
                await axios.delete(`/api/v1/api-tokens/${id}`);
                this.tokens = this.tokens.filter(t => t.id !== id);
            } catch (error) {
                throw error;
            }
        },

        reset() {
            this.tokens = [];
            this.loading = false;
            this.error = null;
        },
    },
});
