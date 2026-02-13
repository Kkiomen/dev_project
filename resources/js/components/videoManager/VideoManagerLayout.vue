<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useBrandsStore } from '@/stores/brands';
import { useVideoManagerStore } from '@/stores/videoManager';
import VideoManagerSidebar from './VideoManagerSidebar.vue';
import NotificationBell from '@/components/notifications/NotificationBell.vue';

const authStore = useAuthStore();
const brandsStore = useBrandsStore();
const videoManagerStore = useVideoManagerStore();

onMounted(() => {
    authStore.fetchUser();
    brandsStore.fetchBrands();
});
</script>

<template>
    <div class="h-screen flex bg-gray-950 overflow-hidden">
        <VideoManagerSidebar />

        <div class="flex-1 flex flex-col min-w-0">
            <!-- Mobile top bar -->
            <div class="lg:hidden h-14 flex items-center px-4 bg-gray-900 border-b border-gray-800 shrink-0">
                <button
                    @click="videoManagerStore.mobileMenuOpen = true"
                    class="p-2 -ml-2 rounded-md text-gray-400 hover:text-gray-200 hover:bg-gray-800"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="ml-3 flex items-center">
                    <div class="w-7 h-7 rounded-lg bg-violet-600 flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </div>
                    <span class="ml-2 text-sm font-semibold text-white">Video Manager</span>
                </div>
                <div class="ml-auto">
                    <NotificationBell />
                </div>
            </div>

            <!-- Main content -->
            <main class="flex-1 min-h-0 overflow-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
