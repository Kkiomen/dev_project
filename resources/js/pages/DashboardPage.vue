<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useBasesStore } from '@/stores/bases';
import { useAuthStore } from '@/stores/auth';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const basesStore = useBasesStore();
const authStore = useAuthStore();

const loading = ref(true);
const stats = ref({
    bases: 0,
    tables: 0,
    templates: 0,
    posts: 0,
});

onMounted(async () => {
    try {
        await basesStore.fetchBases();
        stats.value.bases = basesStore.bases.length;

        // Calculate total tables
        stats.value.tables = basesStore.bases.reduce((sum, base) => sum + (base.tables_count || 0), 0);

        // Fetch other stats from API
        try {
            const response = await window.axios.get('/api/v1/dashboard/stats');
            if (response.data) {
                stats.value.templates = response.data.templates || 0;
                stats.value.posts = response.data.posts || 0;
            }
        } catch (e) {
            // Stats endpoint may not exist, use defaults
        }
    } finally {
        loading.value = false;
    }
});

const quickActions = computed(() => [
    {
        title: t('dashboard.quickActions.newDatabase'),
        description: t('dashboard.quickActions.newDatabaseDesc'),
        icon: 'database',
        to: '/data',
        color: 'blue',
    },
    {
        title: t('dashboard.quickActions.templates'),
        description: t('dashboard.quickActions.templatesDesc'),
        icon: 'image',
        to: '/templates',
        color: 'purple',
    },
    {
        title: t('dashboard.quickActions.calendar'),
        description: t('dashboard.quickActions.calendarDesc'),
        icon: 'calendar',
        to: '/calendar',
        color: 'green',
    },
    {
        title: t('dashboard.quickActions.docs'),
        description: t('dashboard.quickActions.docsDesc'),
        icon: 'book',
        to: '/docs',
        color: 'orange',
    },
]);

const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return t('dashboard.greetings.morning');
    if (hour < 18) return t('dashboard.greetings.afternoon');
    return t('dashboard.greetings.evening');
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Welcome Section -->
            <div class="mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">
                    {{ getGreeting() }}, {{ authStore.user?.name?.split(' ')[0] || 'User' }}!
                </h1>
                <p class="mt-1 text-gray-500">{{ t('dashboard.welcomeMessage') }}</p>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-20">
                <LoadingSpinner size="lg" />
            </div>

            <template v-else>
                <!-- Stats Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                    <RouterLink
                        to="/data"
                        class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-md hover:border-blue-200 transition-all group"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">{{ t('dashboard.stats.databases') }}</p>
                                <p class="text-3xl font-bold text-gray-900 mt-1">{{ stats.bases }}</p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                                </svg>
                            </div>
                        </div>
                    </RouterLink>

                    <RouterLink
                        to="/data"
                        class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-md hover:border-indigo-200 transition-all group"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">{{ t('dashboard.stats.tables') }}</p>
                                <p class="text-3xl font-bold text-gray-900 mt-1">{{ stats.tables }}</p>
                            </div>
                            <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                    </RouterLink>

                    <RouterLink
                        to="/templates"
                        class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-md hover:border-purple-200 transition-all group"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">{{ t('dashboard.stats.templates') }}</p>
                                <p class="text-3xl font-bold text-gray-900 mt-1">{{ stats.templates }}</p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                    </RouterLink>

                    <RouterLink
                        to="/calendar"
                        class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-md hover:border-green-200 transition-all group"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">{{ t('dashboard.stats.posts') }}</p>
                                <p class="text-3xl font-bold text-gray-900 mt-1">{{ stats.posts }}</p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center group-hover:bg-green-200 transition-colors">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                    </RouterLink>
                </div>

                <!-- Quick Actions -->
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ t('dashboard.quickActions.title') }}</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <RouterLink
                            v-for="action in quickActions"
                            :key="action.to"
                            :to="action.to"
                            class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md hover:border-gray-300 transition-all group"
                        >
                            <div class="flex items-start gap-4">
                                <div
                                    class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
                                    :class="{
                                        'bg-blue-100': action.color === 'blue',
                                        'bg-purple-100': action.color === 'purple',
                                        'bg-green-100': action.color === 'green',
                                        'bg-orange-100': action.color === 'orange',
                                    }"
                                >
                                    <svg
                                        v-if="action.icon === 'database'"
                                        class="w-5 h-5 text-blue-600"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                                    </svg>
                                    <svg
                                        v-else-if="action.icon === 'image'"
                                        class="w-5 h-5 text-purple-600"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <svg
                                        v-else-if="action.icon === 'calendar'"
                                        class="w-5 h-5 text-green-600"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <svg
                                        v-else-if="action.icon === 'book'"
                                        class="w-5 h-5 text-orange-600"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-medium text-gray-900 group-hover:text-blue-600 transition-colors">
                                        {{ action.title }}
                                    </h3>
                                    <p class="text-sm text-gray-500 mt-0.5">{{ action.description }}</p>
                                </div>
                            </div>
                        </RouterLink>
                    </div>
                </div>

                <!-- Recent Databases -->
                <div v-if="basesStore.bases.length > 0">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('dashboard.recentDatabases') }}</h2>
                        <RouterLink to="/data" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                            {{ t('dashboard.viewAll') }}
                        </RouterLink>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <RouterLink
                            v-for="base in basesStore.bases.slice(0, 6)"
                            :key="base.id"
                            :to="{ name: 'base', params: { baseId: base.id } }"
                            class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md hover:border-gray-300 transition-all group"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-lg flex items-center justify-center text-lg shrink-0"
                                    :style="{ backgroundColor: base.color || '#3B82F6' }"
                                >
                                    {{ base.icon || '🗃️' }}
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-medium text-gray-900 truncate group-hover:text-blue-600 transition-colors">
                                        {{ base.name }}
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        {{ base.tables_count || 0 }} {{ t('data.tables') }}
                                    </p>
                                </div>
                            </div>
                        </RouterLink>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
