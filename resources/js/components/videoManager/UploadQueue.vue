<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    queue: { type: Array, default: () => [] },
});

const emit = defineEmits(['remove', 'update-title', 'update-language', 'update-style']);

const formatSize = (bytes) => {
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const statusIcons = {
    pending: 'text-gray-400',
    uploading: 'text-violet-400 animate-spin',
    completed: 'text-green-400',
    failed: 'text-red-400',
};
</script>

<template>
    <div v-if="queue.length > 0" class="space-y-2">
        <div
            v-for="item in queue"
            :key="item.id"
            class="bg-gray-900 border border-gray-800 rounded-lg p-4"
        >
            <div class="flex items-center gap-3">
                <!-- Status icon -->
                <div class="shrink-0">
                    <svg v-if="item.status === 'uploading'" class="w-5 h-5 text-violet-400 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" fill="none" stroke-width="3" />
                        <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" fill="none" stroke-width="3" stroke-linecap="round" />
                    </svg>
                    <svg v-else-if="item.status === 'completed'" class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <svg v-else-if="item.status === 'failed'" class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <svg v-else class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>

                <!-- File info -->
                <div class="flex-1 min-w-0">
                    <input
                        :value="item.title"
                        @input="emit('update-title', item.id, $event.target.value)"
                        :disabled="item.status !== 'pending'"
                        class="w-full bg-transparent text-sm text-white border-none focus:outline-none focus:ring-0 p-0 disabled:opacity-50"
                    />
                    <div class="flex items-center gap-3 mt-1">
                        <span class="text-xs text-gray-500">{{ formatSize(item.size) }}</span>
                        <span class="text-xs text-gray-500">{{ item.name }}</span>
                    </div>
                </div>

                <!-- Remove button -->
                <button
                    v-if="item.status !== 'uploading'"
                    @click="emit('remove', item.id)"
                    class="p-1 rounded text-gray-500 hover:text-red-400 transition shrink-0"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Progress bar -->
            <div v-if="item.status === 'uploading'" class="mt-3">
                <div class="w-full bg-gray-800 rounded-full h-1.5">
                    <div
                        class="bg-violet-500 h-1.5 rounded-full transition-all duration-300"
                        :style="{ width: `${item.progress}%` }"
                    />
                </div>
                <p class="mt-1 text-xs text-gray-500 text-right">{{ item.progress }}%</p>
            </div>

            <!-- Error -->
            <p v-if="item.error" class="mt-2 text-xs text-red-400">{{ item.error }}</p>

            <!-- Per-file settings (expandable for pending items) -->
            <div v-if="item.status === 'pending'" class="mt-3 grid grid-cols-2 gap-2">
                <select
                    :value="item.language || ''"
                    @change="emit('update-language', item.id, $event.target.value || null)"
                    class="px-2 py-1.5 bg-gray-800 border border-gray-700 rounded text-xs text-white focus:outline-none focus:border-violet-500"
                >
                    <option value="">{{ t('videoManager.upload.autoDetect') }}</option>
                    <option value="pl">Polski</option>
                    <option value="en">English</option>
                    <option value="de">Deutsch</option>
                    <option value="es">Espanol</option>
                    <option value="fr">Francais</option>
                </select>
                <select
                    :value="item.captionStyle"
                    @change="emit('update-style', item.id, $event.target.value)"
                    class="px-2 py-1.5 bg-gray-800 border border-gray-700 rounded text-xs text-white focus:outline-none focus:border-violet-500"
                >
                    <option value="clean">Clean</option>
                    <option value="hormozi">Hormozi</option>
                    <option value="mrbeast">MrBeast</option>
                    <option value="bold">Bold</option>
                    <option value="neon">Neon</option>
                </select>
            </div>
        </div>
    </div>
</template>
