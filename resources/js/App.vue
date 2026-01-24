<script setup>
import { RouterView, useRoute } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import Toast from '@/components/common/Toast.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';

const route = useRoute();
</script>

<template>
    <AppLayout>
        <RouterView :key="route.fullPath" />
    </AppLayout>
    <Toast />
    <ConfirmModal />
</template>
