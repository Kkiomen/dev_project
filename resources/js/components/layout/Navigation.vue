<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useBrandsStore } from '@/stores/brands';
import { useSettingsStore } from '@/stores/settings';
import BrandSwitcher from '@/components/brand/BrandSwitcher.vue';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import ActiveTasksIndicator from '@/components/tasks/ActiveTasksIndicator.vue';

const { t, locale } = useI18n();
const route = useRoute();
const authStore = useAuthStore();
const brandsStore = useBrandsStore();
const settingsStore = useSettingsStore();
const showMobileMenu = ref(false);
const showLangMenu = ref(false);

const availableLanguages = [
    { code: 'en', name: 'English', flag: '🇬🇧' },
    { code: 'pl', name: 'Polski', flag: '🇵🇱' },
];

const currentLanguage = computed(() => {
    return availableLanguages.find(l => l.code === locale.value) || availableLanguages[0];
});

const changeLanguage = (langCode) => {
    settingsStore.setLanguage(langCode);
    showLangMenu.value = false;
};

const closeMobileMenu = () => {
    showMobileMenu.value = false;
};

onMounted(() => {
    brandsStore.fetchBrands();
});

const logout = () => {
    authStore.logout();
};

const navLinks = computed(() => [
    {
        to: '/dashboard',
        label: t('navigation.dashboard'),
        icon: 'dashboard',
        isActive: route.path === '/dashboard',
    },
    {
        to: '/data',
        label: t('navigation.data'),
        icon: 'data',
        isActive: route.path.startsWith('/data') || route.path.startsWith('/bases') || route.path.startsWith('/tables'),
    },
    {
        to: '/templates',
        label: t('navigation.graphics'),
        icon: 'graphics',
        isActive: route.path.startsWith('/templates'),
    },
    {
        to: '/calendar',
        label: t('navigation.calendar'),
        icon: 'calendar',
        isActive: route.path.startsWith('/calendar') || (route.path.startsWith('/posts') && route.path !== '/posts/automation'),
    },
    {
        to: '/posts/automation',
        label: t('navigation.postAutomation'),
        icon: 'automation',
        isActive: route.path === '/posts/automation',
    },
    {
        to: '/rss-feeds',
        label: t('navigation.rssFeeds'),
        icon: 'rss',
        isActive: route.path === '/rss-feeds',
    },
    {
        to: '/rss-feeds/today',
        label: t('rssFeeds.todayFeed'),
        icon: 'rss-today',
        isActive: route.path === '/rss-feeds/today',
        indent: true,
    },
    {
        to: '/boards',
        label: t('navigation.boards'),
        icon: 'boards',
        isActive: route.path.startsWith('/boards'),
    },
    {
        to: '/docs',
        label: t('navigation.docs'),
        icon: 'docs',
        isActive: route.path.startsWith('/docs'),
    },
]);

const bottomLinks = computed(() => [
    {
        to: '/settings',
        label: t('navigation.settings'),
        icon: 'settings',
        isActive: route.path.startsWith('/settings'),
    },
    {
        to: '/brands',
        label: t('navigation.brands'),
        icon: 'brands',
        isActive: route.path === '/brands',
    },
]);
</script>

<template>
    <!-- Mobile top bar -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-white border-b border-gray-200 h-14 flex items-center px-4">
        <button
            @click="showMobileMenu = true"
            class="p-2 -ml-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none"
        >
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <RouterLink to="/dashboard" class="ml-3">
            <img src="/assets/images/logo_aisello_black.svg" alt="Logo" class="h-7 w-auto" />
        </RouterLink>
        <div class="ml-auto flex items-center space-x-2">
            <ActiveTasksIndicator />
            <NotificationBell />
        </div>
    </div>

    <!-- Mobile overlay backdrop -->
    <Transition
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-300"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="showMobileMenu"
            class="lg:hidden fixed inset-0 z-40 bg-black/50"
            @click="closeMobileMenu"
        />
    </Transition>

    <!-- Sidebar -->
    <aside
        :class="[
            'fixed lg:static inset-y-0 left-0 z-50 w-60 bg-white border-r border-gray-200 flex flex-col transition-transform duration-300 lg:translate-x-0 lg:transition-none',
            showMobileMenu ? 'translate-x-0' : '-translate-x-full'
        ]"
    >
        <!-- Logo -->
        <div class="h-14 flex items-center px-5 border-b border-gray-100 shrink-0">
            <RouterLink to="/dashboard" class="flex items-center" @click="closeMobileMenu">
                <img src="/assets/images/logo_aisello_black.svg" alt="Logo" class="h-8 w-auto" />
            </RouterLink>
            <button
                @click="closeMobileMenu"
                class="ml-auto lg:hidden p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Brand Switcher -->
        <div class="px-3 py-3 border-b border-gray-100 shrink-0">
            <BrandSwitcher />
        </div>

        <!-- Main Navigation -->
        <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-1">
            <RouterLink
                v-for="link in navLinks"
                :key="link.to"
                :to="link.to"
                @click="closeMobileMenu"
                class="flex items-center rounded-lg text-sm font-medium transition-colors duration-150"
                :class="[
                    link.isActive
                        ? 'bg-blue-50 text-blue-700'
                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                    link.indent ? 'pl-8 pr-3 py-2 text-xs' : 'px-3 py-2.5',
                ]"
            >
                <!-- Dashboard -->
                <svg v-if="link.icon === 'dashboard'" class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
                <!-- Data -->
                <svg v-else-if="link.icon === 'data'" class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                </svg>
                <!-- Graphics -->
                <svg v-else-if="link.icon === 'graphics'" class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                </svg>
                <!-- Calendar -->
                <svg v-else-if="link.icon === 'calendar'" class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                </svg>
                <!-- Automation -->
                <svg v-else-if="link.icon === 'automation'" class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                </svg>
                <!-- RSS -->
                <svg v-else-if="link.icon === 'rss'" class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12.75 19.5v-.75a7.5 7.5 0 0 0-7.5-7.5H4.5m0-6.75h.75c7.87 0 14.25 6.38 14.25 14.25v.75M6 18.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                </svg>
                <!-- RSS Today -->
                <svg v-else-if="link.icon === 'rss-today'" class="w-4 h-4 mr-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z" />
                </svg>
                <!-- Boards -->
                <svg v-else-if="link.icon === 'boards'" class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125Z" />
                </svg>
                <!-- Docs -->
                <svg v-else-if="link.icon === 'docs'" class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                </svg>
                <span class="truncate">{{ link.label }}</span>
            </RouterLink>
        </nav>

        <!-- Bottom Section -->
        <div class="border-t border-gray-200 shrink-0">
            <!-- Bottom nav links -->
            <div class="px-3 py-2 space-y-1">
                <RouterLink
                    v-for="link in bottomLinks"
                    :key="link.to"
                    :to="link.to"
                    @click="closeMobileMenu"
                    class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150"
                    :class="link.isActive
                        ? 'bg-blue-50 text-blue-700'
                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                    "
                >
                    <!-- Settings -->
                    <svg v-if="link.icon === 'settings'" class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <!-- Brands -->
                    <svg v-else-if="link.icon === 'brands'" class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                    </svg>
                    <span class="truncate">{{ link.label }}</span>
                </RouterLink>
                <!-- Admin section -->
                <template v-if="authStore.isAdmin">
                    <div class="pt-2 mt-2 border-t border-gray-100">
                        <div class="px-3 mb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            Admin
                        </div>
                        <RouterLink
                            to="/admin/users"
                            @click="closeMobileMenu"
                            class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150"
                            :class="$route.path === '/admin/users'
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                            "
                        >
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            <span class="truncate">{{ t('navigation.adminUsers') }}</span>
                        </RouterLink>
                        <RouterLink
                            to="/admin/dev-tasks"
                            @click="closeMobileMenu"
                            class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150"
                            :class="$route.path === '/admin/dev-tasks'
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                            "
                        >
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                            </svg>
                            <span class="truncate">{{ t('navigation.devTasks') }}</span>
                        </RouterLink>
                    </div>
                </template>
            </div>

            <!-- Indicators row -->
            <div class="hidden lg:flex items-center px-5 py-2 border-t border-gray-100 space-x-3">
                <ActiveTasksIndicator />
                <NotificationBell />

                <!-- Language toggle -->
                <div class="relative ml-auto">
                    <button
                        @click="showLangMenu = !showLangMenu"
                        class="flex items-center px-2 py-1 text-sm rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition"
                    >
                        <span class="text-base">{{ currentLanguage.flag }}</span>
                        <span class="ml-1 text-xs">{{ currentLanguage.code.toUpperCase() }}</span>
                    </button>
                    <!-- Backdrop to close menu -->
                    <div v-if="showLangMenu" class="fixed inset-0 z-40" @click="showLangMenu = false" />
                    <Transition
                        enter-active-class="transition ease-out duration-100"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="showLangMenu"
                            class="absolute bottom-full left-0 mb-1 w-36 bg-white rounded-md shadow-lg ring-1 ring-black/5 py-1 z-50"
                        >
                            <button
                                v-for="lang in availableLanguages"
                                :key="lang.code"
                                @click="changeLanguage(lang.code)"
                                class="w-full flex items-center px-3 py-2 text-sm hover:bg-gray-100 transition"
                                :class="locale === lang.code ? 'bg-blue-50 text-blue-700' : 'text-gray-700'"
                            >
                                <span class="text-base mr-2">{{ lang.flag }}</span>
                                <span>{{ lang.name }}</span>
                                <svg v-if="locale === lang.code" class="ml-auto h-4 w-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- User section -->
            <div class="px-3 py-3 border-t border-gray-100">
                <!-- Mobile language switcher -->
                <div class="lg:hidden mb-3">
                    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-3">
                        {{ t('navigation.language') }}
                    </div>
                    <div class="flex space-x-2 px-3">
                        <button
                            v-for="lang in availableLanguages"
                            :key="lang.code"
                            @click="changeLanguage(lang.code)"
                            class="flex items-center px-3 py-1.5 rounded-lg text-sm font-medium transition"
                            :class="locale === lang.code ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        >
                            <span class="mr-1.5">{{ lang.flag }}</span>
                            <span>{{ lang.code.toUpperCase() }}</span>
                        </button>
                    </div>
                </div>

                <!-- User info + logout -->
                <div class="flex items-center px-3 py-2">
                    <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-sm font-semibold shrink-0">
                        {{ (authStore.user?.name || '?').charAt(0).toUpperCase() }}
                    </div>
                    <div class="ml-3 min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-700 truncate">{{ authStore.user?.name }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ authStore.user?.email }}</p>
                    </div>
                    <button
                        @click="logout"
                        class="ml-2 p-1.5 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition shrink-0"
                        :title="t('navigation.logout')"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </aside>

</template>
