import { defineStore } from 'pinia';
import axios from 'axios';

export const useProposalsStore = defineStore('proposals', {
    state: () => ({
        proposals: [],
        calendarProposals: {},
        loading: false,
        saving: false,
        generating: false,
        pagination: {
            currentPage: 1,
            lastPage: 1,
            total: 0,
        },
    }),

    actions: {
        async fetchProposals(params = {}) {
            this.loading = true;
            try {
                const response = await axios.get('/api/v1/proposals', { params });
                this.proposals = response.data.data;
                this.pagination = {
                    currentPage: response.data.meta.current_page,
                    lastPage: response.data.meta.last_page,
                    total: response.data.meta.total,
                };
                return response.data;
            } catch (error) {
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchCalendarProposals(start, end, brandId = null) {
            try {
                const params = { start, end };
                if (brandId) params.brand_id = brandId;
                const response = await axios.get('/api/v1/proposals/calendar', { params });

                const grouped = {};
                response.data.data.forEach(proposal => {
                    const date = proposal.scheduled_date;
                    if (!grouped[date]) {
                        grouped[date] = [];
                    }
                    grouped[date].push(proposal);
                });

                this.calendarProposals = grouped;
                return grouped;
            } catch (error) {
                throw error;
            }
        },

        async createProposal(data) {
            this.saving = true;
            try {
                const response = await axios.post('/api/v1/proposals', data);
                return response.data.data;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async updateProposal(id, data) {
            this.saving = true;
            try {
                const response = await axios.put(`/api/v1/proposals/${id}`, data);
                const updated = response.data.data;

                const idx = this.proposals.findIndex(p => p.id === id);
                if (idx !== -1) {
                    this.proposals[idx] = updated;
                }

                return updated;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async fetchNextFreeDate(brandId = null) {
            try {
                const params = {};
                if (brandId) params.brand_id = brandId;
                const response = await axios.get('/api/v1/proposals/next-free-date', { params });
                return response.data.date;
            } catch (error) {
                throw error;
            }
        },

        async deleteProposal(id) {
            try {
                await axios.delete(`/api/v1/proposals/${id}`);

                this.proposals = this.proposals.filter(p => p.id !== id);

                Object.keys(this.calendarProposals).forEach(date => {
                    this.calendarProposals[date] = this.calendarProposals[date].filter(p => p.id !== id);
                });
            } catch (error) {
                throw error;
            }
        },

        async generateBatch(days, brandId, language) {
            this.generating = true;
            try {
                const response = await axios.post('/api/v1/proposals/generate-batch', {
                    days,
                    brand_id: brandId,
                    language,
                });
                return response.data;
            } catch (error) {
                throw error;
            } finally {
                this.generating = false;
            }
        },

        async generatePost(proposalId) {
            try {
                const response = await axios.post(`/api/v1/proposals/${proposalId}/generate-post`);

                const updatedProposal = response.data.proposal;
                if (updatedProposal) {
                    const idx = this.proposals.findIndex(p => p.id === proposalId);
                    if (idx !== -1) {
                        this.proposals[idx] = updatedProposal;
                    }
                }

                return response.data;
            } catch (error) {
                throw error;
            }
        },
    },
});
