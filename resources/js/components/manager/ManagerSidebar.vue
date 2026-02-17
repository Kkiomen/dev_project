<script setup>
import { computed, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { useManagerStore } from '@/stores/manager';
import BrandSwitcher from '@/components/brand/BrandSwitcher.vue';

const { t } = useI18n();
const route = useRoute();
const brandsStore = useBrandsStore();
const managerStore = useManagerStore();

const isCollapsed = computed(() => managerStore.sidebarCollapsed);

const toggleSidebar = () => {
    managerStore.toggleSidebar();
};

const mainNav = computed(() => [
    {
        to: '/app/manager',
        label: t('manager.nav.dashboard'),
        icon: 'command-center',
        isActive: route.path === '/app/manager',
    },
    {
        to: '/app/manager/approval',
        label: t('manager.nav.approval'),
        icon: 'approval',
        isActive: route.path === '/app/manager/approval',
        badge: managerStore.pendingApprovalCount,
    },
    {
        to: '/app/manager/calendar',
        label: t('manager.nav.calendar'),
        icon: 'calendar',
        isActive: route.path === '/app/manager/calendar',
    },
    {
        to: '/app/manager/content',
        label: t('manager.nav.content'),
        icon: 'content',
        isActive: route.path.startsWith('/app/manager/content'),
    },
    {
        to: '/app/manager/pipelines',
        label: t('manager.nav.pipelines'),
        icon: 'pipelines',
        isActive: route.path.startsWith('/app/manager/pipelines'),
    },
    {
        to: '/app/manager/strategy',
        label: t('manager.nav.strategy'),
        icon: 'strategy',
        isActive: route.path === '/app/manager/strategy',
    },
]);

const analyticsNav = computed(() => [
    {
        to: '/app/manager/analytics',
        label: t('manager.nav.analytics'),
        icon: 'analytics',
        isActive: route.path === '/app/manager/analytics',
    },
    {
        to: '/app/manager/analytics/reports',
        label: t('manager.nav.reports'),
        icon: 'reports',
        isActive: route.path === '/app/manager/analytics/reports',
    },
    {
        to: '/app/manager/competitors',
        label: t('manager.nav.competitors'),
        icon: 'competitors',
        isActive: route.path === '/app/manager/competitors',
    },
    {
        to: '/app/manager/listening',
        label: t('manager.nav.listening'),
        icon: 'listening',
        isActive: route.path === '/app/manager/listening',
    },
    {
        to: '/app/manager/rss',
        label: t('manager.nav.rss'),
        icon: 'rss',
        isActive: route.path === '/app/manager/rss',
    },
]);

const engagementNav = computed(() => [
    {
        to: '/app/manager/inbox',
        label: t('manager.nav.inbox'),
        icon: 'inbox',
        isActive: route.path === '/app/manager/inbox',
    },
]);

const settingsNav = computed(() => [
    {
        to: '/app/manager/brand',
        label: t('manager.nav.brandKit'),
        icon: 'brand-kit',
        isActive: route.path === '/app/manager/brand',
    },
    {
        to: '/app/manager/accounts',
        label: t('manager.nav.accounts'),
        icon: 'accounts',
        isActive: route.path === '/app/manager/accounts',
    },
    {
        to: '/app/manager/ai-chat',
        label: t('manager.nav.aiChat'),
        icon: 'ai-chat',
        isActive: route.path === '/app/manager/ai-chat',
    },
    {
        to: '/app/manager/tutorial',
        label: t('manager.nav.tutorial'),
        icon: 'tutorial',
        isActive: route.path === '/app/manager/tutorial',
    },
]);

const closeMobileMenu = () => {
    managerStore.mobileMenuOpen = false;
};
</script>

<template>
    <!-- Mobile overlay -->
    <Transition
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-300"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="managerStore.mobileMenuOpen"
            class="lg:hidden fixed inset-0 z-40 bg-black/60"
            @click="closeMobileMenu"
        />
    </Transition>

    <aside
        :class="[
            'fixed lg:static inset-y-0 left-0 z-50 flex flex-col bg-gray-900 border-r border-gray-800 transition-all duration-300 lg:translate-x-0',
            isCollapsed ? 'w-16' : 'w-64',
            managerStore.mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
        ]"
    >
        <!-- Header -->
        <div class="h-14 flex items-center px-4 border-b border-gray-800 shrink-0">
            <RouterLink
                to="/app/manager"
                class="flex items-center min-w-0"
                @click="closeMobileMenu"
            >
                <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                    </svg>
                </div>
                <span v-if="!isCollapsed" class="ml-3 text-sm font-semibold text-white truncate">
                    AI Manager
                </span>
            </RouterLink>

            <!-- Collapse toggle (desktop only) -->
            <button
                @click="toggleSidebar"
                class="hidden lg:flex ml-auto p-1 rounded text-gray-500 hover:text-gray-300 hover:bg-gray-800 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path v-if="isCollapsed" stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </button>

            <!-- Mobile close button -->
            <button
                @click="closeMobileMenu"
                class="lg:hidden ml-auto p-1 rounded text-gray-500 hover:text-gray-300"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Brand Switcher -->
        <div v-if="!isCollapsed" class="px-3 py-3 border-b border-gray-800 shrink-0">
            <BrandSwitcher />
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-6">
            <!-- Main -->
            <div>
                <div v-if="!isCollapsed" class="px-3 mb-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wider">
                    {{ t('manager.nav.sectionMain') }}
                </div>
                <div class="space-y-0.5">
                    <RouterLink
                        v-for="link in mainNav"
                        :key="link.to"
                        :to="link.to"
                        @click="closeMobileMenu"
                        class="flex items-center rounded-lg text-sm font-medium transition-colors duration-150"
                        :class="[
                            link.isActive
                                ? 'bg-indigo-600/20 text-indigo-400'
                                : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200',
                            isCollapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2',
                        ]"
                        :title="isCollapsed ? link.label : undefined"
                    >
                        <!-- Command Center -->
                        <svg v-if="link.icon === 'command-center'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />
                        </svg>
                        <!-- Approval -->
                        <svg v-else-if="link.icon === 'approval'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <!-- Calendar -->
                        <svg v-else-if="link.icon === 'calendar'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <!-- Content -->
                        <svg v-else-if="link.icon === 'content'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <!-- Pipelines -->
                        <svg v-else-if="link.icon === 'pipelines'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                        <!-- Strategy -->
                        <svg v-else-if="link.icon === 'strategy'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                        </svg>

                        <span v-if="!isCollapsed" class="ml-3 truncate">{{ link.label }}</span>

                        <!-- Badge -->
                        <span
                            v-if="link.badge && link.badge > 0"
                            class="shrink-0 min-w-[20px] h-5 flex items-center justify-center rounded-full bg-red-500 text-white text-xs font-semibold px-1.5"
                            :class="isCollapsed ? 'absolute -top-1 -right-1' : 'ml-auto'"
                        >
                            {{ link.badge > 99 ? '99+' : link.badge }}
                        </span>
                    </RouterLink>
                </div>
            </div>

            <!-- Analytics -->
            <div>
                <div v-if="!isCollapsed" class="px-3 mb-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wider">
                    {{ t('manager.nav.sectionAnalytics') }}
                </div>
                <div class="space-y-0.5">
                    <RouterLink
                        v-for="link in analyticsNav"
                        :key="link.to"
                        :to="link.to"
                        @click="closeMobileMenu"
                        class="flex items-center rounded-lg text-sm font-medium transition-colors duration-150"
                        :class="[
                            link.isActive
                                ? 'bg-indigo-600/20 text-indigo-400'
                                : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200',
                            isCollapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2',
                        ]"
                        :title="isCollapsed ? link.label : undefined"
                    >
                        <!-- Analytics -->
                        <svg v-if="link.icon === 'analytics'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                        <!-- Reports -->
                        <svg v-else-if="link.icon === 'reports'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <!-- Competitors -->
                        <svg v-else-if="link.icon === 'competitors'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                        <!-- Listening -->
                        <svg v-else-if="link.icon === 'listening'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <!-- RSS -->
                        <svg v-else-if="link.icon === 'rss'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12.75 19.5v-.75a7.5 7.5 0 0 0-7.5-7.5H4.5m0-6.75h.75c7.87 0 14.25 6.38 14.25 14.25v.75M6 18.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>

                        <span v-if="!isCollapsed" class="ml-3 truncate">{{ link.label }}</span>
                    </RouterLink>
                </div>
            </div>

            <!-- Engagement -->
            <div>
                <div v-if="!isCollapsed" class="px-3 mb-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wider">
                    {{ t('manager.nav.sectionEngagement') }}
                </div>
                <div class="space-y-0.5">
                    <RouterLink
                        v-for="link in engagementNav"
                        :key="link.to"
                        :to="link.to"
                        @click="closeMobileMenu"
                        class="flex items-center rounded-lg text-sm font-medium transition-colors duration-150"
                        :class="[
                            link.isActive
                                ? 'bg-indigo-600/20 text-indigo-400'
                                : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200',
                            isCollapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2',
                        ]"
                        :title="isCollapsed ? link.label : undefined"
                    >
                        <!-- Inbox -->
                        <svg v-if="link.icon === 'inbox'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3M2.25 18.75h19.5" />
                        </svg>

                        <span v-if="!isCollapsed" class="ml-3 truncate">{{ link.label }}</span>
                    </RouterLink>
                </div>
            </div>

            <!-- Settings -->
            <div>
                <div v-if="!isCollapsed" class="px-3 mb-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wider">
                    {{ t('manager.nav.sectionSettings') }}
                </div>
                <div class="space-y-0.5">
                    <RouterLink
                        v-for="link in settingsNav"
                        :key="link.to"
                        :to="link.to"
                        @click="closeMobileMenu"
                        class="flex items-center rounded-lg text-sm font-medium transition-colors duration-150"
                        :class="[
                            link.isActive
                                ? 'bg-indigo-600/20 text-indigo-400'
                                : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200',
                            isCollapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2',
                        ]"
                        :title="isCollapsed ? link.label : undefined"
                    >
                        <!-- Brand Kit -->
                        <svg v-if="link.icon === 'brand-kit'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z" />
                        </svg>
                        <!-- Accounts -->
                        <svg v-else-if="link.icon === 'accounts'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                        <!-- AI Chat -->
                        <svg v-else-if="link.icon === 'ai-chat'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                        </svg>
                        <!-- Tutorial -->
                        <svg v-else-if="link.icon === 'tutorial'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                        </svg>

                        <span v-if="!isCollapsed" class="ml-3 truncate">{{ link.label }}</span>
                    </RouterLink>
                </div>
            </div>
        </nav>

        <!-- Back to app link -->
        <div class="border-t border-gray-800 px-2 py-3 shrink-0">
            <RouterLink
                to="/dashboard"
                class="flex items-center rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-800 hover:text-gray-300 transition-colors duration-150"
                :class="isCollapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2'"
                :title="isCollapsed ? t('manager.nav.backToApp') : undefined"
            >
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                </svg>
                <span v-if="!isCollapsed" class="ml-3 truncate">{{ t('manager.nav.backToApp') }}</span>
            </RouterLink>
        </div>
    </aside>
</template>
