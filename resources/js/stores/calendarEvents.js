import { defineStore } from 'pinia';
import axios from 'axios';

export const useCalendarEventsStore = defineStore('calendarEvents', {
    state: () => ({
        events: [],
        calendarEvents: {},
        currentEvent: null,
        loading: false,
        saving: false,
        error: null,
    }),

    getters: {
        getEventById: (state) => (id) => state.events.find(e => e.id === id),
        getEventsByDate: (state) => (date) => state.calendarEvents[date] || [],
    },

    actions: {
        async fetchEvents(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/events', { params });
                this.events = response.data.data;
                return this.events;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch events';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchCalendarEvents(start, end) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/events/calendar', {
                    params: { start, end },
                });

                // Group events by date
                const grouped = {};
                response.data.data.forEach(event => {
                    if (event.scheduled_date) {
                        if (!grouped[event.scheduled_date]) {
                            grouped[event.scheduled_date] = [];
                        }
                        grouped[event.scheduled_date].push(event);
                    }
                });

                this.calendarEvents = grouped;
                return grouped;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch calendar events';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchEvent(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/v1/events/${id}`);
                this.currentEvent = response.data.data;
                return this.currentEvent;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch event';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createEvent(data) {
            this.saving = true;
            try {
                const response = await axios.post('/api/v1/events', data);
                const newEvent = response.data.data;
                this.events.push(newEvent);

                // Add to calendar events
                this.addCalendarEvent(newEvent);

                return newEvent;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async updateEvent(id, data) {
            this.saving = true;
            try {
                const response = await axios.put(`/api/v1/events/${id}`, data);
                const updatedEvent = response.data.data;

                // Update in events list
                const index = this.events.findIndex(e => e.id === id);
                if (index !== -1) {
                    this.events[index] = updatedEvent;
                }

                if (this.currentEvent?.id === id) {
                    this.currentEvent = updatedEvent;
                }

                // Update in calendar events
                this.removeCalendarEvent(id);
                this.addCalendarEvent(updatedEvent);

                return updatedEvent;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async deleteEvent(id) {
            try {
                await axios.delete(`/api/v1/events/${id}`);
                this.events = this.events.filter(e => e.id !== id);

                // Remove from calendar events
                this.removeCalendarEvent(id);

                if (this.currentEvent?.id === id) {
                    this.currentEvent = null;
                }
            } catch (error) {
                throw error;
            }
        },

        async rescheduleEvent(id, startsAt, endsAt = null) {
            this.saving = true;
            try {
                const response = await axios.post(`/api/v1/events/${id}/reschedule`, {
                    starts_at: startsAt,
                    ends_at: endsAt,
                });
                const updatedEvent = response.data.data;

                // Update in events list
                const index = this.events.findIndex(e => e.id === id);
                if (index !== -1) {
                    this.events[index] = updatedEvent;
                }

                // Update in calendar events
                this.removeCalendarEvent(id);
                this.addCalendarEvent(updatedEvent);

                return updatedEvent;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        addCalendarEvent(event) {
            const dateKey = event.scheduled_date;
            if (!dateKey) return;

            if (!this.calendarEvents[dateKey]) {
                this.calendarEvents[dateKey] = [];
            }

            // Check if event already exists to avoid duplicates
            const exists = this.calendarEvents[dateKey].some(e => e.id === event.id);
            if (!exists) {
                this.calendarEvents[dateKey].push(event);
            }
        },

        removeCalendarEvent(eventId) {
            Object.keys(this.calendarEvents).forEach(date => {
                this.calendarEvents[date] = this.calendarEvents[date].filter(e => e.id !== eventId);
            });
        },

        clearCurrentEvent() {
            this.currentEvent = null;
        },

        reset() {
            this.events = [];
            this.calendarEvents = {};
            this.currentEvent = null;
            this.loading = false;
            this.saving = false;
            this.error = null;
        },
    },
});
