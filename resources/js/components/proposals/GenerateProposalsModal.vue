<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal from '@/components/common/Modal.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    panelLanguage: { type: String, default: 'en' },
    brandLanguage: { type: String, default: 'en' },
});

const emit = defineEmits(['close', 'generate']);
const { t } = useI18n();

const days = ref(7);
const languageSource = ref('panel');

const selectedLanguage = computed(() => {
    return languageSource.value === 'panel' ? props.panelLanguage : props.brandLanguage;
});

const panelLanguageLabel = computed(() => {
    const code = props.panelLanguage;
    const name = code === 'pl' ? 'Polski' : 'English';
    return `${name} (${t('postAutomation.proposals.generate.languagePanel')})`;
});

const brandLanguageLabel = computed(() => {
    const code = props.brandLanguage;
    const name = code === 'pl' ? 'Polski' : 'English';
    return `${name} (${t('postAutomation.proposals.generate.languageBrand')})`;
});

function handleGenerate() {
    emit('generate', { days: days.value, language: selectedLanguage.value });
}

function handleClose() {
    if (!props.loading) {
        emit('close');
    }
}
</script>

<template>
    <Modal :show="show" max-width="sm" @close="handleClose">
        <div class="space-y-5">
            <!-- Header -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ t('postAutomation.proposals.generate.title') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('postAutomation.proposals.generate.description') }}
                </p>
            </div>

            <!-- Loading overlay -->
            <div v-if="loading" class="flex flex-col items-center justify-center py-8">
                <LoadingSpinner size="lg" />
                <p class="mt-3 text-sm text-gray-500">
                    {{ t('postAutomation.proposals.generate.generating') }}
                </p>
            </div>

            <!-- Form -->
            <template v-else>
                <!-- Days input -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('postAutomation.proposals.generate.daysLabel') }}
                    </label>
                    <input
                        v-model.number="days"
                        type="number"
                        min="3"
                        max="30"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                    />
                    <p class="mt-1 text-xs text-gray-400">
                        {{ t('postAutomation.proposals.generate.daysHint', { days }) }}
                    </p>
                </div>

                <!-- Language switch -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ t('postAutomation.proposals.generate.languageLabel') }}
                    </label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="languageSource"
                                type="radio"
                                value="panel"
                                class="text-purple-600 focus:ring-purple-500"
                            />
                            <span class="text-sm text-gray-700">{{ panelLanguageLabel }}</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="languageSource"
                                type="radio"
                                value="brand"
                                class="text-purple-600 focus:ring-purple-500"
                            />
                            <span class="text-sm text-gray-700">{{ brandLanguageLabel }}</span>
                        </label>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        @click="handleClose"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                    >
                        {{ t('postAutomation.proposals.form.cancel') }}
                    </button>
                    <button
                        @click="handleGenerate"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                        {{ t('postAutomation.proposals.generate.generateButton') }}
                    </button>
                </div>
            </template>
        </div>
    </Modal>
</template>
