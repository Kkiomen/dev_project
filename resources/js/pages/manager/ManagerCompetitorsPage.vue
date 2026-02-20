<script setup>
import { useI18n } from 'vue-i18n';
import CiDashboard from '@/components/ci/CiDashboard.vue';

const { t } = useI18n();
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">{{ t('ci.title') }}</h1>
            <p class="mt-1 text-sm text-gray-400">{{ t('ci.description') }}</p>
        </div>

        <!-- Dashboard -->
        <CiDashboard />
    </div>
</template>
