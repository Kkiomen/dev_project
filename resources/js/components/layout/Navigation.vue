<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useBrandsStore } from '@/stores/brands';
import Dropdown from '@/components/common/Dropdown.vue';
import BrandSwitcher from '@/components/brand/BrandSwitcher.vue';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import ActiveTasksIndicator from '@/components/tasks/ActiveTasksIndicator.vue';

const { t } = useI18n();
const authStore = useAuthStore();
const brandsStore = useBrandsStore();
const showMobileMenu = ref(false);

onMounted(() => {
    brandsStore.fetchBrands();
});

const logout = () => {
    authStore.logout();
};
</script>

<template>
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <!-- Logo -->
                    <div class="shrink-0 flex items-center mr-4">
                        <RouterLink to="/dashboard">
                            <img src="/assets/images/logo_aisello_black.svg" alt="Logo" class="h-9 w-auto" />
                        </RouterLink>
                    </div>

                    <!-- Brand Switcher -->
                    <div class="hidden sm:flex sm:items-center mr-6">
                        <BrandSwitcher />
                    </div>

                    <!-- Navigation Links -->
                    <div class="hidden sm:flex sm:space-x-8">
                        <RouterLink
                            to="/dashboard"
                            class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none"
                            active-class="border-blue-500 text-gray-900"
                            :class="{
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': $route.path !== '/dashboard',
                            }"
                        >
                            {{ t('navigation.dashboard') }}
                        </RouterLink>
                        <RouterLink
                            to="/templates"
                            class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none"
                            :class="{
                                'border-blue-500 text-gray-900': $route.path.startsWith('/templates'),
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': !$route.path.startsWith('/templates'),
                            }"
                        >
                            {{ t('navigation.graphics') }}
                        </RouterLink>
                        <RouterLink
                            to="/calendar"
                            class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none"
                            :class="{
                                'border-blue-500 text-gray-900': $route.path.startsWith('/calendar') || $route.path.startsWith('/posts'),
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': !$route.path.startsWith('/calendar') && !$route.path.startsWith('/posts'),
                            }"
                        >
                            {{ t('navigation.calendar') }}
                        </RouterLink>
                        <RouterLink
                            to="/docs"
                            class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none"
                            :class="{
                                'border-blue-500 text-gray-900': $route.path.startsWith('/docs'),
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': !$route.path.startsWith('/docs'),
                            }"
                        >
                            {{ t('navigation.docs') }}
                        </RouterLink>
                    </div>
                </div>

                <div class="hidden sm:flex sm:items-center sm:ml-6 sm:space-x-4">
                    <!-- Active Tasks -->
                    <ActiveTasksIndicator />

                    <!-- Notifications -->
                    <NotificationBell />

                    <!-- User Dropdown -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150"
                            >
                                {{ authStore.user?.name || t('navigation.menu') }}

                                <svg
                                    class="ml-2 -mr-0.5 h-4 w-4"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </template>

                        <template #content>
                            <RouterLink
                                to="/settings"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            >
                                {{ t('navigation.settings') }}
                            </RouterLink>
                            <RouterLink
                                to="/settings?tab=tokens"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            >
                                {{ t('navigation.tokens') }}
                            </RouterLink>
                            <RouterLink
                                to="/brands"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            >
                                {{ t('navigation.brands') }}
                            </RouterLink>
                            <button
                                @click="logout"
                                class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            >
                                {{ t('navigation.logout') }}
                            </button>
                        </template>
                    </Dropdown>
                </div>

                <!-- Hamburger -->
                <div class="-mr-2 flex items-center sm:hidden">
                    <button
                        @click="showMobileMenu = !showMobileMenu"
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
                    >
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path
                                :class="{ hidden: showMobileMenu, 'inline-flex': !showMobileMenu }"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                            <path
                                :class="{ hidden: !showMobileMenu, 'inline-flex': showMobileMenu }"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div :class="{ block: showMobileMenu, hidden: !showMobileMenu }" class="sm:hidden">
            <div class="pt-2 pb-3 space-y-1">
                <RouterLink
                    to="/dashboard"
                    class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out"
                    active-class="border-blue-500 text-blue-700 bg-blue-50"
                >
                    {{ t('navigation.dashboard') }}
                </RouterLink>
                <RouterLink
                    to="/templates"
                    class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out"
                    :class="{
                        'border-blue-500 text-blue-700 bg-blue-50': $route.path.startsWith('/templates'),
                        'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300': !$route.path.startsWith('/templates'),
                    }"
                >
                    {{ t('navigation.graphics') }}
                </RouterLink>
                <RouterLink
                    to="/calendar"
                    class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out"
                    :class="{
                        'border-blue-500 text-blue-700 bg-blue-50': $route.path.startsWith('/calendar') || $route.path.startsWith('/posts'),
                        'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300': !$route.path.startsWith('/calendar') && !$route.path.startsWith('/posts'),
                    }"
                >
                    {{ t('navigation.calendar') }}
                </RouterLink>
                <RouterLink
                    to="/docs"
                    class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out"
                    :class="{
                        'border-blue-500 text-blue-700 bg-blue-50': $route.path.startsWith('/docs'),
                        'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300': !$route.path.startsWith('/docs'),
                    }"
                >
                    {{ t('navigation.docs') }}
                </RouterLink>
            </div>

            <div class="pt-4 pb-1 border-t border-gray-200">
                <div class="px-4">
                    <div class="font-medium text-base text-gray-800">
                        {{ authStore.user?.name }}
                    </div>
                    <div class="font-medium text-sm text-gray-500">
                        {{ authStore.user?.email }}
                    </div>
                </div>

                <div class="mt-3 space-y-1">
                    <RouterLink
                        to="/settings"
                        class="block pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300"
                    >
                        {{ t('navigation.settings') }}
                    </RouterLink>
                    <RouterLink
                        to="/settings?tab=tokens"
                        class="block pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300"
                    >
                        {{ t('navigation.tokens') }}
                    </RouterLink>
                    <RouterLink
                        to="/brands"
                        class="block pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300"
                    >
                        {{ t('navigation.brands') }}
                    </RouterLink>
                    <button
                        @click="logout"
                        class="block w-full text-left pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300"
                    >
                        {{ t('navigation.logout') }}
                    </button>
                </div>
            </div>
        </div>
    </nav>
</template>
