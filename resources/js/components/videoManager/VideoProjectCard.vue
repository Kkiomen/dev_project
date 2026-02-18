<script setup>
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';

const { t } = useI18n();
const router = useRouter();

const props = defineProps({
    project: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle-select', 'delete', 'render', 'download']);

const statusClasses = {
    pending: 'bg-gray-500/20 text-gray-400',
    uploading: 'bg-blue-500/20 text-blue-400',
    transcribing: 'bg-indigo-500/20 text-indigo-400',
    transcribed: 'bg-purple-500/20 text-purple-400',
    editing: 'bg-yellow-500/20 text-yellow-400',
    rendering: 'bg-orange-500/20 text-orange-400',
    completed: 'bg-green-500/20 text-green-400',
    failed: 'bg-red-500/20 text-red-400',
};

const formatDuration = (seconds) => {
    if (!seconds) return '--';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
};

const formatDate = (date) => {
    if (!date) return '--';
    return new Date(date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};

const openProject = () => {
    router.push({ name: 'videoManager.nle', params: { projectId: props.project.id } });
};
</script>

<template>
    <div
        class="bg-gray-900 rounded-xl border transition-colors group relative"
        :class="selected ? 'border-violet-500' : 'border-gray-800 hover:border-gray-700'"
    >
        <!-- Select checkbox -->
        <div class="absolute top-3 left-3 z-10">
            <input
                type="checkbox"
                :checked="selected"
                @change="emit('toggle-select', project.id)"
                class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-violet-600 focus:ring-violet-500 focus:ring-offset-gray-900 cursor-pointer"
            />
        </div>

        <!-- Thumbnail area -->
        <div
            @click="openProject"
            class="h-32 bg-gray-800 rounded-t-xl flex items-center justify-center cursor-pointer relative overflow-hidden"
        >
            <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" />
            </svg>

            <!-- Processing overlay -->
            <div v-if="project.is_processing" class="absolute inset-0 bg-black/40 flex items-center justify-center">
                <svg class="w-6 h-6 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" fill="none" stroke-width="3" />
                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" fill="none" stroke-width="3" stroke-linecap="round" />
                </svg>
            </div>

            <!-- Duration badge -->
            <span v-if="project.duration" class="absolute bottom-2 right-2 px-1.5 py-0.5 bg-black/70 rounded text-xs text-white font-medium">
                {{ formatDuration(project.duration) }}
            </span>

            <!-- Hover actions -->
            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                <button
                    @click.stop="openProject"
                    class="p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white transition"
                    :title="t('videoManager.library.edit')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                </button>
                <button
                    v-if="project.can_export"
                    @click.stop="emit('render', project.id)"
                    class="p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white transition"
                    :title="t('videoManager.library.render')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-2.625 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0 1 18 18.375M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-1.5C18.504 4.5 18 5.004 18 5.625m3.75 0v1.5c0 .621-.504 1.125-1.125 1.125M3.375 4.5c-.621 0-1.125.504-1.125 1.125M3.375 4.5h1.5C5.496 4.5 6 5.004 6 5.625m-2.625 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 8.25 6 7.746 6 7.125v-1.5M4.875 8.25C5.496 8.25 6 8.754 6 9.375v1.5m0-5.25v5.25m0-5.25C6 5.004 6.504 4.5 7.125 4.5h9.75c.621 0 1.125.504 1.125 1.125m1.125 2.625h1.5m-1.5 0A1.125 1.125 0 0 1 18 7.125v-1.5m1.125 2.625c-.621 0-1.125.504-1.125 1.125v1.5m2.625-2.625c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M18 5.625v5.25M7.125 12h9.75m-9.75 0A1.125 1.125 0 0 1 6 10.875M7.125 12C6.504 12 6 12.504 6 13.125m0-2.25C6 11.496 5.496 12 4.875 12M18 10.875c0 .621-.504 1.125-1.125 1.125M18 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m-12 5.25v-5.25m0 5.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125m-12 0v-1.5c0-.621-.504-1.125-1.125-1.125M18 18.375v-5.25m0 5.25v-1.5c0-.621.504-1.125 1.125-1.125M18 13.125v1.5c0 .621.504 1.125 1.125 1.125M18 13.125c0-.621.504-1.125 1.125-1.125M6 13.125v1.5c0 .621-.504 1.125-1.125 1.125M6 13.125C6 12.504 5.496 12 4.875 12m-1.5 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 12 6 12.504 6 13.125M5.625 15.75v2.625m12.75-12.75v2.625m0 7.5v2.625" />
                    </svg>
                </button>
                <button
                    v-if="project.status === 'completed'"
                    @click.stop="emit('download', project.id)"
                    class="p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white transition"
                    :title="t('videoManager.library.download')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </button>
                <button
                    @click.stop="emit('delete', project.id)"
                    class="p-2 rounded-lg bg-white/10 hover:bg-red-500/30 text-white transition"
                    :title="t('videoManager.library.delete')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Info -->
        <div class="p-4">
            <h4 class="text-sm font-medium text-white truncate">{{ project.title }}</h4>
            <div class="mt-2 flex items-center justify-between">
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="statusClasses[project.status] || 'bg-gray-500/20 text-gray-400'"
                >
                    {{ project.status_label }}
                </span>
                <span class="text-xs text-gray-500">{{ formatDate(project.created_at) }}</span>
            </div>
        </div>
    </div>
</template>
