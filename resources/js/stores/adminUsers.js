import { defineStore } from 'pinia';
import axios from 'axios';

export const useAdminUsersStore = defineStore('adminUsers', {
    state: () => ({
        users: [],
        selectedUser: null,
        loading: false,
        error: null,
        pagination: {
            currentPage: 1,
            lastPage: 1,
            total: 0,
            perPage: 15,
        },
        userNotifications: [],
        notificationsLoading: false,
    }),

    actions: {
        async fetchUsers(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/admin/users', { params });
                this.users = response.data.data;
                if (response.data.meta) {
                    this.pagination = {
                        currentPage: response.data.meta.current_page,
                        lastPage: response.data.meta.last_page,
                        total: response.data.meta.total,
                        perPage: response.data.meta.per_page,
                    };
                }
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch users';
            } finally {
                this.loading = false;
            }
        },

        async fetchUser(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/admin/users/${id}`);
                this.selectedUser = response.data.data;
                return this.selectedUser;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch user';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async updateUser(id, data) {
            const response = await axios.put(`/api/admin/users/${id}`, data);
            const updated = response.data.data;
            const index = this.users.findIndex(u => u.id === id);
            if (index !== -1) {
                this.users[index] = { ...this.users[index], ...updated };
            }
            if (this.selectedUser?.id === id) {
                this.selectedUser = { ...this.selectedUser, ...updated };
            }
            return updated;
        },

        async updatePassword(id, data) {
            await axios.put(`/api/admin/users/${id}/password`, data);
        },

        async deleteUser(id) {
            await axios.delete(`/api/admin/users/${id}`);
            this.users = this.users.filter(u => u.id !== id);
            if (this.selectedUser?.id === id) {
                this.selectedUser = null;
            }
        },

        async fetchUserNotifications(id) {
            this.notificationsLoading = true;
            try {
                const response = await axios.get(`/api/admin/users/${id}/notifications`);
                this.userNotifications = response.data.data;
                return this.userNotifications;
            } catch (error) {
                this.userNotifications = [];
                throw error;
            } finally {
                this.notificationsLoading = false;
            }
        },
    },
});
