import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loading: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user,
        isAdmin: (state) => state.user?.is_admin === true,
    },

    actions: {
        async fetchUser() {
            try {
                this.loading = true;
                const response = await axios.get('/api/user');
                this.user = response.data;
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
