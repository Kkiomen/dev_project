<script setup>
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';

const { t } = useI18n();
const router = useRouter();

defineProps({
    projects: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});

const statusColors = {
    uploading: 'bg-blue-500',
    transcribing: 'bg-indigo-500',
    editing: 'bg-yellow-500',
    rendering: 'bg-orange-500',
};

const openProject = (project) => {
    router.push({ name: 'videoManager.nle', params: { projectId: project.id } });
};
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800">
        <div class="px-5 py-4 border-b border-gray-800">
            <h3 class="text-sm font-semibold text-white">{{ t('videoManager.dashboard.processingQueue') }}</h3>
        </div>
        <div class="divide-y divide-gray-800">
            <!-- Loading skeleton -->
            <template v-if="loading">
                <div v-for="i in 3" :key="i" class="px-5 py-3 flex items-center gap-3">
                    <div class="w-2 h-2 rounded-full bg-gray-700 animate-pulse" />
                    <div class="flex-1">
                        <div class="h-4 w-32 bg-gray-800 rounded animate-pulse" />
                        <div class="mt-1 h-3 w-20 bg-gray-800 rounded animate-pulse" />
                    </div>
                </div>
            </template>

            <!-- Empty -->
            <div v-else-if="projects.length === 0" class="px-5 py-8 text-center">
                <p class="text-sm text-gray-500">{{ t('videoManager.dashboard.noProcessing') }}</p>
            </div>

            <!-- Projects -->
            <div
                v-else
                v-for="project in projects"
                :key="project.id"
                @click="openProject(project)"
                class="px-5 py-3 flex items-center gap-3 hover:bg-gray-800/50 cursor-pointer transition-colors"
            >
                <div class="relative">
                    <div
                        class="w-2 h-2 rounded-full animate-pulse"
                        :class="statusColors[project.status] || 'bg-gray-500'"
                    />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-white truncate">{{ project.title }}</p>
                    <p class="text-xs text-gray-500">{{ project.status_label }}</p>
                </div>
                <svg class="w-4 h-4 text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </div>
        </div>
    </div>
</template>
