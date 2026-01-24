import { defineStore } from 'pinia';
import { useSettingsStore } from './settings';

const MONTH_KEYS = [
    'january', 'february', 'march', 'april', 'may', 'june',
    'july', 'august', 'september', 'october', 'november', 'december'
];

export const useCalendarStore = defineStore('calendar', {
    state: () => ({
        currentDate: new Date(),
        view: 'month',
        selectedDate: null,
        draggedPost: null,
        filters: {
            status: null,
            platforms: [],
        },
    }),

    getters: {
        currentYear: (state) => state.currentDate.getFullYear(),
        currentMonth: (state) => state.currentDate.getMonth(),
        currentMonthKey: (state) => MONTH_KEYS[state.currentDate.getMonth()],

        weekStartsOn() {
            const settingsStore = useSettingsStore();
            return settingsStore.settings.weekStartsOn;
        },

        // Returns array of day keys in order based on week start setting
        orderedDayKeys() {
            const settingsStore = useSettingsStore();
            if (settingsStore.settings.weekStartsOn === 1) {
                // Monday first
                return ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
            }
            // Sunday first
            return ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
        },

        monthStart: (state) => {
            if (state.view === 'week') {
                const weekDays = getWeekDays(state.currentDate);
                return weekDays[0].date;
            }
            const date = new Date(state.currentDate.getFullYear(), state.currentDate.getMonth(), 1);
            return date.toISOString().split('T')[0];
        },

        monthEnd: (state) => {
            if (state.view === 'week') {
                const weekDays = getWeekDays(state.currentDate);
                return weekDays[6].date;
            }
            const date = new Date(state.currentDate.getFullYear(), state.currentDate.getMonth() + 1, 0);
            return date.toISOString().split('T')[0];
        },

        calendarDays() {
            const settingsStore = useSettingsStore();
            const weekStartsOn = settingsStore.settings.weekStartsOn;

            if (this.view === 'week') {
                return getWeekDays(this.currentDate, weekStartsOn);
            }

            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth();

            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);

            const days = [];

            // Get day of week for first day (0 = Sunday, 1 = Monday, etc.)
            let firstDayOfWeek = firstDay.getDay();

            // Adjust for week start setting
            if (weekStartsOn === 1) {
                // Week starts on Monday
                firstDayOfWeek = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1;
            }

            // Previous month days to fill the first week
            if (firstDayOfWeek > 0) {
                const prevMonthLastDay = new Date(year, month, 0).getDate();
                for (let i = firstDayOfWeek - 1; i >= 0; i--) {
                    const day = prevMonthLastDay - i;
                    const date = new Date(year, month - 1, day);
                    days.push({
                        date: formatDate(date),
                        day,
                        isCurrentMonth: false,
                        isToday: false,
                    });
                }
            }

            // Current month days
            const todayStr = formatDate(new Date());
            for (let day = 1; day <= lastDay.getDate(); day++) {
                const date = new Date(year, month, day);
                const dateStr = formatDate(date);
                days.push({
                    date: dateStr,
                    day,
                    isCurrentMonth: true,
                    isToday: dateStr === todayStr,
                });
            }

            // Next month days to fill remaining cells (always show 6 weeks = 42 days)
            const remainingDays = 42 - days.length;
            for (let day = 1; day <= remainingDays; day++) {
                const date = new Date(year, month + 1, day);
                days.push({
                    date: formatDate(date),
                    day,
                    isCurrentMonth: false,
                    isToday: false,
                });
            }

            return days;
        },
    },

    actions: {
        nextMonth() {
            if (this.view === 'week') {
                const nextWeek = new Date(this.currentDate);
                nextWeek.setDate(nextWeek.getDate() + 7);
                this.currentDate = nextWeek;
            } else {
                this.currentDate = new Date(
                    this.currentDate.getFullYear(),
                    this.currentDate.getMonth() + 1,
                    1
                );
            }
        },

        prevMonth() {
            if (this.view === 'week') {
                const prevWeek = new Date(this.currentDate);
                prevWeek.setDate(prevWeek.getDate() - 7);
                this.currentDate = prevWeek;
            } else {
                this.currentDate = new Date(
                    this.currentDate.getFullYear(),
                    this.currentDate.getMonth() - 1,
                    1
                );
            }
        },

        goToToday() {
            this.currentDate = new Date();
        },

        goToMonth(year, month) {
            this.currentDate = new Date(year, month, 1);
        },

        selectDate(date) {
            this.selectedDate = date;
        },

        clearSelection() {
            this.selectedDate = null;
        },

        startDragging(post) {
            this.draggedPost = post;
        },

        stopDragging() {
            this.draggedPost = null;
        },

        setView(view) {
            this.view = view;
        },

        setFilter(key, value) {
            this.filters[key] = value;
        },

        clearFilters() {
            this.filters = {
                status: null,
                platforms: [],
            };
        },

        reset() {
            this.currentDate = new Date();
            this.view = 'month';
            this.selectedDate = null;
            this.draggedPost = null;
            this.clearFilters();
        },
    },
});

// Helper function to format date as YYYY-MM-DD
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Helper function to get week days
function getWeekDays(date, weekStartsOn = 1) {
    const currentDate = new Date(date);
    const dayOfWeek = currentDate.getDay();

    // Calculate the start of the week based on weekStartsOn setting
    const weekStart = new Date(currentDate);
    let diff;

    if (weekStartsOn === 1) {
        // Week starts on Monday
        diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
    } else {
        // Week starts on Sunday
        diff = -dayOfWeek;
    }

    weekStart.setDate(currentDate.getDate() + diff);

    const days = [];
    const todayStr = formatDate(new Date());
    const currentMonth = currentDate.getMonth();

    for (let i = 0; i < 7; i++) {
        const weekDate = new Date(weekStart);
        weekDate.setDate(weekStart.getDate() + i);
        const dateStr = formatDate(weekDate);

        days.push({
            date: dateStr,
            day: weekDate.getDate(),
            isCurrentMonth: weekDate.getMonth() === currentMonth,
            isToday: dateStr === todayStr,
        });
    }

    return days;
}
