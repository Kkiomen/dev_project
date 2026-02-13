<script setup>
import { RouterView, useRoute } from 'vue-router';
import VideoManagerLayout from '@/components/videoManager/VideoManagerLayout.vue';
import Toast from '@/components/common/Toast.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';

const route = useRoute();
</script>

<template>
    <VideoManagerLayout>
        <RouterView :key="route.fullPath" />
    </VideoManagerLayout>
    <Toast />
    <ConfirmModal />
</template>
