<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useBrandsStore } from '@/stores/brands';
import { useManagerStore } from '@/stores/manager';
import ManagerSidebar from './ManagerSidebar.vue';
import NotificationBell from '@/components/notifications/NotificationBell.vue';

const authStore = useAuthStore();
const brandsStore = useBrandsStore();
const managerStore = useManagerStore();

onMounted(() => {
    authStore.fetchUser();
    brandsStore.fetchBrands();
});
</script>

<template>
    <div class="h-screen flex bg-gray-950 overflow-hidden">
        <ManagerSidebar />

        <div class="flex-1 flex flex-col min-w-0">
            <!-- Mobile top bar -->
            <div class="lg:hidden h-14 flex items-center px-4 bg-gray-900 border-b border-gray-800 shrink-0">
                <button
                    @click="managerStore.mobileMenuOpen = true"
                    class="p-2 -ml-2 rounded-md text-gray-400 hover:text-gray-200 hover:bg-gray-800"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="ml-3 flex items-center">
                    <div class="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                        </svg>
                    </div>
                    <span class="ml-2 text-sm font-semibold text-white">AI Manager</span>
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
