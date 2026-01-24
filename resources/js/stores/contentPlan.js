import { defineStore } from 'pinia';
import axios from 'axios';

export const useContentPlanStore = defineStore('contentPlan', {
    state: () => ({
        generatingPlan: false,
        generatingContent: false,
        error: null,
        lastGeneratedPlan: null,
        generatedContent: null,
    }),

    actions: {
        async generatePlan(brandId, period = 'week', startDate = null, async = false) {
            this.generatingPlan = true;
            this.error = null;

            try {
                const response = await axios.post('/api/v1/content-plan/generate', {
                    brand_id: brandId,
                    period,
                    start_date: startDate,
                    async,
                });

                if (!async) {
                    this.lastGeneratedPlan = response.data.plan;
                }

                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to generate content plan';
                throw error;
            } finally {
                this.generatingPlan = false;
            }
        },

        async generateContent(brandId, config) {
            this.generatingContent = true;
            this.error = null;

            try {
                const response = await axios.post('/api/v1/content-plan/generate-content', {
                    brand_id: brandId,
                    ...config,
                });

                this.generatedContent = response.data.content;
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to generate content';
                throw error;
            } finally {
                this.generatingContent = false;
            }
        },

        async regenerateContent(brandId, config, feedback) {
            this.generatingContent = true;
            this.error = null;

            try {
                const response = await axios.post('/api/v1/content-plan/regenerate-content', {
                    brand_id: brandId,
                    feedback,
                    ...config,
                });

                this.generatedContent = response.data.content;
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to regenerate content';
                throw error;
            } finally {
                this.generatingContent = false;
            }
        },

        clearGeneratedContent() {
            this.generatedContent = null;
        },

        reset() {
            this.generatingPlan = false;
            this.generatingContent = false;
            this.error = null;
            this.lastGeneratedPlan = null;
            this.generatedContent = null;
        },
    },
});
