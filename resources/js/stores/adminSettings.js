import { defineStore } from 'pinia';
import axios from 'axios';

export const useAdminSettingsStore = defineStore('adminSettings', {
    state: () => ({
        settings: {
            registration_enabled: true,
            login_enabled: true,
        },
        loading: false,
        saving: false,
    }),

    actions: {
        async fetchSettings() {
            this.loading = true;
            try {
                const response = await axios.get('/api/admin/settings');
                this.settings = response.data.data;
            } catch (error) {
                console.error('Failed to fetch settings:', error);
            } finally {
                this.loading = false;
            }
        },

        async updateSettings(data) {
            this.saving = true;
            try {
                const response = await axios.put('/api/admin/settings', data);
                this.settings = response.data.data;
                return response.data;
            } catch (error) {
                console.error('Failed to update settings:', error);
                throw error;
            } finally {
                this.saving = false;
            }
        },
    },
});
