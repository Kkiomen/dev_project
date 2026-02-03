import { defineStore } from 'pinia';
import axios from 'axios';
import { useSettingsStore } from './settings';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loading: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user,
        isAdmin: (state) => state.user?.is_admin === true,
        isOnboarded: (state) => state.user?.onboarding_completed === true,
    },

    actions: {
        async fetchUser() {
            try {
                this.loading = true;
                const response = await axios.get('/api/user');
                this.user = response.data;

                // Initialize settings store with user settings
                const settingsStore = useSettingsStore();
                settingsStore.initFromUser(this.user);
            } catch (error) {
                this.user = null;
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            await axios.post('/logout');
            this.user = null;
            window.location.href = '/login';
        },
    },
});
