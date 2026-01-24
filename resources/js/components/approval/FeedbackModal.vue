<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/common/Button.vue';

const emit = defineEmits(['submit', 'close']);

const { t } = useI18n();

const notes = ref('');
const submitting = ref(false);

const handleSubmit = async () => {
    submitting.value = true;
    try {
        emit('submit', notes.value.trim() || null);
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <teleport to="body">
        <div
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="emit('close')"
        >
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                <form @submit.prevent="handleSubmit">
                    <div class="p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-2">
                            {{ t('approval.requestChangesTitle') }}
                        </h2>
                        <p class="text-sm text-gray-600 mb-4">
                            {{ t('approval.requestChangesDescription') }}
                        </p>

                        <textarea
                            v-model="notes"
                            rows="4"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                            :placeholder="t('approval.feedbackPlaceholder')"
                        ></textarea>
                    </div>

                    <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3 rounded-b-lg">
                        <Button type="button" variant="secondary" @click="emit('close')">
                            {{ t('common.cancel') }}
                        </Button>
                        <Button type="submit" :loading="submitting">
                            {{ t('approval.submitFeedback') }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </teleport>
</template>
