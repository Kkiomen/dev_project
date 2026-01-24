<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';

const { t, locale } = useI18n();
const authStore = useAuthStore();
const toast = useToast();

const loading = ref(true);
const saving = ref(false);
const activeTab = ref('profile');

// Profile data
const profile = ref({
    name: '',
    email: '',
});

// Password change
const passwordData = ref({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const changingPassword = ref(false);

// Settings data
const settings = ref({
    language: 'pl',
    timezone: 'Europe/Warsaw',
    weekStartsOn: 1,
    timeFormat: '24h',
    ai: {
        creativity: 'medium',
        defaultLength: 'medium',
        customInstructions: '',
        autoSuggest: true,
    },
    notifications: {
        email: true,
        postPublished: true,
        approvalRequired: true,
        weeklyReport: false,
    },
});

const tabs = [
    { key: 'profile', icon: 'user' },
    { key: 'ai', icon: 'sparkles' },
    { key: 'notifications', icon: 'bell' },
];

const timezones = [
    'Europe/Warsaw',
    'Europe/London',
    'Europe/Paris',
    'Europe/Berlin',
    'Europe/Rome',
    'Europe/Madrid',
    'Europe/Amsterdam',
    'Europe/Brussels',
    'Europe/Vienna',
    'Europe/Prague',
    'Europe/Budapest',
    'Europe/Bucharest',
    'Europe/Sofia',
    'Europe/Athens',
    'Europe/Helsinki',
    'Europe/Stockholm',
    'Europe/Oslo',
    'Europe/Copenhagen',
    'Europe/Dublin',
    'Europe/Lisbon',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'America/Toronto',
    'America/Vancouver',
    'America/Sao_Paulo',
    'America/Mexico_City',
    'Asia/Tokyo',
    'Asia/Seoul',
    'Asia/Shanghai',
    'Asia/Hong_Kong',
    'Asia/Singapore',
    'Asia/Dubai',
    'Asia/Kolkata',
    'Australia/Sydney',
    'Australia/Melbourne',
    'Pacific/Auckland',
];

const languages = [
    { code: 'pl', name: 'Polski' },
    { code: 'en', name: 'English' },
];

const creativityOptions = ['low', 'medium', 'high'];
const lengthOptions = ['short', 'medium', 'long'];

const tabIcons = {
    user: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>`,
    sparkles: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>`,
    bell: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>`,
};

onMounted(async () => {
    await loadSettings();
});

const loadSettings = async () => {
    loading.value = true;
    try {
        await authStore.fetchUser();
        if (authStore.user) {
            profile.value = {
                name: authStore.user.name || '',
                email: authStore.user.email || '',
            };

            const userSettings = authStore.user.settings || {};
            settings.value = {
                language: userSettings.language || 'pl',
                timezone: userSettings.timezone || 'Europe/Warsaw',
                weekStartsOn: userSettings.weekStartsOn ?? 1,
                timeFormat: userSettings.timeFormat || '24h',
                ai: {
                    creativity: userSettings.ai?.creativity || 'medium',
                    defaultLength: userSettings.ai?.defaultLength || 'medium',
                    customInstructions: userSettings.ai?.customInstructions || '',
                    autoSuggest: userSettings.ai?.autoSuggest ?? true,
                },
                notifications: {
                    email: userSettings.notifications?.email ?? true,
                    postPublished: userSettings.notifications?.postPublished ?? true,
                    approvalRequired: userSettings.notifications?.approvalRequired ?? true,
                    weeklyReport: userSettings.notifications?.weeklyReport ?? false,
                },
            };
        }
    } catch (error) {
        console.error('Failed to load settings:', error);
        toast.error(t('common.error'));
    } finally {
        loading.value = false;
    }
};

const saveProfile = async () => {
    saving.value = true;
    try {
        await window.axios.put('/api/user/profile', profile.value);
        await authStore.fetchUser();
        toast.success(t('settings.profileSaved'));
    } catch (error) {
        console.error('Failed to save profile:', error);
        toast.error(error.response?.data?.message || t('common.error'));
    } finally {
        saving.value = false;
    }
};

const saveSettings = async () => {
    saving.value = true;
    try {
        await window.axios.put('/api/user/settings', { settings: settings.value });

        // Update locale if language changed
        if (settings.value.language !== locale.value) {
            locale.value = settings.value.language;
            localStorage.setItem('locale', settings.value.language);
        }

        await authStore.fetchUser();
        toast.success(t('settings.settingsSaved'));
    } catch (error) {
        console.error('Failed to save settings:', error);
        toast.error(error.response?.data?.message || t('common.error'));
    } finally {
        saving.value = false;
    }
};

const changePassword = async () => {
    changingPassword.value = true;
    try {
        await window.axios.put('/api/user/password', passwordData.value);
        toast.success(t('settings.passwordChanged'));
        passwordData.value = {
            current_password: '',
            password: '',
            password_confirmation: '',
        };
    } catch (error) {
        console.error('Failed to change password:', error);
        toast.error(error.response?.data?.message || t('common.error'));
    } finally {
        changingPassword.value = false;
    }
};

const handleSave = () => {
    if (activeTab.value === 'profile') {
        saveProfile();
        saveSettings();
    } else {
        saveSettings();
    }
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 sticky top-0 z-10">
            <div class="max-w-5xl mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-xl font-semibold text-gray-900">
                        {{ t('settings.title') }}
                    </h1>
                    <Button @click="handleSave" :loading="saving" :disabled="saving || loading">
                        {{ t('common.save') }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-5xl mx-auto p-6">
            <div v-if="loading" class="flex items-center justify-center py-20">
                <LoadingSpinner size="lg" />
            </div>

            <div v-else class="flex gap-6">
                <!-- Sidebar -->
                <nav class="w-56 flex-shrink-0">
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden sticky top-24">
                        <ul class="divide-y divide-gray-100">
                            <li v-for="tab in tabs" :key="tab.key">
                                <button
                                    @click="activeTab = tab.key"
                                    class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium transition-colors text-left"
                                    :class="[
                                        activeTab === tab.key
                                            ? 'bg-blue-50 text-blue-700 border-l-2 border-blue-600'
                                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-2 border-transparent'
                                    ]"
                                >
                                    <span
                                        :class="activeTab === tab.key ? 'text-blue-600' : 'text-gray-400'"
                                        v-html="tabIcons[tab.icon]"
                                    />
                                    {{ t(`settings.tabs.${tab.key}`) }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </nav>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <!-- Profile Tab -->
                    <div v-if="activeTab === 'profile'" class="space-y-6">
                        <!-- Profile Info -->
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">
                                {{ t('settings.profile.title') }}
                            </h2>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ t('settings.profile.name') }}
                                    </label>
                                    <input
                                        v-model="profile.name"
                                        type="text"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ t('settings.profile.email') }}
                                    </label>
                                    <input
                                        v-model="profile.email"
                                        type="email"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Interface Settings -->
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">
                                {{ t('settings.profile.interface') }}
                            </h2>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ t('settings.profile.language') }}
                                    </label>
                                    <select
                                        v-model="settings.language"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    >
                                        <option v-for="lang in languages" :key="lang.code" :value="lang.code">
                                            {{ lang.name }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ t('settings.profile.timezone') }}
                                    </label>
                                    <select
                                        v-model="settings.timezone"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    >
                                        <option v-for="tz in timezones" :key="tz" :value="tz">
                                            {{ tz }}
                                        </option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ t('settings.profile.weekStartsOn') }}
                                        </label>
                                        <select
                                            v-model="settings.weekStartsOn"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        >
                                            <option :value="1">{{ t('settings.profile.monday') }}</option>
                                            <option :value="0">{{ t('settings.profile.sunday') }}</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ t('settings.profile.timeFormat') }}
                                        </label>
                                        <select
                                            v-model="settings.timeFormat"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        >
                                            <option value="24h">24h (14:00)</option>
                                            <option value="12h">12h (2:00 PM)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">
                                {{ t('settings.profile.changePassword') }}
                            </h2>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ t('settings.profile.currentPassword') }}
                                    </label>
                                    <input
                                        v-model="passwordData.current_password"
                                        type="password"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ t('settings.profile.newPassword') }}
                                    </label>
                                    <input
                                        v-model="passwordData.password"
                                        type="password"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ t('settings.profile.confirmPassword') }}
                                    </label>
                                    <input
                                        v-model="passwordData.password_confirmation"
                                        type="password"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    />
                                </div>

                                <Button
                                    variant="secondary"
                                    @click="changePassword"
                                    :loading="changingPassword"
                                    :disabled="!passwordData.current_password || !passwordData.password"
                                >
                                    {{ t('settings.profile.updatePassword') }}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- AI Tab -->
                    <div v-if="activeTab === 'ai'" class="space-y-6">
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-2">
                                {{ t('settings.ai.title') }}
                            </h2>
                            <p class="text-sm text-gray-500 mb-6">
                                {{ t('settings.ai.description') }}
                            </p>

                            <div class="space-y-6">
                                <!-- Creativity -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        {{ t('settings.ai.creativity') }}
                                    </label>
                                    <div class="flex gap-2">
                                        <button
                                            v-for="option in creativityOptions"
                                            :key="option"
                                            @click="settings.ai.creativity = option"
                                            class="flex-1 px-4 py-2 text-sm font-medium rounded-lg border transition-colors"
                                            :class="{
                                                'bg-blue-600 text-white border-blue-600': settings.ai.creativity === option,
                                                'bg-white text-gray-700 border-gray-300 hover:bg-gray-50': settings.ai.creativity !== option,
                                            }"
                                        >
                                            {{ t(`settings.ai.creativityOptions.${option}`) }}
                                        </button>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-500">
                                        {{ t(`settings.ai.creativityHint.${settings.ai.creativity}`) }}
                                    </p>
                                </div>

                                <!-- Default Length -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        {{ t('settings.ai.defaultLength') }}
                                    </label>
                                    <div class="flex gap-2">
                                        <button
                                            v-for="option in lengthOptions"
                                            :key="option"
                                            @click="settings.ai.defaultLength = option"
                                            class="flex-1 px-4 py-2 text-sm font-medium rounded-lg border transition-colors"
                                            :class="{
                                                'bg-blue-600 text-white border-blue-600': settings.ai.defaultLength === option,
                                                'bg-white text-gray-700 border-gray-300 hover:bg-gray-50': settings.ai.defaultLength !== option,
                                            }"
                                        >
                                            {{ t(`settings.ai.lengthOptions.${option}`) }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Custom Instructions -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ t('settings.ai.customInstructions') }}
                                    </label>
                                    <p class="text-sm text-gray-500 mb-2">
                                        {{ t('settings.ai.customInstructionsHint') }}
                                    </p>
                                    <textarea
                                        v-model="settings.ai.customInstructions"
                                        rows="4"
                                        maxlength="1000"
                                        :placeholder="t('settings.ai.customInstructionsPlaceholder')"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    ></textarea>
                                    <p class="mt-1 text-xs text-gray-400 text-right">
                                        {{ settings.ai.customInstructions?.length || 0 }} / 1000
                                    </p>
                                </div>

                                <!-- Auto Suggest -->
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900">
                                            {{ t('settings.ai.autoSuggest') }}
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            {{ t('settings.ai.autoSuggestHint') }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="settings.ai.autoSuggest = !settings.ai.autoSuggest"
                                        :class="[
                                            settings.ai.autoSuggest ? 'bg-blue-600' : 'bg-gray-200',
                                            'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2'
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                settings.ai.autoSuggest ? 'translate-x-5' : 'translate-x-0',
                                                'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
                                            ]"
                                        />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications Tab -->
                    <div v-if="activeTab === 'notifications'" class="space-y-6">
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-2">
                                {{ t('settings.notifications.title') }}
                            </h2>
                            <p class="text-sm text-gray-500 mb-6">
                                {{ t('settings.notifications.description') }}
                            </p>

                            <div class="space-y-4">
                                <!-- Email notifications master toggle -->
                                <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900">
                                            {{ t('settings.notifications.emailNotifications') }}
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            {{ t('settings.notifications.emailNotificationsHint') }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="settings.notifications.email = !settings.notifications.email"
                                        :class="[
                                            settings.notifications.email ? 'bg-blue-600' : 'bg-gray-200',
                                            'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2'
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                settings.notifications.email ? 'translate-x-5' : 'translate-x-0',
                                                'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
                                            ]"
                                        />
                                    </button>
                                </div>

                                <!-- Individual notification types -->
                                <div
                                    v-for="type in ['postPublished', 'approvalRequired', 'weeklyReport']"
                                    :key="type"
                                    class="flex items-center justify-between py-3"
                                    :class="{ 'opacity-50 pointer-events-none': !settings.notifications.email }"
                                >
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900">
                                            {{ t(`settings.notifications.${type}`) }}
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            {{ t(`settings.notifications.${type}Hint`) }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="settings.notifications[type] = !settings.notifications[type]"
                                        :disabled="!settings.notifications.email"
                                        :class="[
                                            settings.notifications[type] ? 'bg-blue-600' : 'bg-gray-200',
                                            'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2'
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                settings.notifications[type] ? 'translate-x-5' : 'translate-x-0',
                                                'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
                                            ]"
                                        />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
