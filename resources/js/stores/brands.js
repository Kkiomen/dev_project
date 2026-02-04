import { defineStore } from 'pinia';
import axios from 'axios';

const ONBOARDING_STORAGE_KEY = 'brands_onboarding_draft';

export const useBrandsStore = defineStore('brands', {
    state: () => ({
        brands: [],
        currentBrand: null,
        loading: false,
        saving: false,
        error: null,
        onboardingStep: 1,
        onboardingData: getDefaultOnboardingData(),
    }),

    getters: {
        getBrandById: (state) => (id) => state.brands.find(b => b.id === id),
        activeBrands: (state) => state.brands.filter(b => b.is_active),
        hasCompletedOnboarding: (state) => state.currentBrand?.onboarding_completed ?? false,
        enabledPlatforms: (state) => state.currentBrand?.enabled_platforms ?? [],
    },

    actions: {
        async fetchBrands() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/brands');
                this.brands = response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch brands';
            } finally {
                this.loading = false;
            }
        },

        async fetchCurrentBrand() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/brands/current');
                this.currentBrand = response.data.brand;
                return this.currentBrand;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch current brand';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchBrand(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/v1/brands/${id}`);
                const brand = response.data.data;

                const index = this.brands.findIndex(b => b.id === id);
                if (index !== -1) {
                    this.brands[index] = brand;
                }

                return brand;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch brand';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createBrand(data) {
            this.saving = true;
            try {
                const response = await axios.post('/api/v1/brands', data);
                const newBrand = response.data.data;
                this.brands.unshift(newBrand);

                // If this is the first brand, set it as current
                if (this.brands.length === 1 || !this.currentBrand) {
                    this.currentBrand = newBrand;
                }

                return newBrand;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async updateBrand(id, data) {
            this.saving = true;
            try {
                const response = await axios.put(`/api/v1/brands/${id}`, data);
                const updatedBrand = response.data.data;

                const index = this.brands.findIndex(b => b.id === id);
                if (index !== -1) {
                    this.brands[index] = updatedBrand;
                }

                if (this.currentBrand?.id === id) {
                    this.currentBrand = updatedBrand;
                }

                return updatedBrand;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async deleteBrand(id) {
            try {
                await axios.delete(`/api/v1/brands/${id}`);
                this.brands = this.brands.filter(b => b.id !== id);

                if (this.currentBrand?.id === id) {
                    this.currentBrand = this.brands[0] || null;
                }
            } catch (error) {
                throw error;
            }
        },

        async setCurrentBrand(id) {
            this.saving = true;
            try {
                const response = await axios.post(`/api/v1/brands/${id}/set-current`);
                this.currentBrand = response.data.brand;
                return this.currentBrand;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async completeOnboarding(id) {
            this.saving = true;
            try {
                const response = await axios.post(`/api/v1/brands/${id}/complete-onboarding`);
                const updatedBrand = response.data.data;

                const index = this.brands.findIndex(b => b.id === id);
                if (index !== -1) {
                    this.brands[index] = updatedBrand;
                }

                if (this.currentBrand?.id === id) {
                    this.currentBrand = updatedBrand;
                }

                return updatedBrand;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        // Onboarding wizard methods
        setOnboardingStep(step) {
            this.onboardingStep = step;
            this.saveOnboardingToStorage();
        },

        nextStep() {
            if (this.onboardingStep < 5) {
                this.onboardingStep++;
                this.saveOnboardingToStorage();
            }
        },

        previousStep() {
            if (this.onboardingStep > 1) {
                this.onboardingStep--;
                this.saveOnboardingToStorage();
            }
        },

        updateOnboardingData(data) {
            this.onboardingData = { ...this.onboardingData, ...data };
            this.saveOnboardingToStorage();
        },

        resetOnboarding() {
            this.onboardingStep = 1;
            this.onboardingData = getDefaultOnboardingData();
            this.clearOnboardingStorage();
        },

        // LocalStorage persistence for onboarding
        saveOnboardingToStorage() {
            try {
                const data = {
                    step: this.onboardingStep,
                    data: this.onboardingData,
                    savedAt: Date.now(),
                };
                localStorage.setItem(ONBOARDING_STORAGE_KEY, JSON.stringify(data));
            } catch (e) {
                console.warn('Failed to save onboarding to localStorage:', e);
            }
        },

        loadOnboardingFromStorage() {
            try {
                const stored = localStorage.getItem(ONBOARDING_STORAGE_KEY);
                if (!stored) return false;

                const data = JSON.parse(stored);

                // Check if data is not too old (24 hours)
                const maxAge = 24 * 60 * 60 * 1000;
                if (Date.now() - data.savedAt > maxAge) {
                    this.clearOnboardingStorage();
                    return false;
                }

                // Restore the data
                if (data.step) {
                    this.onboardingStep = data.step;
                }
                if (data.data) {
                    // Merge with defaults to ensure all fields exist
                    this.onboardingData = {
                        ...getDefaultOnboardingData(),
                        ...data.data,
                    };
                }

                return true;
            } catch (e) {
                console.warn('Failed to load onboarding from localStorage:', e);
                return false;
            }
        },

        clearOnboardingStorage() {
            try {
                localStorage.removeItem(ONBOARDING_STORAGE_KEY);
            } catch (e) {
                console.warn('Failed to clear onboarding from localStorage:', e);
            }
        },

        hasOnboardingDraft() {
            try {
                const stored = localStorage.getItem(ONBOARDING_STORAGE_KEY);
                if (!stored) return false;

                const data = JSON.parse(stored);
                const maxAge = 24 * 60 * 60 * 1000;
                return Date.now() - data.savedAt <= maxAge;
            } catch (e) {
                return false;
            }
        },

        async generateAiSuggestions(type) {
            const response = await axios.post('/api/v1/brands/ai/suggestions', {
                type,
                brandData: {
                    name: this.onboardingData.name,
                    description: this.onboardingData.description,
                    industry: this.onboardingData.industry,
                    ageRange: this.onboardingData.ageRange,
                    gender: this.onboardingData.gender,
                },
            });

            if (!response.data.success) {
                throw new Error(response.data.message || 'Failed to generate suggestions');
            }

            return response.data.data;
        },

        async saveOnboardingBrand() {
            this.saving = true;
            try {
                // Convert onboarding data to brand format
                const brandData = {
                    name: this.onboardingData.name,
                    industry: this.onboardingData.industry,
                    description: this.onboardingData.description,
                    target_audience: {
                        age_range: this.onboardingData.ageRange,
                        gender: this.onboardingData.gender,
                        interests: this.onboardingData.interests,
                        pain_points: this.onboardingData.painPoints,
                    },
                    voice: {
                        tone: this.onboardingData.tone,
                        personality: this.onboardingData.personality,
                        language: this.onboardingData.language,
                        emoji_usage: this.onboardingData.emojiUsage,
                    },
                    content_pillars: this.onboardingData.contentPillars,
                    posting_preferences: {
                        frequency: this.onboardingData.frequency,
                        best_times: this.onboardingData.bestTimes,
                        auto_schedule: this.onboardingData.autoSchedule,
                    },
                    platforms: this.onboardingData.platforms,
                    onboarding_completed: true,
                };

                const response = await axios.post('/api/v1/brands', brandData);
                const newBrand = response.data.data;
                this.brands.unshift(newBrand);

                // Set as current brand on backend and frontend
                await this.setCurrentBrand(newBrand.id);

                // Complete onboarding
                await this.completeOnboarding(newBrand.id);

                // Reset onboarding state and clear storage
                this.resetOnboarding();

                return newBrand;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        reset() {
            this.brands = [];
            this.currentBrand = null;
            this.loading = false;
            this.saving = false;
            this.error = null;
            this.resetOnboarding();
        },

        // Automation methods
        async fetchAutomationStats(brandId) {
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/automation/stats`);
                return response.data.data;
            } catch (error) {
                throw error;
            }
        },

        async enableAutomation(brandId, settings = {}) {
            this.saving = true;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/automation/enable`, settings);
                const updatedBrand = response.data.brand;

                const index = this.brands.findIndex(b => b.id === brandId);
                if (index !== -1) {
                    this.brands[index] = updatedBrand;
                }

                if (this.currentBrand?.id === brandId) {
                    this.currentBrand = updatedBrand;
                }

                return updatedBrand;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async disableAutomation(brandId) {
            this.saving = true;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/automation/disable`);
                const updatedBrand = response.data.brand;

                const index = this.brands.findIndex(b => b.id === brandId);
                if (index !== -1) {
                    this.brands[index] = updatedBrand;
                }

                if (this.currentBrand?.id === brandId) {
                    this.currentBrand = updatedBrand;
                }

                return updatedBrand;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async triggerAutomation(brandId) {
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/automation/process`);
                return response.data;
            } catch (error) {
                throw error;
            }
        },

        async extendQueue(brandId, days = 7) {
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/automation/extend`, { days });
                return response.data;
            } catch (error) {
                throw error;
            }
        },

        async updateAutomationSettings(brandId, settings) {
            this.saving = true;
            try {
                const response = await axios.put(`/api/v1/brands/${brandId}/automation/settings`, settings);
                const updatedBrand = response.data.brand;

                const index = this.brands.findIndex(b => b.id === brandId);
                if (index !== -1) {
                    this.brands[index] = updatedBrand;
                }

                if (this.currentBrand?.id === brandId) {
                    this.currentBrand = updatedBrand;
                }

                return updatedBrand;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },
    },
});

function getDefaultOnboardingData() {
    return {
        // Step 1: Basics
        name: '',
        industry: '',
        description: '',

        // Step 2: Target audience
        ageRange: '25-40',
        gender: 'all',
        interests: [],
        painPoints: [],

        // Step 3: Voice
        tone: 'professional',
        personality: [],
        language: 'pl',
        emojiUsage: 'sometimes',

        // Step 4: Content pillars
        contentPillars: [],

        // Step 5: Platforms & frequency
        platforms: {
            facebook: { enabled: false },
            instagram: { enabled: false },
            youtube: { enabled: false },
        },
        frequency: {
            facebook: 3,
            instagram: 5,
            youtube: 1,
        },
        bestTimes: {
            facebook: ['09:00', '18:00'],
            instagram: ['12:00', '20:00'],
            youtube: ['17:00'],
        },
        autoSchedule: true,
    };
}
