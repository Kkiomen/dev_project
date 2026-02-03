<script setup>
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import Modal from '@/components/common/Modal.vue';

const { t } = useI18n();
const router = useRouter();

const props = defineProps({
    show: { type: Boolean, default: false },
    post: { type: Object, default: null },
});

const emit = defineEmits(['close']);

function openEditor() {
    if (props.post) {
        router.push({ name: 'post.edit', params: { postId: props.post.id } });
        emit('close');
    }
}

function statusColor(status) {
    const colors = {
        draft: 'bg-gray-100 text-gray-700',
        pending_approval: 'bg-yellow-100 text-yellow-700',
        approved: 'bg-green-100 text-green-700',
        scheduled: 'bg-blue-100 text-blue-700',
        published: 'bg-purple-100 text-purple-700',
        failed: 'bg-red-100 text-red-700',
    };
    return colors[status] || 'bg-gray-100 text-gray-700';
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div v-if="post" class="space-y-5">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ t('postAutomation.preview.title') }}
                    </h2>
                    <span
                        class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="statusColor(post.status)"
                    >
                        {{ post.status_label }}
                    </span>
                </div>
            </div>

            <!-- Title -->
            <div v-if="post.title">
                <h3 class="text-sm font-medium text-gray-500 mb-1">
                    {{ t('postAutomation.table.topic') }}
                </h3>
                <p class="text-gray-900">{{ post.title }}</p>
            </div>

            <!-- Content -->
            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-1">
                    {{ t('postAutomation.table.content') }}
                </h3>
                <div
                    v-if="post.main_caption"
                    class="text-gray-900 whitespace-pre-wrap bg-gray-50 rounded-lg p-3 text-sm max-h-60 overflow-y-auto"
                >
                    {{ post.main_caption }}
                </div>
                <p v-else class="text-gray-400 italic text-sm">
                    {{ t('postAutomation.preview.noContent') }}
                </p>
            </div>

            <!-- Image Description -->
            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-1">
                    {{ t('postAutomation.table.imageDescription') }}
                </h3>
                <div
                    v-if="post.image_prompt"
                    class="text-gray-900 whitespace-pre-wrap bg-gray-50 rounded-lg p-3 text-sm"
                >
                    {{ post.image_prompt }}
                </div>
                <p v-else class="text-gray-400 italic text-sm">
                    {{ t('postAutomation.preview.noImagePrompt') }}
                </p>
            </div>

            <!-- Scheduled -->
            <div v-if="post.scheduled_at">
                <h3 class="text-sm font-medium text-gray-500 mb-1">
                    {{ t('postAutomation.table.scheduledAt') }}
                </h3>
                <p class="text-gray-900 text-sm">
                    {{ new Date(post.scheduled_at).toLocaleString() }}
                </p>
            </div>

            <!-- Media -->
            <div v-if="post.media && post.media.length">
                <h3 class="text-sm font-medium text-gray-500 mb-2">
                    {{ t('posts.media.title') }}
                </h3>
                <div class="flex gap-2 overflow-x-auto">
                    <img
                        v-for="media in post.media"
                        :key="media.id"
                        :src="media.url"
                        :alt="media.filename"
                        class="h-20 w-20 rounded-lg object-cover flex-shrink-0"
                    />
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-4 border-t">
                <button
                    @click="emit('close')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    {{ t('common.close') }}
                </button>
                <button
                    @click="openEditor"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                >
                    {{ t('postAutomation.preview.openEditor') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
