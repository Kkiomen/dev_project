import { defineStore } from 'pinia';
import axios from 'axios';
import { useBrandsStore } from '@/stores/brands';

export const useCompetitiveIntelligenceStore = defineStore('competitiveIntelligence', {
    state: () => ({
        competitors: [],
        competitorsLoading: false,

        currentCompetitor: null,
        currentCompetitorLoading: false,

        competitorPosts: [],
        competitorPostsMeta: null,
        competitorPostsLoading: false,

        insights: [],
        insightsMeta: null,
        insightsLoading: false,

        trends: [],
        trendsLoading: false,

        benchmarks: null,
        benchmarksLoading: false,

        scrapeStatus: null,
        scrapeStatusLoading: false,

        costData: null,
        costLoading: false,

        saving: false,

        discoveredCompetitors: [],
        discovering: false,
    }),

    getters: {
        currentBrandId() {
            const brandsStore = useBrandsStore();
            return brandsStore.currentBrand?.id;
        },

        activeCompetitors(state) {
            return state.competitors.filter(c => c.is_active);
        },

        unactionedInsights(state) {
            return state.insights.filter(i => !i.is_actioned);
        },

        highPriorityInsights(state) {
            return state.insights.filter(i => i.priority >= 7 && !i.is_actioned);
        },

        risingTrends(state) {
            return state.trends.filter(t => t.trend_direction === 'rising' || t.trend_direction === 'breakout');
        },

        budgetUsagePercent(state) {
            if (!state.costData?.current) return 0;
            const { total_cost, budget_limit } = state.costData.current;
            return budget_limit > 0 ? Math.round((total_cost / budget_limit) * 100) : 0;
        },
    },

    actions: {
        // === Competitors CRUD ===
        async fetchCompetitors() {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.competitorsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/ci/competitors`);
                this.competitors = response.data.data;
            } catch (error) { throw error; }
            finally { this.competitorsLoading = false; }
        },

        async addCompetitor(data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.saving = true;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/ci/competitors`, data);
                this.competitors.push(response.data.data);
                return response.data.data;
            } catch (error) { throw error; }
            finally { this.saving = false; }
        },

        async updateCompetitor(competitorId, data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.saving = true;
            try {
                const response = await axios.put(`/api/v1/brands/${brandId}/ci/competitors/${competitorId}`, data);
                const index = this.competitors.findIndex(c => c.id === competitorId || c.public_id === competitorId);
                if (index !== -1) {
                    this.competitors[index] = response.data.data;
                }
                return response.data.data;
            } catch (error) { throw error; }
            finally { this.saving = false; }
        },

        async removeCompetitor(competitorId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                await axios.delete(`/api/v1/brands/${brandId}/ci/competitors/${competitorId}`);
                this.competitors = this.competitors.filter(c => c.public_id !== competitorId && c.id !== competitorId);
            } catch (error) { throw error; }
        },

        async fetchCompetitor(competitorId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.currentCompetitorLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/ci/competitors/${competitorId}`);
                this.currentCompetitor = response.data.data;
            } catch (error) { throw error; }
            finally { this.currentCompetitorLoading = false; }
        },

        async fetchCompetitorPosts(competitorId, params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.competitorPostsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/ci/competitors/${competitorId}/posts`, { params });
                this.competitorPosts = response.data.data;
                this.competitorPostsMeta = response.data.meta;
            } catch (error) { throw error; }
            finally { this.competitorPostsLoading = false; }
        },

        // === Insights ===
        async fetchInsights(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.insightsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/ci/insights`, { params });
                this.insights = response.data.data;
                this.insightsMeta = response.data.meta;
            } catch (error) { throw error; }
            finally { this.insightsLoading = false; }
        },

        async actionInsight(insightId, actionTaken) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/ci/insights/${insightId}/action`, {
                    action_taken: actionTaken,
                });
                const index = this.insights.findIndex(i => i.public_id === insightId || i.id === insightId);
                if (index !== -1) {
                    this.insights[index] = response.data.data;
                }
                return response.data.data;
            } catch (error) { throw error; }
        },

        // === Trends ===
        async fetchTrends(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.trendsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/ci/trends`, { params });
                this.trends = response.data.data;
            } catch (error) { throw error; }
            finally { this.trendsLoading = false; }
        },

        // === Benchmarks ===
        async fetchBenchmarks() {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.benchmarksLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/ci/benchmarks`);
                this.benchmarks = response.data.data;
            } catch (error) { throw error; }
            finally { this.benchmarksLoading = false; }
        },

        // === Scraping ===
        async triggerScrape(type) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/ci/scrape`, { type });
                return response.data;
            } catch (error) { throw error; }
        },

        async fetchScrapeStatus() {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.scrapeStatusLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/ci/scrape-status`);
                this.scrapeStatus = response.data.data;
            } catch (error) { throw error; }
            finally { this.scrapeStatusLoading = false; }
        },

        // === Cost ===
        async fetchCost() {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.costLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/ci/cost`);
                this.costData = response.data.data;
            } catch (error) { throw error; }
            finally { this.costLoading = false; }
        },

        // === Discover ===
        async discoverCompetitors(platforms = []) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.discovering = true;
            this.discoveredCompetitors = [];
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/ci/discover-competitors`, { platforms });
                this.discoveredCompetitors = response.data.data;
                return this.discoveredCompetitors;
            } catch (error) { throw error; }
            finally { this.discovering = false; }
        },

        // === Bulk fetch for dashboard ===
        async fetchDashboardData() {
            await Promise.allSettled([
                this.fetchCompetitors(),
                this.fetchInsights({ unactioned_only: true }),
                this.fetchTrends(),
                this.fetchBenchmarks(),
                this.fetchCost(),
            ]);
        },
    },
});
