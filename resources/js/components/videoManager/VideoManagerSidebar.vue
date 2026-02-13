<script setup>
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { useVideoManagerStore } from '@/stores/videoManager';
import BrandSwitcher from '@/components/brand/BrandSwitcher.vue';

const { t } = useI18n();
const route = useRoute();
const brandsStore = useBrandsStore();
const videoManagerStore = useVideoManagerStore();

const isCollapsed = computed(() => videoManagerStore.sidebarCollapsed);

const toggleSidebar = () => {
    videoManagerStore.toggleSidebar();
};

const mainNav = computed(() => [
    {
        to: '/app/video',
        label: t('videoManager.nav.dashboard'),
        icon: 'dashboard',
        isActive: route.path === '/app/video',
    },
    {
        to: '/app/video/library',
        label: t('videoManager.nav.library'),
        icon: 'library',
        isActive: route.path === '/app/video/library',
        badge: videoManagerStore.processingCount,
    },
    {
        to: '/app/video/upload',
        label: t('videoManager.nav.upload'),
        icon: 'upload',
        isActive: route.path === '/app/video/upload',
    },
]);

const toolsNav = computed(() => {
    const items = [];
    if (videoManagerStore.currentProject) {
        items.push({
            to: `/app/video/editor/${videoManagerStore.currentProject.id}`,
            label: t('videoManager.nav.editor'),
            icon: 'editor',
            isActive: route.path.startsWith('/app/video/editor'),
        });
    }
    return items;
});

const settingsNav = computed(() => [
    {
        to: '/app/video/settings',
        label: t('videoManager.nav.settings'),
        icon: 'settings',
        isActive: route.path === '/app/video/settings',
    },
]);

const closeMobileMenu = () => {
    videoManagerStore.mobileMenuOpen = false;
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
            v-if="videoManagerStore.mobileMenuOpen"
            class="lg:hidden fixed inset-0 z-40 bg-black/60"
            @click="closeMobileMenu"
        />
    </Transition>

    <aside
        :class="[
            'fixed lg:static inset-y-0 left-0 z-50 flex flex-col bg-gray-900 border-r border-gray-800 transition-all duration-300 lg:translate-x-0',
            isCollapsed ? 'w-16' : 'w-64',
            videoManagerStore.mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
        ]"
    >
        <!-- Header -->
        <div class="h-14 flex items-center px-4 border-b border-gray-800 shrink-0">
            <RouterLink
                to="/app/video"
                class="flex items-center min-w-0"
                @click="closeMobileMenu"
            >
                <div class="w-8 h-8 rounded-lg bg-violet-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
                <span v-if="!isCollapsed" class="ml-3 text-sm font-semibold text-white truncate">
                    Video Manager
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
                    {{ t('videoManager.nav.sectionMain') }}
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
                                ? 'bg-violet-600/20 text-violet-400'
                                : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200',
                            isCollapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2',
                        ]"
                        :title="isCollapsed ? link.label : undefined"
                    >
                        <!-- Dashboard -->
                        <svg v-if="link.icon === 'dashboard'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />
                        </svg>
                        <!-- Library -->
                        <svg v-else-if="link.icon === 'library'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                        </svg>
                        <!-- Upload -->
                        <svg v-else-if="link.icon === 'upload'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>

                        <span v-if="!isCollapsed" class="ml-3 truncate">{{ link.label }}</span>

                        <!-- Badge -->
                        <span
                            v-if="link.badge && link.badge > 0"
                            class="shrink-0 min-w-[20px] h-5 flex items-center justify-center rounded-full bg-violet-500 text-white text-xs font-semibold px-1.5"
                            :class="isCollapsed ? 'absolute -top-1 -right-1' : 'ml-auto'"
                        >
                            {{ link.badge > 99 ? '99+' : link.badge }}
                        </span>
                    </RouterLink>
                </div>
            </div>

            <!-- Tools (shown when project is selected) -->
            <div v-if="toolsNav.length > 0">
                <div v-if="!isCollapsed" class="px-3 mb-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wider">
                    {{ t('videoManager.nav.sectionTools') }}
                </div>
                <div class="space-y-0.5">
                    <RouterLink
                        v-for="link in toolsNav"
                        :key="link.to"
                        :to="link.to"
                        @click="closeMobileMenu"
                        class="flex items-center rounded-lg text-sm font-medium transition-colors duration-150"
                        :class="[
                            link.isActive
                                ? 'bg-violet-600/20 text-violet-400'
                                : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200',
                            isCollapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2',
                        ]"
                        :title="isCollapsed ? link.label : undefined"
                    >
                        <!-- Editor -->
                        <svg v-if="link.icon === 'editor'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                        </svg>

                        <span v-if="!isCollapsed" class="ml-3 truncate">{{ link.label }}</span>
                    </RouterLink>
                </div>
            </div>

            <!-- Settings -->
            <div>
                <div v-if="!isCollapsed" class="px-3 mb-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wider">
                    {{ t('videoManager.nav.sectionSettings') }}
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
                                ? 'bg-violet-600/20 text-violet-400'
                                : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200',
                            isCollapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2',
                        ]"
                        :title="isCollapsed ? link.label : undefined"
                    >
                        <!-- Settings -->
                        <svg v-if="link.icon === 'settings'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.248a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
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
                :title="isCollapsed ? t('videoManager.nav.backToApp') : undefined"
            >
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                </svg>
                <span v-if="!isCollapsed" class="ml-3 truncate">{{ t('videoManager.nav.backToApp') }}</span>
            </RouterLink>
        </div>
    </aside>
</template>
