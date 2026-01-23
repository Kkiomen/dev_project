<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import Navigation from './Navigation.vue';

const authStore = useAuthStore();

onMounted(() => {
    authStore.fetchUser();
});
</script>

<template>
    <div class="h-screen flex flex-col bg-gray-100 overflow-hidden">
        <Navigation />

        <main class="flex-1 min-h-0 overflow-auto">
            <slot />
        </main>
    </div>
</template>
