<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import PostStatusBadge from './PostStatusBadge.vue';
import RichTextEditor from './RichTextEditor.vue';

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
    post: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();
const settingsStore = useSettingsStore();

const formData = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const updateField = (field, value) => {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
};

const platforms = [
    { value: 'facebook', label: 'Facebook', color: 'blue', icon: 'F' },
    { value: 'instagram', label: 'Instagram', color: 'pink', icon: 'I' },
    { value: 'youtube', label: 'YouTube', color: 'red', icon: 'Y' },
];

const togglePlatform = (platform) => {
    const current = formData.value.platforms || [];
    const newPlatforms = current.includes(platform)
        ? current.filter(p => p !== platform)
        : [...current, platform];
    updateField('platforms', newPlatforms);
};

const formatDateForInput = (date) => {
    if (!date) return '';
    const d = new Date(date);

    // Format according to user's time settings
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const handleDateChange = (event) => {
    const value = event.target.value;
    updateField('scheduled_at', value ? new Date(value).toISOString() : null);
};

// Helper to format schedule time display
const formattedSchedule = computed(() => {
    if (!formData.value.scheduled_at) return null;

    const date = new Date(formData.value.scheduled_at);
    const dateStr = date.toLocaleDateString();
    const timeStr = settingsStore.formatTime(date);

    return `${dateStr} ${timeStr}`;
});

// Quick schedule options
const quickScheduleOptions = [
    { label: t('posts.schedule.today'), hours: 0 },
    { label: t('posts.schedule.tomorrow'), hours: 24 },
    { label: t('posts.schedule.nextWeek'), hours: 24 * 7 },
];

const setQuickSchedule = (hoursFromNow) => {
    const date = new Date();
    date.setHours(date.getHours() + hoursFromNow);
    // Round to nearest hour
    date.setMinutes(0, 0, 0);
    updateField('scheduled_at', date.toISOString());
};

const clearSchedule = () => {
    updateField('scheduled_at', null);
};
</script>

<template>
    <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-6">
        <!-- Status badge (if editing) -->
        <div v-if="post" class="flex items-center justify-between pb-4 border-b border-gray-100">
            <PostStatusBadge :status="post.status" />
            <div class="text-sm text-gray-500">
                <span>{{ t('common.created') }}: </span>
                <span class="font-medium">{{ new Date(post.created_at).toLocaleDateString() }}</span>
            </div>
        </div>

        <!-- Title -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                {{ t('posts.title') }}
                <span class="text-red-500">*</span>
            </label>
            <input
                :value="formData.title"
                @input="updateField('title', $event.target.value)"
                type="text"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg"
                :placeholder="t('posts.titlePlaceholder')"
            />
        </div>

        <!-- Main Caption -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                {{ t('posts.mainCaption') }}
                <span class="text-red-500">*</span>
            </label>
            <RichTextEditor
                :model-value="formData.main_caption || ''"
                @update:model-value="updateField('main_caption', $event)"
                :placeholder="t('posts.mainCaptionPlaceholder')"
                :rows="8"
            />
            <p class="mt-2 text-xs text-gray-500">
                {{ t('posts.captionHint') }}
            </p>
        </div>

        <!-- Platforms -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-3">
                {{ t('posts.platforms.title') }}
            </label>
            <div class="grid grid-cols-3 gap-3">
                <button
                    v-for="platform in platforms"
                    :key="platform.value"
                    @click="togglePlatform(platform.value)"
                    class="relative px-4 py-4 rounded-lg border-2 transition-all flex flex-col items-center space-y-2"
                    :class="formData.platforms?.includes(platform.value)
                        ? {
                            'border-blue-500 bg-blue-50': platform.color === 'blue',
                            'border-pink-500 bg-pink-50': platform.color === 'pink',
                            'border-red-500 bg-red-50': platform.color === 'red',
                        }
                        : 'border-gray-200 hover:border-gray-300 bg-white'"
                >
                    <span
                        class="w-10 h-10 rounded-full flex items-center justify-center text-white text-lg font-bold"
                        :class="{
                            'bg-blue-600': platform.value === 'facebook',
                            'bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400': platform.value === 'instagram',
                            'bg-red-600': platform.value === 'youtube',
                        }"
                    >
                        {{ platform.icon }}
                    </span>
                    <span
                        class="text-sm font-medium"
                        :class="formData.platforms?.includes(platform.value)
                            ? {
                                'text-blue-700': platform.color === 'blue',
                                'text-pink-700': platform.color === 'pink',
                                'text-red-700': platform.color === 'red',
                            }
                            : 'text-gray-600'"
                    >
                        {{ platform.label }}
                    </span>

                    <!-- Check mark -->
                    <div
                        v-if="formData.platforms?.includes(platform.value)"
                        class="absolute top-2 right-2"
                    >
                        <svg
                            class="w-5 h-5"
                            :class="{
                                'text-blue-600': platform.color === 'blue',
                                'text-pink-600': platform.color === 'pink',
                                'text-red-600': platform.color === 'red',
                            }"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                </button>
            </div>
        </div>

        <!-- Schedule -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                {{ t('posts.scheduledAt') }}
            </label>

            <div class="flex items-center space-x-3">
                <div class="relative flex-1">
                    <input
                        :value="formatDateForInput(formData.scheduled_at)"
                        @input="handleDateChange"
                        type="datetime-local"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <button
                    v-if="formData.scheduled_at"
                    @click="clearSchedule"
                    class="p-3 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                    :title="t('posts.schedule.clear')"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Quick schedule buttons -->
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="option in quickScheduleOptions"
                    :key="option.hours"
                    @click="setQuickSchedule(option.hours)"
                    class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
                >
                    {{ option.label }}
                </button>
            </div>

            <!-- Formatted schedule display -->
            <div v-if="formData.scheduled_at" class="mt-3 p-3 bg-blue-50 rounded-lg">
                <div class="flex items-center space-x-2 text-blue-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ formattedSchedule }}</span>
                </div>
            </div>
            <p v-else class="mt-2 text-sm text-gray-500">
                {{ t('posts.notScheduled') }}
            </p>
        </div>
    </div>
</template>
