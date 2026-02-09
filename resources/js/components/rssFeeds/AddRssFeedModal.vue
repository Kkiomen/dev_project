<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRssFeedsStore } from '@/stores/rssFeeds';
import Modal from '@/components/common/Modal.vue';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'created']);

const { t } = useI18n();
const rssStore = useRssFeedsStore();

const saving = ref(false);
const errorMessage = ref('');
const form = ref({
    url: '',
    name: '',
});

watch(() => props.show, (val) => {
    if (val) {
        form.value = { url: '', name: '' };
        errorMessage.value = '';
    }
});

const handleSubmit = async () => {
    if (!form.value.url.trim()) return;

    saving.value = true;
    errorMessage.value = '';
    try {
        const feed = await rssStore.createFeed(form.value);
        emit('created', feed);
    } catch (error) {
        errorMessage.value = error.response?.data?.message || t('rssFeeds.addFeedError');
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Modal :show="show" max-width="md" @close="$emit('close')">
        <!-- Header -->
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-orange-50">
                    <svg class="w-4.5 h-4.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12.75 19.5v-.75a7.5 7.5 0 0 0-7.5-7.5H4.5m0-6.75h.75c7.87 0 14.25 6.38 14.25 14.25v.75M6 18.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ t('rssFeeds.addFeed') }}
                </h2>
            </div>
            <button
                @click="$emit('close')"
                class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-5">
            <!-- URL -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    {{ t('rssFeeds.feedUrl') }} <span class="text-red-500">*</span>
                </label>
                <input
                    v-model="form.url"
                    type="url"
                    :placeholder="t('rssFeeds.feedUrlPlaceholder')"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    required
                />
            </div>

            <!-- Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    {{ t('rssFeeds.feedName') }}
                    <span class="text-gray-400 text-xs ml-1">({{ t('common.optional') }})</span>
                </label>
                <input
                    v-model="form.name"
                    type="text"
                    :placeholder="t('rssFeeds.feedNamePlaceholder')"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                />
            </div>

            <!-- Error -->
            <div v-if="errorMessage" class="rounded-lg bg-red-50 border border-red-200 p-3">
                <p class="text-sm text-red-700">{{ errorMessage }}</p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-2">
                <Button variant="secondary" @click="$emit('close')">
                    {{ t('common.cancel') }}
                </Button>
                <Button type="submit" :loading="saving" :disabled="!form.url.trim()">
                    {{ t('rssFeeds.addFeed') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>
