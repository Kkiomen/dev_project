<script setup>
import { RouterView, useRoute } from 'vue-router';
import { computed } from 'vue';
import AppLayout from '@/components/layout/AppLayout.vue';
import Toast from '@/components/common/Toast.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';

const route = useRoute();

// Hide layout for certain routes (e.g., render-preview)
const hideLayout = computed(() => route.meta?.hideLayout === true);
</script>

<template>
    <!-- Routes with hideLayout meta don't get the AppLayout wrapper -->
    <template v-if="hideLayout">
        <RouterView :key="route.fullPath" />
    </template>
    <template v-else>
        <AppLayout>
            <RouterView :key="route.fullPath" />
        </AppLayout>
        <Toast />
        <ConfirmModal />
    </template>
</template>
