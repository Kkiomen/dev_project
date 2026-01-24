<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePostsStore } from '@/stores/posts';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    platform: {
        type: String,
        required: true,
        validator: (v) => ['facebook', 'instagram', 'youtube'].includes(v),
    },
});

const emit = defineEmits(['close', 'generated']);

const { t, locale } = useI18n();
const postsStore = usePostsStore();

const generating = ref(false);
const error = ref(null);

const config = ref({
    topic: '',
    tone: 'casual',
    length: 'medium',
    language: locale.value,
    customPrompt: '',
});

const platformColors = {
    facebook: { bg: 'from-blue-600 to-blue-700', light: 'blue' },
    instagram: { bg: 'from-purple-600 via-pink-500 to-orange-400', light: 'pink' },
    youtube: { bg: 'from-red-600 to-red-700', light: 'red' },
};

const platformLabels = {
    facebook: 'Facebook',
    instagram: 'Instagram',
    youtube: 'YouTube',
};

const tones = [
    { value: 'professional', label: computed(() => t('posts.ai.tones.professional')) },
    { value: 'casual', label: computed(() => t('posts.ai.tones.casual')) },
    { value: 'playful', label: computed(() => t('posts.ai.tones.playful')) },
    { value: 'inspirational', label: computed(() => t('posts.ai.tones.inspirational')) },
];

const lengths = [
    { value: 'short', label: computed(() => t('posts.ai.lengths.short')) },
    { value: 'medium', label: computed(() => t('posts.ai.lengths.medium')) },
    { value: 'long', label: computed(() => t('posts.ai.lengths.long')) },
];

const canGenerate = computed(() => config.value.topic.trim().length > 0);

const generate = async () => {
    if (!canGenerate.value) return;

    generating.value = true;
    error.value = null;

    // Capture values before async call
    const payload = {
        topic: config.value.topic,
        tone: config.value.tone,
        length: config.value.length,
        language: config.value.language,
        customPrompt: config.value.customPrompt || null,
        platform: props.platform,
    };

    try {
        const result = await postsStore.generateWithAi(payload);
        emit('generated', result);
        emit('close');
    } catch (e) {
        error.value = e.response?.data?.error || e.message;
    } finally {
        generating.value = false;
    }
};

const handleBackdropClick = (e) => {
    if (e.target === e.currentTarget) {
        emit('close');
    }
};
</script>

<template>
    <div
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        @click="handleBackdropClick"
    >
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div
                        class="w-10 h-10 rounded-lg flex items-center justify-center bg-gradient-to-br"
                        :class="platformColors[platform].bg"
                    >
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ t('posts.ai.generate') }}
                        </h2>
                        <p class="text-sm text-gray-500">{{ platformLabels[platform] }}</p>
                    </div>
                </div>
                <button
                    @click="$emit('close')"
                    class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6 space-y-5">
                <!-- Error message -->
                <div v-if="error" class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-600">{{ error }}</p>
                </div>

                <!-- Topic -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ t('posts.ai.topic') }}
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="config.topic"
                        type="text"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:border-transparent"
                        :class="`focus:ring-${platformColors[platform].light}-500`"
                        :placeholder="t('posts.ai.topicPlaceholder')"
                    />
                </div>

                <!-- Tone -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ t('posts.ai.tone') }}
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="tone in tones"
                            :key="tone.value"
                            @click="config.tone = tone.value"
                            class="px-4 py-2.5 text-sm font-medium rounded-lg border-2 transition-all"
                            :class="config.tone === tone.value
                                ? {
                                    'border-blue-500 bg-blue-50 text-blue-700': platform === 'facebook',
                                    'border-pink-500 bg-pink-50 text-pink-700': platform === 'instagram',
                                    'border-red-500 bg-red-50 text-red-700': platform === 'youtube',
                                }
                                : 'border-gray-200 text-gray-700 hover:border-gray-300'"
                        >
                            {{ tone.label }}
                        </button>
                    </div>
                </div>

                <!-- Length -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ t('posts.ai.length') }}
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="length in lengths"
                            :key="length.value"
                            @click="config.length = length.value"
                            class="px-3 py-2.5 text-sm font-medium rounded-lg border-2 transition-all"
                            :class="config.length === length.value
                                ? {
                                    'border-blue-500 bg-blue-50 text-blue-700': platform === 'facebook',
                                    'border-pink-500 bg-pink-50 text-pink-700': platform === 'instagram',
                                    'border-red-500 bg-red-50 text-red-700': platform === 'youtube',
                                }
                                : 'border-gray-200 text-gray-700 hover:border-gray-300'"
                        >
                            {{ length.label }}
                        </button>
                    </div>
                </div>

                <!-- Custom prompt -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ t('posts.ai.customPrompt') }}
                        <span class="text-gray-400 font-normal">({{ t('common.optional') }})</span>
                    </label>
                    <textarea
                        v-model="config.customPrompt"
                        rows="3"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:border-transparent resize-none"
                        :class="`focus:ring-${platformColors[platform].light}-500`"
                        :placeholder="t('posts.ai.customPromptPlaceholder')"
                    ></textarea>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end space-x-3">
                <Button variant="secondary" @click="$emit('close')">
                    {{ t('common.cancel') }}
                </Button>
                <Button
                    :loading="generating"
                    :disabled="!canGenerate"
                    @click="generate"
                    :class="{
                        'bg-blue-600 hover:bg-blue-700': platform === 'facebook',
                        'bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700': platform === 'instagram',
                        'bg-red-600 hover:bg-red-700': platform === 'youtube',
                    }"
                >
                    <svg v-if="!generating" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                    {{ generating ? t('posts.ai.generating') : t('posts.ai.generateButton') }}
                </Button>
            </div>
        </div>
    </div>
</template>
