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
    return new Date(date).toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const openProject = () => {
    router.push({ name: 'videoManager.nle', params: { projectId: props.project.id } });
};
</script>

<template>
    <tr class="hover:bg-gray-800/50 transition-colors group">
        <!-- Checkbox -->
        <td class="pl-4 pr-2 py-3 w-10">
            <input
                type="checkbox"
                :checked="selected"
                @change="emit('toggle-select', project.id)"
                class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-violet-600 focus:ring-violet-500 focus:ring-offset-gray-900 cursor-pointer"
            />
        </td>

        <!-- Title -->
        <td class="px-3 py-3">
            <button @click="openProject" class="text-sm text-white hover:text-violet-400 transition-colors truncate max-w-xs block text-left">
                {{ project.title }}
            </button>
        </td>

        <!-- Status -->
        <td class="px-3 py-3">
            <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                :class="statusClasses[project.status] || 'bg-gray-500/20 text-gray-400'"
            >
                <span
                    v-if="project.is_processing"
                    class="w-1.5 h-1.5 rounded-full bg-current animate-pulse mr-1.5"
                />
                {{ project.status_label }}
            </span>
        </td>

        <!-- Duration -->
        <td class="px-3 py-3 text-sm text-gray-400">
            {{ formatDuration(project.duration) }}
        </td>

        <!-- Style -->
        <td class="px-3 py-3 text-sm text-gray-400 capitalize">
            {{ project.caption_style || '--' }}
        </td>

        <!-- Created -->
        <td class="px-3 py-3 text-sm text-gray-500">
            {{ formatDate(project.created_at) }}
        </td>

        <!-- Actions -->
        <td class="px-3 py-3 text-right">
            <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button
                    @click="openProject"
                    class="p-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-700 transition"
                    :title="t('videoManager.library.edit')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                </button>
                <button
                    v-if="project.can_export"
                    @click="emit('render', project.id)"
                    class="p-1.5 rounded text-gray-400 hover:text-violet-400 hover:bg-gray-700 transition"
                    :title="t('videoManager.library.render')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                    </svg>
                </button>
                <button
                    v-if="project.status === 'completed'"
                    @click="emit('download', project.id)"
                    class="p-1.5 rounded text-gray-400 hover:text-green-400 hover:bg-gray-700 transition"
                    :title="t('videoManager.library.download')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </button>
                <button
                    @click="emit('delete', project.id)"
                    class="p-1.5 rounded text-gray-400 hover:text-red-400 hover:bg-gray-700 transition"
                    :title="t('videoManager.library.delete')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </button>
            </div>
        </td>
    </tr>
</template>
