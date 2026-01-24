<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCalendarStore } from '@/stores/calendar';
import { useSettingsStore } from '@/stores/settings';
import Button from '@/components/common/Button.vue';

const { t } = useI18n();
const calendarStore = useCalendarStore();
const settingsStore = useSettingsStore();

const showSettings = ref(false);

// Translated month name
const currentMonthName = computed(() => {
    return t(`calendar.months.${calendarStore.currentMonthKey}`);
});

// Current view title with translated month
const viewTitle = computed(() => {
    return `${currentMonthName.value} ${calendarStore.currentYear}`;
});

const setWeekStart = (day) => {
    settingsStore.setWeekStartsOn(day);
};

const setTimeFormat = (format) => {
    settingsStore.setTimeFormat(format);
};
</script>

<template>
    <div class="bg-white border-b border-gray-200 px-6 py-3">
        <div class="flex items-center justify-between">
            <!-- Navigation -->
            <div class="flex items-center space-x-4">
                <Button variant="secondary" size="sm" @click="calendarStore.goToToday">
                    {{ t('calendar.today') }}
                </Button>
                <div class="flex items-center space-x-2">
                    <button
                        @click="calendarStore.prevMonth"
                        class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <button
                        @click="calendarStore.nextMonth"
                        class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ viewTitle }}
                </h2>
            </div>

            <!-- Filters -->
            <div class="flex items-center space-x-4">
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

                <!-- View toggle -->
                <div class="flex items-center bg-gray-100 rounded-lg p-1">
                    <button
                        @click="calendarStore.setView('month')"
                        class="px-3 py-1 text-sm rounded-md transition-colors"
                        :class="calendarStore.view === 'month' ? 'bg-white shadow text-gray-900' : 'text-gray-600 hover:text-gray-900'"
                    >
                        {{ t('calendar.month') }}
                    </button>
                    <button
                        @click="calendarStore.setView('week')"
                        class="px-3 py-1 text-sm rounded-md transition-colors"
                        :class="calendarStore.view === 'week' ? 'bg-white shadow text-gray-900' : 'text-gray-600 hover:text-gray-900'"
                    >
                        {{ t('calendar.week') }}
                    </button>
                </div>

                <!-- Settings dropdown -->
                <div class="relative">
                    <button
                        @click="showSettings = !showSettings"
                        class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                        :title="t('settings.title')"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                    {{ t('settings.weekStartsOn') }}
                                </label>
                                <div class="flex space-x-2">
                                    <button
                                        @click="setWeekStart(1)"
                                        class="flex-1 px-3 py-2 text-sm rounded-lg border transition-colors"
                                        :class="settingsStore.settings.weekStartsOn === 1 ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                                    >
                                        {{ t('settings.monday') }}
                                    </button>
                                    <button
                                        @click="setWeekStart(0)"
                                        class="flex-1 px-3 py-2 text-sm rounded-lg border transition-colors"
                                        :class="settingsStore.settings.weekStartsOn === 0 ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                                    >
                                        {{ t('settings.sunday') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Time format -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ t('settings.timeFormat') }}
                                </label>
                                <div class="flex space-x-2">
                                    <button
                                        @click="setTimeFormat('24h')"
                                        class="flex-1 px-3 py-2 text-sm rounded-lg border transition-colors"
                                        :class="settingsStore.settings.timeFormat === '24h' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                                    >
                                        {{ t('settings.time24h') }}
                                    </button>
                                    <button
                                        @click="setTimeFormat('12h')"
                                        class="flex-1 px-3 py-2 text-sm rounded-lg border transition-colors"
                                        :class="settingsStore.settings.timeFormat === '12h' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                                    >
                                        {{ t('settings.time12h') }}
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
