import { defineStore } from 'pinia';
import axios from 'axios';

export const useApprovalStore = defineStore('approval', {
    state: () => ({
        tokens: [],
        currentToken: null,
        pendingPosts: [],
        approvalHistory: [],
        loading: false,
        error: null,
        clientInfo: null,
    }),

    getters: {
        getTokenById: (state) => (id) => state.tokens.find(t => t.id === id),
        activeTokens: (state) => state.tokens.filter(t => t.is_active && t.is_valid),
        expiredTokens: (state) => state.tokens.filter(t => t.is_expired),
    },

    actions: {
        // Manager actions
        async fetchTokens(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/approval-tokens', { params });
                this.tokens = response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch tokens';
            } finally {
                this.loading = false;
            }
        },

        async createToken(data) {
            try {
                const response = await axios.post('/api/v1/approval-tokens', data);
                const newToken = response.data.data;
                this.tokens.unshift(newToken);
                return newToken;
            } catch (error) {
                throw error;
            }
        },

        async revokeToken(id) {
            try {
                await axios.delete(`/api/v1/approval-tokens/${id}`);
                const index = this.tokens.findIndex(t => t.id === id);
                if (index !== -1) {
                    this.tokens[index].is_active = false;
                }
            } catch (error) {
                throw error;
            }
        },

        async regenerateToken(id) {
            try {
                const response = await axios.post(`/api/v1/approval-tokens/${id}/regenerate`);
                const updatedToken = response.data.data;
                const index = this.tokens.findIndex(t => t.id === id);
                if (index !== -1) {
                    this.tokens[index] = updatedToken;
                }
                return updatedToken;
            } catch (error) {
                throw error;
            }
        },

        async getTokenStats(id) {
            try {
                const response = await axios.get(`/api/v1/approval-tokens/${id}/stats`);
                return response.data;
            } catch (error) {
                throw error;
            }
        },

        // Client actions (public)
        async validateToken(token) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/v1/approve/${token}`);
                this.currentToken = token;
                this.clientInfo = {
                    clientName: response.data.client_name,
                    expiresAt: response.data.expires_at,
                };
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Invalid or expired token';
                this.currentToken = null;
                this.clientInfo = null;
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchPendingPosts(token) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/v1/approve/${token}/posts`);
                this.pendingPosts = response.data.data;
                return this.pendingPosts;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch pending posts';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchPostForApproval(token, postId) {
            this.loading = true;
            try {
                const response = await axios.get(`/api/v1/approve/${token}/posts/${postId}`);
                return response.data.data;
            } catch (error) {
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async submitApproval(token, postId, approved, notes = null) {
            try {
                const response = await axios.post(`/api/v1/approve/${token}/posts/${postId}/respond`, {
                    approved,
                    notes,
                });

                // Remove from pending posts
                this.pendingPosts = this.pendingPosts.filter(p => p.id !== postId);

                return response.data;
            } catch (error) {
                throw error;
            }
        },

        async fetchApprovalHistory(token) {
            this.loading = true;
            try {
                const response = await axios.get(`/api/v1/approve/${token}/history`);
                this.approvalHistory = response.data.data;
                return this.approvalHistory;
            } catch (error) {
                throw error;
            } finally {
                this.loading = false;
            }
        },

        clearClientSession() {
            this.currentToken = null;
            this.clientInfo = null;
            this.pendingPosts = [];
            this.approvalHistory = [];
        },

        reset() {
            this.tokens = [];
            this.currentToken = null;
            this.pendingPosts = [];
            this.approvalHistory = [];
            this.loading = false;
            this.error = null;
            this.clientInfo = null;
        },
    },
});
