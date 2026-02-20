<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AutomationPostRow from './AutomationPostRow.vue';

const props = defineProps({
    posts: { type: Array, required: true },
    selectedIds: { type: Array, required: true },
    generatingText: { type: Object, default: () => ({}) },
    generatingImageDescription: { type: Object, default: () => ({}) },
    generatingImage: { type: Object, default: () => ({}) },
    webhookPublishing: { type: Object, default: () => ({}) },
    platformColors: { type: Object, required: true },
});

const emit = defineEmits([
    'toggle-select',
    'toggle-select-all',
    'generate-text',
    'generate-image-description',
    'generate-image',
    'approve',
    'publish',
    'preview',
    'edit',
    'update-field',
    'toggle-platform',
    'add-tag',
    'remove-tag',
    'upload-media',
    'delete-media',
    'reschedule',
    'process-next',
]);

const { t } = useI18n();

const expandedId = ref(null);

const allSelected = computed(() => props.posts.length > 0 && props.selectedIds.length === props.posts.length);
const someSelected = computed(() => props.selectedIds.length > 0 && props.selectedIds.length < props.posts.length);

function toggleExpand(postId) {
    expandedId.value = expandedId.value === postId ? null : postId;
}
</script>

<template>
    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full border border-gray-200 rounded-lg overflow-hidden">
            <thead class="bg-gray-50">
                <tr>
                    <th class="w-10 px-3 py-3">
                        <input
                            type="checkbox"
                            :checked="allSelected"
                            :indeterminate="someSelected"
                            @change="emit('toggle-select-all')"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ t('postAutomation.table.topic') }}
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                        {{ t('postAutomation.table.status') }}
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                        {{ t('postAutomation.table.pipeline') }}
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                        {{ t('postAutomation.table.platforms') }}
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-36">
                        {{ t('postAutomation.table.scheduledAt') }}
                    </th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-16">
                        {{ t('postAutomation.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <AutomationPostRow
                    v-for="post in posts"
                    :key="post.id"
                    :post="post"
                    :selected="selectedIds.includes(post.id)"
                    :expanded="expandedId === post.id"
                    :generating-text="!!generatingText[post.id]"
                    :generating-image-description="!!generatingImageDescription[post.id]"
                    :generating-image="!!generatingImage[post.id]"
                    :publishing="!!webhookPublishing[post.id]"
                    :platform-colors="platformColors"
                    @toggle-select="emit('toggle-select', post.id)"
                    @toggle-expand="toggleExpand(post.id)"
                    @generate-text="emit('generate-text', post.id)"
                    @generate-image-description="emit('generate-image-description', post.id)"
                    @generate-image="emit('generate-image', post.id)"
                    @approve="emit('approve', post.id)"
                    @publish="emit('publish', post.id)"
                    @preview="emit('preview', post)"
                    @edit="emit('edit', post.id)"
                    @update-field="emit('update-field', $event)"
                    @toggle-platform="emit('toggle-platform', $event)"
                    @add-tag="emit('add-tag', $event)"
                    @remove-tag="emit('remove-tag', $event)"
                    @upload-media="emit('upload-media', $event)"
                    @delete-media="emit('delete-media', $event)"
                    @reschedule="emit('reschedule', $event)"
                    @process-next="emit('process-next', post.id)"
                />
            </tbody>
        </table>
    </div>
</template>
