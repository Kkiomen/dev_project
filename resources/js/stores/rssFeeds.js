import { defineStore } from 'pinia';
import axios from 'axios';

export const useRssFeedsStore = defineStore('rssFeeds', {
    state: () => ({
        feeds: [],
        articles: [],
        loading: false,
        articlesLoading: false,
        articlesMeta: null,
        error: null,
    }),

    getters: {
        getFeedById: (state) => (id) => state.feeds.find(f => f.id === id),
        totalArticlesCount: (state) => state.feeds.reduce((sum, f) => sum + (f.articles_count || 0), 0),
        activeFeedsCount: (state) => state.feeds.filter(f => f.status === 'active').length,
    },

    actions: {
        async fetchFeeds() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/rss-feeds');
                this.feeds = response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch feeds';
            } finally {
                this.loading = false;
            }
        },

        async createFeed(data) {
            const response = await axios.post('/api/v1/rss-feeds', data);
            const newFeed = response.data.data;
            this.feeds.unshift(newFeed);
            return newFeed;
        },

        async updateFeed(id, data) {
            const response = await axios.put(`/api/v1/rss-feeds/${id}`, data);
            const updated = response.data.data;
            const index = this.feeds.findIndex(f => f.id === id);
            if (index !== -1) {
                this.feeds[index] = { ...this.feeds[index], ...updated };
            }
            return updated;
        },

        async deleteFeed(id) {
            await axios.delete(`/api/v1/rss-feeds/${id}`);
            this.feeds = this.feeds.filter(f => f.id !== id);
        },

        async refreshFeed(id) {
            const response = await axios.post(`/api/v1/rss-feeds/${id}/refresh`);
            const updated = response.data.data;
            const index = this.feeds.findIndex(f => f.id === id);
            if (index !== -1) {
                this.feeds[index] = { ...this.feeds[index], ...updated };
            }
            return updated;
        },

        async fetchArticles(params = {}) {
            this.articlesLoading = true;
            try {
                const response = await axios.get('/api/v1/rss-articles', { params });
                this.articles = response.data.data;
                this.articlesMeta = response.data.meta || null;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch articles';
            } finally {
                this.articlesLoading = false;
            }
        },

        async fetchFeedArticles(feedId, params = {}) {
            this.articlesLoading = true;
            try {
                const response = await axios.get(`/api/v1/rss-feeds/${feedId}/articles`, { params });
                this.articles = response.data.data;
                this.articlesMeta = response.data.meta || null;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch articles';
            } finally {
                this.articlesLoading = false;
            }
        },
    },
});
