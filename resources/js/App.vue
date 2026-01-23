<script setup>
import { RouterView } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import Toast from '@/components/common/Toast.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';
</script>

<template>
    <AppLayout>
        <RouterView />
    </AppLayout>
    <Toast />
    <ConfirmModal />
</template>
