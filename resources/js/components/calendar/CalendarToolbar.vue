<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCalendarStore } from '@/stores/calendar';
import { useSettingsStore } from '@/stores/settings';
import Button from '@/components/common/Button.vue';

const emit = defineEmits(['create-event']);

const { t } = useI18n();
const calendarStore = useCalendarStore();
const settingsStore = useSettingsStore();

const showSettings = ref(false);
const showFilters = ref(false);

// Translated month name
const currentMonthName = computed(() => {
    return t(`calendar.months.${calendarStore.currentMonthKey}`);
});

// Current view title with translated month
const viewTitle = computed(() => {
    return `${currentMonthName.value} ${calendarStore.currentYear}`;
});

// Short month name for mobile
const shortViewTitle = computed(() => {
    const shortMonth = currentMonthName.value.substring(0, 3);
    return `${shortMonth} ${calendarStore.currentYear}`;
});

const setWeekStart = (day) => {
    settingsStore.setWeekStartsOn(day);
};

const setTimeFormat = (format) => {
    settingsStore.setTimeFormat(format);
};

// Check if any filter is active
const hasActiveFilters = computed(() => {
    return calendarStore.filters.itemType || calendarStore.filters.status;
});
</script>

<template>
    <div class="bg-white border-b border-gray-200 px-4 sm:px-6 py-2 sm:py-3">
        <div class="flex items-center justify-between gap-2">
            <!-- Navigation -->
            <div class="flex items-center space-x-1 sm:space-x-4">
                <Button
                    variant="secondary"
                    size="sm"
                    @click="calendarStore.goToToday"
                    class="hidden sm:inline-flex"
                >
                    {{ t('calendar.today') }}
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    @click="calendarStore.goToToday"
                    class="sm:hidden !px-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </Button>
                <div class="flex items-center">
                    <button
                        @click="calendarStore.prevMonth"
                        class="p-1.5 sm:p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                    >
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <button
                        @click="calendarStore.nextMonth"
                        class="p-1.5 sm:p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                    >
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
                <h2 class="text-sm sm:text-lg font-semibold text-gray-900 whitespace-nowrap">
                    <span class="hidden sm:inline">{{ viewTitle }}</span>
                    <span class="sm:hidden">{{ shortViewTitle }}</span>
                </h2>
            </div>

            <!-- Right side controls -->
            <div class="flex items-center space-x-1 sm:space-x-4">
                <!-- Desktop Filters -->
                <div class="hidden lg:flex items-center space-x-2">
                    <!-- Item type filter -->
                    <select
                        :value="calendarStore.filters.itemType"
                        @change="calendarStore.setFilter('itemType', $event.target.value || null)"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">{{ t('calendar.allItems') }}</option>
                        <option value="posts">{{ t('calendar.postsOnly') }}</option>
                        <option value="events">{{ t('calendar.eventsOnly') }}</option>
                    </select>

                    <!-- Status filter (for posts) -->
                    <select
                        :value="calendarStore.filters.status"
                        @change="calendarStore.setFilter('status', $event.target.value || null)"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">{{ t('calendar.allStatuses') }}</option>
                        <option value="draft">{{ t('posts.status.draft') }}</option>
                        <option value="pending_approval">{{ t('posts.status.pending_approval') }}</option>
                        <option value="approved">{{ t('posts.status.approved') }}</option>
                        <option value="scheduled">{{ t('posts.status.scheduled') }}</option>
                        <option value="published">{{ t('posts.status.published') }}</option>
                    </select>
                </div>

                <!-- Mobile/Tablet Filter Button -->
                <div class="lg:hidden relative">
                    <button
                        @click="showFilters = !showFilters"
                        class="p-1.5 sm:p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100 relative"
                        :class="{ 'text-blue-600': hasActiveFilters }"
                    >
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        <span
                            v-if="hasActiveFilters"
                            class="absolute -top-1 -right-1 w-2 h-2 bg-blue-600 rounded-full"
                        />
                    </button>

                    <!-- Mobile Filters Dropdown -->
                    <div
                        v-if="showFilters"
                        class="absolute right-0 top-full mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50 p-3 space-y-3"
                    >
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                {{ t('calendar.allItems') }}
                            </label>
                            <select
                                :value="calendarStore.filters.itemType"
                                @change="calendarStore.setFilter('itemType', $event.target.value || null)"
                                class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">{{ t('calendar.allItems') }}</option>
                                <option value="posts">{{ t('calendar.postsOnly') }}</option>
                                <option value="events">{{ t('calendar.eventsOnly') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                {{ t('calendar.allStatuses') }}
                            </label>
                            <select
                                :value="calendarStore.filters.status"
                                @change="calendarStore.setFilter('status', $event.target.value || null)"
                                class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">{{ t('calendar.allStatuses') }}</option>
                                <option value="draft">{{ t('posts.status.draft') }}</option>
                                <option value="pending_approval">{{ t('posts.status.pending_approval') }}</option>
                                <option value="approved">{{ t('posts.status.approved') }}</option>
                                <option value="scheduled">{{ t('posts.status.scheduled') }}</option>
                                <option value="published">{{ t('posts.status.published') }}</option>
                            </select>
                        </div>
                        <button
                            v-if="hasActiveFilters"
                            @click="calendarStore.clearFilters(); showFilters = false"
                            class="w-full text-xs text-blue-600 hover:text-blue-800"
                        >
                            {{ t('filter.clearAll') }}
                        </button>
                    </div>

                    <!-- Backdrop -->
                    <div
                        v-if="showFilters"
                        class="fixed inset-0 z-40"
                        @click="showFilters = false"
                    />
                </div>

                <!-- View toggle -->
                <div class="flex items-center bg-gray-100 rounded-lg p-0.5 sm:p-1">
                    <button
                        @click="calendarStore.setView('month')"
                        class="px-2 sm:px-3 py-1 text-xs sm:text-sm rounded-md transition-colors"
                        :class="calendarStore.view === 'month' ? 'bg-white shadow text-gray-900' : 'text-gray-600 hover:text-gray-900'"
                    >
                        <span class="hidden sm:inline">{{ t('calendar.month') }}</span>
                        <span class="sm:hidden">M</span>
                    </button>
                    <button
                        @click="calendarStore.setView('week')"
                        class="px-2 sm:px-3 py-1 text-xs sm:text-sm rounded-md transition-colors"
                        :class="calendarStore.view === 'week' ? 'bg-white shadow text-gray-900' : 'text-gray-600 hover:text-gray-900'"
                    >
                        <span class="hidden sm:inline">{{ t('calendar.week') }}</span>
                        <span class="sm:hidden">W</span>
                    </button>
                </div>

                <!-- Settings dropdown -->
                <div class="relative">
                    <button
                        @click="showSettings = !showSettings"
                        class="p-1.5 sm:p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                        :title="t('settings.title')"
                    >
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>

                    <!-- Settings panel -->
                    <div
                        v-if="showSettings"
                        class="absolute right-0 top-full mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50"
                    >
                        <div class="p-4 space-y-4">
                            <h3 class="font-medium text-gray-900">{{ t('settings.title') }}</h3>

                            <!-- Week starts on -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ t('settings.profile.weekStartsOn') }}
                                </label>
                                <div class="flex space-x-2">
                                    <button
                                        @click="setWeekStart(1)"
                                        class="flex-1 px-3 py-2 text-sm rounded-lg border transition-colors"
                                        :class="settingsStore.settings.weekStartsOn === 1 ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                                    >
                                        {{ t('settings.profile.monday') }}
                                    </button>
                                    <button
                                        @click="setWeekStart(0)"
                                        class="flex-1 px-3 py-2 text-sm rounded-lg border transition-colors"
                                        :class="settingsStore.settings.weekStartsOn === 0 ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                                    >
                                        {{ t('settings.profile.sunday') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Time format -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ t('settings.profile.timeFormat') }}
                                </label>
                                <div class="flex space-x-2">
                                    <button
                                        @click="setTimeFormat('24h')"
                                        class="flex-1 px-3 py-2 text-sm rounded-lg border transition-colors"
                                        :class="settingsStore.settings.timeFormat === '24h' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                                    >
                                        {{ t('settings.profile.time24h') }}
                                    </button>
                                    <button
                                        @click="setTimeFormat('12h')"
                                        class="flex-1 px-3 py-2 text-sm rounded-lg border transition-colors"
                                        :class="settingsStore.settings.timeFormat === '12h' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                                    >
                                        {{ t('settings.profile.time12h') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Backdrop to close settings -->
                    <div
                        v-if="showSettings"
                        class="fixed inset-0 z-40"
                        @click="showSettings = false"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
