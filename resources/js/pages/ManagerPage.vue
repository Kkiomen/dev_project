<script setup>
import { RouterView, useRoute } from 'vue-router';
import ManagerLayout from '@/components/manager/ManagerLayout.vue';
import Toast from '@/components/common/Toast.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';

const route = useRoute();
</script>

<template>
    <ManagerLayout>
        <RouterView :key="route.fullPath" />
    </ManagerLayout>
    <Toast />
    <ConfirmModal />
</template>
