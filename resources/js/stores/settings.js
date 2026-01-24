import { defineStore } from 'pinia';
import axios from 'axios';
import { setLocale } from '@/i18n';

export const useSettingsStore = defineStore('settings', {
    state: () => ({
        settings: {
            weekStartsOn: 1, // 0 = Sunday, 1 = Monday
            timeFormat: '24h', // '12h' or '24h'
            language: 'pl',
        },
        loading: false,
        saving: false,
    }),

    getters: {
        weekStartsOnMonday: (state) => state.settings.weekStartsOn === 1,
        weekStartsOnSunday: (state) => state.settings.weekStartsOn === 0,
        is24HourFormat: (state) => state.settings.timeFormat === '24h',
        is12HourFormat: (state) => state.settings.timeFormat === '12h',
    },

    actions: {
        async fetchSettings() {
            this.loading = true;
            try {
                const response = await axios.get('/api/user');
                if (response.data.settings) {
                    this.settings = {
                        ...this.settings,
                        ...response.data.settings,
                    };
                    // Apply language setting
                    if (response.data.settings.language) {
                        setLocale(response.data.settings.language);
                    }
                }
            } catch (error) {
                console.error('Failed to fetch settings:', error);
            } finally {
                this.loading = false;
            }
        },

        async updateSettings(newSettings) {
            this.saving = true;
            try {
                const response = await axios.put('/api/user/settings', {
                    settings: { ...this.settings, ...newSettings },
                });
                this.settings = {
                    ...this.settings,
                    ...newSettings,
                };
                return response.data;
            } catch (error) {
                console.error('Failed to update settings:', error);
                throw error;
            } finally {
                this.saving = false;
            }
        },

        setWeekStartsOn(day) {
            this.updateSettings({ weekStartsOn: day });
        },

        setTimeFormat(format) {
            this.updateSettings({ timeFormat: format });
        },

        setLanguage(language) {
            setLocale(language);
            this.updateSettings({ language });
        },

        initFromUser(user) {
            if (user?.settings) {
                this.settings = {
                    ...this.settings,
                    ...user.settings,
                };
                // Apply language setting
                if (user.settings.language) {
                    setLocale(user.settings.language);
                }
            }
        },

        formatTime(date) {
            if (typeof date === 'string') {
                date = new Date(date);
            }
            const hours = date.getHours();
            const minutes = date.getMinutes();

            if (this.settings.timeFormat === '12h') {
                const period = hours >= 12 ? 'PM' : 'AM';
                const hour12 = hours % 12 || 12;
                return `${hour12}:${String(minutes).padStart(2, '0')} ${period}`;
            }

            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
        },

        parseTimeInput(timeString) {
            // Parse time input and return { hours, minutes }
            const match24 = timeString.match(/^(\d{1,2}):(\d{2})$/);
            if (match24) {
                return { hours: parseInt(match24[1]), minutes: parseInt(match24[2]) };
            }

            const match12 = timeString.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
            if (match12) {
                let hours = parseInt(match12[1]);
                const minutes = parseInt(match12[2]);
                const period = match12[3].toUpperCase();

                if (period === 'PM' && hours !== 12) hours += 12;
                if (period === 'AM' && hours === 12) hours = 0;

                return { hours, minutes };
            }

            return null;
        },
    },
});
