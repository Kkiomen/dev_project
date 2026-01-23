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
    <div class="min-h-screen bg-gray-100">
        <Navigation />

        <main>
            <slot />
        </main>
    </div>
</template>
