<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useApiTokensStore } from '@/stores/apiTokens';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import ApiTokenList from '@/components/settings/ApiTokenList.vue';
import ApiTokenForm from '@/components/settings/ApiTokenForm.vue';
import AiKeysPanel from '@/components/brand/AiKeysPanel.vue';
import { useBrandsStore } from '@/stores/brands';

const { t, locale } = useI18n();
const route = useRoute();
const authStore = useAuthStore();
const apiTokensStore = useApiTokensStore();
const brandsStore = useBrandsStore();
const toast = useToast();

const loading = ref(true);
const saving = ref(false);
const activeTab = ref('profile');
const showTokenForm = ref(false);

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
    { key: 'aiKeys', icon: 'sparkles-key' },
    { key: 'tokens', icon: 'key' },
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
    'sparkles-key': `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>`,
    key: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>`,
};

onMounted(async () => {
    if (route.query.tab) {
        activeTab.value = route.query.tab;
    }
    await loadSettings();
    if (activeTab.value === 'tokens') {
        await apiTokensStore.fetchTokens();
    }
});

watch(activeTab, async (newTab) => {
    if (newTab === 'tokens') {
        await apiTokensStore.fetchTokens();
    }
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
    } else if (activeTab.value !== 'tokens') {
        saveSettings();
    }
};

// Token management functions
const newTokenValue = ref(null);

const handleCreateToken = async (data) => {
    try {
        const result = await apiTokensStore.createToken(data);
        newTokenValue.value = result.plain_text_token;
        showTokenForm.value = false;
        toast.success(t('settings.tokens.tokenCreated'));
    } catch (error) {
        console.error('Failed to create token:', error);
        toast.error(error.response?.data?.message || t('common.error'));
    }
};

const handleRevokeToken = async (token) => {
    if (!confirm(t('settings.tokens.revokeConfirm'))) return;
    try {
        await apiTokensStore.revokeToken(token.id);
        toast.success(t('settings.tokens.tokenRevoked'));
    } catch (error) {
        console.error('Failed to revoke token:', error);
        toast.error(error.response?.data?.message || t('common.error'));
    }
};

const handleCopyToken = async () => {
    if (!newTokenValue.value) return;
    try {
        await navigator.clipboard.writeText(newTokenValue.value);
        toast.success(t('settings.tokens.tokenCopied'));
    } catch (error) {
        console.error('Failed to copy token:', error);
        toast.error(t('common.error'));
    }
};

const closeNewTokenModal = () => {
    newTokenValue.value = null;
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 sticky top-0 z-10">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 sm:py-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-lg sm:text-xl font-semibold text-gray-900">
                        {{ t('settings.title') }}
                    </h1>
                    <Button
                        v-if="activeTab !== 'tokens' && activeTab !== 'aiKeys'"
                        @click="handleSave"
                        :loading="saving"
                        :disabled="saving || loading"
                        size="sm"
                        class="sm:size-md"
                    >
                        {{ t('common.save') }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-5xl mx-auto p-4 sm:p-6">
            <div v-if="loading" class="flex items-center justify-center py-20">
                <LoadingSpinner size="lg" />
            </div>

            <div v-else class="flex flex-col lg:flex-row gap-4 sm:gap-6">
                <!-- Mobile Tabs (horizontal scroll) -->
                <nav class="lg:hidden">
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-1 flex overflow-x-auto">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            @click="activeTab = tab.key"
                            class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-colors whitespace-nowrap flex-shrink-0"
                            :class="[
                                activeTab === tab.key
                                    ? 'bg-blue-100 text-blue-700'
                                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                            ]"
                        >
                            <span
                                :class="activeTab === tab.key ? 'text-blue-600' : 'text-gray-400'"
                                v-html="tabIcons[tab.icon]"
                            />
                            <span class="hidden sm:inline">{{ t(`settings.tabs.${tab.key}`) }}</span>
                        </button>
                    </div>
                </nav>

                <!-- Desktop Sidebar -->
                <nav class="hidden lg:block w-56 flex-shrink-0">
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
                    <div v-if="activeTab === 'profile'" class="space-y-4 sm:space-y-6">
                        <!-- Profile Info -->
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                            <h2 class="text-base sm:text-lg font-medium text-gray-900 mb-4">
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
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ t('settings.profile.email') }}
                                    </label>
                                    <input
                                        v-model="profile.email"
                                        type="email"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Interface Settings -->
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                            <h2 class="text-base sm:text-lg font-medium text-gray-900 mb-4">
                                {{ t('settings.profile.interface') }}
                            </h2>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ t('settings.profile.language') }}
                                    </label>
                                    <select
                                        v-model="settings.language"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
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
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    >
                                        <option v-for="tz in timezones" :key="tz" :value="tz">
                                            {{ tz }}
                                        </option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ t('settings.profile.weekStartsOn') }}
                                        </label>
                                        <select
                                            v-model="settings.weekStartsOn"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
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
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                        >
                                            <option value="24h">24h (14:00)</option>
                                            <option value="12h">12h (2:00 PM)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                            <h2 class="text-base sm:text-lg font-medium text-gray-900 mb-4">
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
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ t('settings.profile.newPassword') }}
                                    </label>
                                    <input
                                        v-model="passwordData.password"
                                        type="password"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ t('settings.profile.confirmPassword') }}
                                    </label>
                                    <input
                                        v-model="passwordData.password_confirmation"
                                        type="password"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    />
                                </div>

                                <Button
                                    variant="secondary"
                                    @click="changePassword"
                                    :loading="changingPassword"
                                    :disabled="!passwordData.current_password || !passwordData.password"
                                    class="w-full sm:w-auto"
                                >
                                    {{ t('settings.profile.updatePassword') }}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- AI Tab -->
                    <div v-if="activeTab === 'ai'" class="space-y-4 sm:space-y-6">
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                            <h2 class="text-base sm:text-lg font-medium text-gray-900 mb-2">
                                {{ t('settings.ai.title') }}
                            </h2>
                            <p class="text-sm text-gray-500 mb-4 sm:mb-6">
                                {{ t('settings.ai.description') }}
                            </p>

                            <div class="space-y-5 sm:space-y-6">
                                <!-- Creativity -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        {{ t('settings.ai.creativity') }}
                                    </label>
                                    <div class="flex flex-col sm:flex-row gap-2">
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
                                    <div class="flex flex-col sm:flex-row gap-2">
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
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    ></textarea>
                                    <p class="mt-1 text-xs text-gray-400 text-right">
                                        {{ settings.ai.customInstructions?.length || 0 }} / 1000
                                    </p>
                                </div>

                                <!-- Auto Suggest -->
                                <div class="flex items-start sm:items-center justify-between gap-4">
                                    <div class="flex-1">
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
                    <div v-if="activeTab === 'notifications'" class="space-y-4 sm:space-y-6">
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                            <h2 class="text-base sm:text-lg font-medium text-gray-900 mb-2">
                                {{ t('settings.notifications.title') }}
                            </h2>
                            <p class="text-sm text-gray-500 mb-4 sm:mb-6">
                                {{ t('settings.notifications.description') }}
                            </p>

                            <div class="space-y-4">
                                <!-- Email notifications master toggle -->
                                <div class="flex items-start sm:items-center justify-between gap-4 py-3 border-b border-gray-100">
                                    <div class="flex-1">
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
                                    class="flex items-start sm:items-center justify-between gap-4 py-3"
                                    :class="{ 'opacity-50 pointer-events-none': !settings.notifications.email }"
                                >
                                    <div class="flex-1">
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

                    <!-- AI Keys Tab -->
                    <div v-if="activeTab === 'aiKeys'" class="space-y-4 sm:space-y-6">
                        <div v-if="brandsStore.currentBrand" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                            <AiKeysPanel :brandId="brandsStore.currentBrand.id" />
                        </div>
                        <div v-else class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">{{ t('brands.noBrands') }}</p>
                        </div>
                    </div>

                    <!-- Tokens Tab -->
                    <div v-if="activeTab === 'tokens'" class="space-y-4 sm:space-y-6">
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4 sm:mb-6">
                                <div>
                                    <h2 class="text-base sm:text-lg font-medium text-gray-900">
                                        {{ t('settings.tokens.title') }}
                                    </h2>
                                    <p class="text-sm text-gray-500 mt-1">
                                        {{ t('settings.tokens.description') }}
                                    </p>
                                </div>
                                <Button @click="showTokenForm = true" class="w-full sm:w-auto">
                                    {{ t('settings.tokens.createToken') }}
                                </Button>
                            </div>

                            <div v-if="apiTokensStore.loading" class="flex items-center justify-center py-12">
                                <LoadingSpinner />
                            </div>

                            <div v-else-if="apiTokensStore.tokens.length === 0" class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">
                                    {{ t('settings.tokens.noTokens') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ t('settings.tokens.noTokensHint') }}
                                </p>
                            </div>

                            <ApiTokenList
                                v-else
                                :tokens="apiTokensStore.tokens"
                                @revoke="handleRevokeToken"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Token Form Modal -->
        <div
            v-if="showTokenForm"
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex items-end sm:items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                    @click="showTokenForm = false"
                ></div>
                <div class="relative bg-white rounded-t-xl sm:rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:max-w-lg">
                    <ApiTokenForm
                        @submit="handleCreateToken"
                        @cancel="showTokenForm = false"
                    />
                </div>
            </div>
        </div>

        <!-- New Token Display Modal -->
        <div
            v-if="newTokenValue"
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="new-token-modal"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex items-end sm:items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                ></div>
                <div class="relative bg-white rounded-t-xl sm:rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:max-w-lg">
                    <div class="p-4 sm:p-6">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h2 class="ml-3 text-base sm:text-lg font-semibold text-gray-900">
                                {{ t('settings.tokens.tokenCreatedTitle') }}
                            </h2>
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 sm:p-4 mb-4">
                            <div class="flex">
                                <svg class="w-5 h-5 text-yellow-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-sm text-yellow-800">
                                    {{ t('settings.tokens.tokenWarning') }}
                                </p>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ t('settings.tokens.yourToken') }}
                            </label>
                            <div class="flex flex-col sm:flex-row gap-2">
                                <input
                                    :value="newTokenValue"
                                    readonly
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg sm:rounded-r-none bg-gray-50 font-mono text-xs sm:text-sm overflow-x-auto"
                                />
                                <button
                                    @click="handleCopyToken"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg sm:rounded-l-none hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    {{ t('common.copy') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-4 sm:px-6 py-3 flex justify-end rounded-b-lg">
                        <Button @click="closeNewTokenModal" class="w-full sm:w-auto">
                            {{ t('common.done') }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
