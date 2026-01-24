<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PostStatusBadge from '@/components/posts/PostStatusBadge.vue';

const props = defineProps({
    post: {
        type: Object,
        required: true,
    },
    selected: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggle-select', 'approve', 'reject', 'edit']);

const { t } = useI18n();

const formattedDate = computed(() => {
    if (!props.post.scheduled_at) return null;
    return new Date(props.post.scheduled_at).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
});

const formattedTime = computed(() => {
    if (!props.post.scheduled_at) return null;
    return new Date(props.post.scheduled_at).toLocaleTimeString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    });
});

const truncatedCaption = computed(() => {
    const caption = props.post.main_caption || '';
    return caption.length > 120 ? caption.substring(0, 120) + '...' : caption;
});

const platformColors = {
    facebook: 'bg-blue-600',
    instagram: 'bg-gradient-to-r from-purple-500 to-pink-500',
    youtube: 'bg-red-600',
};
</script>

<template>
    <div
        class="bg-white rounded-xl border shadow-sm overflow-hidden transition-all"
        :class="selected ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200 hover:shadow-md'"
    >
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <!-- Checkbox -->
                <button
                    @click="emit('toggle-select')"
                    class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
                    :class="selected ? 'bg-blue-500 border-blue-500' : 'border-gray-300 hover:border-gray-400'"
                >
                    <svg v-if="selected" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </button>

                <!-- Date & Time -->
                <div v-if="formattedDate" class="text-sm">
                    <span class="font-medium text-gray-900">{{ formattedDate }}</span>
                    <span v-if="formattedTime" class="text-gray-500 ml-1">{{ formattedTime }}</span>
                </div>
            </div>

            <!-- Status Badge -->
            <PostStatusBadge :status="post.status" />
        </div>

        <!-- Content -->
        <div class="p-4">
            <!-- Media Preview -->
            <div
                v-if="post.first_media_url"
                class="aspect-video rounded-lg overflow-hidden mb-3 bg-gray-100"
            >
                <img
                    :src="post.first_media_url"
                    :alt="post.title"
                    class="w-full h-full object-cover"
                />
            </div>

            <!-- Title -->
            <h3 v-if="post.title" class="font-medium text-gray-900 mb-2">
                {{ post.title }}
            </h3>

            <!-- Caption -->
            <p class="text-sm text-gray-600 mb-3">
                {{ truncatedCaption || t('posts.noCaption') }}
            </p>

            <!-- Platforms -->
            <div class="flex items-center space-x-2 mb-4">
                <template v-for="platform in post.enabled_platforms" :key="platform">
                    <span
                        class="inline-flex items-center px-2 py-1 text-xs font-medium text-white rounded"
                        :class="platformColors[platform]"
                    >
                        {{ platform.charAt(0).toUpperCase() + platform.slice(1) }}
                    </span>
                </template>
            </div>

            <!-- Brand -->
            <div v-if="post.brand" class="text-xs text-gray-500 mb-4">
                {{ post.brand.name }}
            </div>
        </div>

        <!-- Actions -->
        <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <button
                @click="emit('edit')"
                class="text-sm text-gray-600 hover:text-gray-900"
            >
                {{ t('common.edit') }}
            </button>
            <div class="flex items-center space-x-2">
                <button
                    @click="emit('reject')"
                    class="px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                >
                    {{ t('approval.requestChanges') }}
                </button>
                <button
                    @click="emit('approve')"
                    class="px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors"
                >
                    {{ t('approval.approve') }}
                </button>
            </div>
        </div>
    </div>
</template>
