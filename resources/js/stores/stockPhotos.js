import { defineStore } from 'pinia';
import axios from 'axios';

export const useStockPhotosStore = defineStore('stockPhotos', {
    state: () => ({
        photos: [],
        providers: [],
        loading: false,
        error: null,
        lastKeywords: [],
    }),

    getters: {
        hasPhotos: (state) => state.photos.length > 0,
        isAvailable: (state) => state.providers.length > 0,
    },

    actions: {
        async search(keywords, perPage = 9) {
            this.loading = true;
            this.error = null;
            this.lastKeywords = keywords;

            try {
                const response = await axios.get('/api/v1/stock-photos/search', {
                    params: {
                        keywords,
                        per_page: perPage,
                    },
                });

                this.photos = response.data.photos;
                this.providers = response.data.providers;

                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to search stock photos';
                this.photos = [];
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async featured(perPage = 9) {
            this.loading = true;
            this.error = null;
            this.lastKeywords = [];

            try {
                const response = await axios.get('/api/v1/stock-photos/featured', {
                    params: {
                        per_page: perPage,
                    },
                });

                this.photos = response.data.photos;
                this.providers = response.data.providers;

                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to load featured photos';
                this.photos = [];
                throw error;
            } finally {
                this.loading = false;
            }
        },

        clearPhotos() {
            this.photos = [];
            this.lastKeywords = [];
        },

        reset() {
            this.photos = [];
            this.providers = [];
            this.loading = false;
            this.error = null;
            this.lastKeywords = [];
        },
    },
});
