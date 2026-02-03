<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useActiveTasksStore } from '@/stores/activeTasks';
import { useAuthStore } from '@/stores/auth';

const { t } = useI18n();
const router = useRouter();
const activeTasksStore = useActiveTasksStore();
const authStore = useAuthStore();

const isOpen = ref(false);
const dropdownRef = ref(null);

const taskTypeLabels = computed(() => ({
    content_generation: t('tasks.types.contentGeneration'),
    post_publishing: t('tasks.types.postPublishing'),
    image_generation: t('tasks.types.imageGeneration'),
}));

const getTaskLabel = (task) => {
    return taskTypeLabels.value[task.task_type] || task.task_type;
};

const getTaskDescription = (task) => {
    const data = task.data || {};

    switch (task.task_type) {
        case 'content_generation':
            return t('tasks.descriptions.contentGeneration', {
                pillar: data.pillar || '',
                brand: data.brand_name || '',
            });
        default:
            return '';
    }
};

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
};

const handleTaskClick = (task) => {
    if (task.completed && task.success && task.result_data?.post_id) {
        router.push({ name: 'post.edit', params: { postId: task.result_data.post_id } });
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);

    if (authStore.user?.id) {
        activeTasksStore.setupWebSocket(authStore.user.id);
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);

    if (authStore.user?.id) {
        activeTasksStore.cleanupWebSocket(authStore.user.id);
    }
});
</script>

<template>
    <div v-if="activeTasksStore.hasActiveTasks" ref="dropdownRef" class="relative">
        <!-- Indicator Button -->
        <button
            @click="toggleDropdown"
            class="relative flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-full transition-colors"
        >
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                />
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
            </svg>
            <span>{{ t('tasks.inProgress', { count: activeTasksStore.activeCount }) }}</span>
        </button>

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-80 lg:right-auto lg:left-0 lg:mt-0 lg:mb-2 lg:bottom-full bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 z-50"
            >
                <!-- Header -->
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">
                        {{ t('tasks.title') }}
                    </h3>
                </div>

                <!-- Tasks List -->
                <div class="max-h-64 overflow-y-auto">
                    <div
                        v-for="task in activeTasksStore.tasks"
                        :key="task.task_id"
                        @click="handleTaskClick(task)"
                        class="flex items-start gap-3 px-4 py-3 border-b border-gray-50 last:border-0"
                        :class="{
                            'cursor-pointer hover:bg-gray-50': task.completed && task.success && task.result_data?.post_id,
                        }"
                    >
                        <!-- Status Icon -->
                        <div class="flex-shrink-0 mt-0.5">
                            <!-- Spinning -->
                            <svg
                                v-if="!task.completed"
                                class="w-5 h-5 text-blue-500 animate-spin"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                />
                            </svg>
                            <!-- Success -->
                            <svg
                                v-else-if="task.success"
                                class="w-5 h-5 text-green-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <!-- Failed -->
                            <svg
                                v-else
                                class="w-5 h-5 text-red-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900">
                                {{ getTaskLabel(task) }}
                            </p>
                            <p class="text-xs text-gray-500 truncate">
                                {{ getTaskDescription(task) }}
                            </p>
                            <p v-if="task.error" class="text-xs text-red-500 mt-1">
                                {{ task.error }}
                            </p>
                            <p
                                v-if="task.completed && task.success && task.result_data?.post_title"
                                class="text-xs text-green-600 mt-1"
                            >
                                {{ t('tasks.completed') }}: {{ task.result_data.post_title }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
