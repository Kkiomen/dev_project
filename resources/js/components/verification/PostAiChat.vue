<script setup>
import { ref, computed, nextTick, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const props = defineProps({
    post: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'modify']);

const { t } = useI18n();

const messages = ref([]);
const inputMessage = ref('');
const isLoading = ref(false);
const messagesContainer = ref(null);

// Suggestions for quick actions
const suggestions = computed(() => [
    { label: t('verification.ai.suggestions.shorter'), prompt: t('verification.ai.prompts.shorter') },
    { label: t('verification.ai.suggestions.longer'), prompt: t('verification.ai.prompts.longer') },
    { label: t('verification.ai.suggestions.professional'), prompt: t('verification.ai.prompts.professional') },
    { label: t('verification.ai.suggestions.casual'), prompt: t('verification.ai.prompts.casual') },
    { label: t('verification.ai.suggestions.addEmojis'), prompt: t('verification.ai.prompts.addEmojis') },
    { label: t('verification.ai.suggestions.addCTA'), prompt: t('verification.ai.prompts.addCTA') },
]);

// Scroll to bottom when new message added
const scrollToBottom = async () => {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

watch(messages, scrollToBottom, { deep: true });

// Send message to AI
const sendMessage = async (prompt = null) => {
    const messageText = prompt || inputMessage.value.trim();
    if (!messageText || isLoading.value) return;

    // Add user message
    messages.value.push({
        role: 'user',
        content: messageText,
        timestamp: new Date(),
    });

    inputMessage.value = '';
    isLoading.value = true;

    try {
        const response = await axios.post('/api/v1/posts/ai/modify', {
            post_id: props.post.id,
            current_caption: props.post.main_caption,
            current_title: props.post.title,
            instruction: messageText,
        });

        const result = response.data;

        // Add AI response
        messages.value.push({
            role: 'assistant',
            content: result.message || t('verification.ai.modified'),
            modified: result.modified,
            newCaption: result.caption,
            newTitle: result.title,
            timestamp: new Date(),
        });

        // If AI modified the content, emit the changes
        if (result.modified) {
            emit('modify', {
                caption: result.caption,
                title: result.title,
            });
        }
    } catch (error) {
        console.error('AI request failed:', error);

        messages.value.push({
            role: 'assistant',
            content: error.response?.data?.message || t('verification.ai.error'),
            isError: true,
            timestamp: new Date(),
        });
    } finally {
        isLoading.value = false;
    }
};

const handleKeydown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
};

const useSuggestion = (suggestion) => {
    sendMessage(suggestion.prompt);
};

const formatTime = (date) => {
    return new Date(date).toLocaleTimeString('pl-PL', {
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <div class="bg-gray-800 rounded-2xl border border-gray-700 flex flex-col h-[500px]">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-purple-600 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
                <span class="font-medium text-white">{{ t('verification.ai.title') }}</span>
            </div>
            <button
                @click="emit('close')"
                class="text-gray-400 hover:text-white transition-colors"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Messages -->
        <div
            ref="messagesContainer"
            class="flex-1 overflow-y-auto p-4 space-y-4"
        >
            <!-- Welcome message -->
            <div v-if="messages.length === 0" class="text-center py-8">
                <div class="w-16 h-16 bg-purple-900/50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                </div>
                <h4 class="text-white font-medium mb-2">{{ t('verification.ai.welcome') }}</h4>
                <p class="text-gray-400 text-sm mb-6">{{ t('verification.ai.welcomeDescription') }}</p>

                <!-- Quick suggestions -->
                <div class="flex flex-wrap justify-center gap-2">
                    <button
                        v-for="suggestion in suggestions"
                        :key="suggestion.label"
                        @click="useSuggestion(suggestion)"
                        class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs rounded-full transition-colors"
                    >
                        {{ suggestion.label }}
                    </button>
                </div>
            </div>

            <!-- Message list -->
            <template v-else>
                <div
                    v-for="(message, index) in messages"
                    :key="index"
                    :class="[
                        'flex',
                        message.role === 'user' ? 'justify-end' : 'justify-start'
                    ]"
                >
                    <div
                        :class="[
                            'max-w-[85%] rounded-2xl px-4 py-2',
                            message.role === 'user'
                                ? 'bg-purple-600 text-white'
                                : message.isError
                                    ? 'bg-red-900/50 text-red-300 border border-red-700'
                                    : 'bg-gray-700 text-gray-200'
                        ]"
                    >
                        <p class="text-sm whitespace-pre-wrap">{{ message.content }}</p>

                        <!-- Modified content preview -->
                        <div
                            v-if="message.modified && message.newCaption"
                            class="mt-3 pt-3 border-t border-gray-600"
                        >
                            <p class="text-xs text-gray-400 mb-1">{{ t('verification.ai.newCaption') }}:</p>
                            <p class="text-sm text-white line-clamp-3">{{ message.newCaption }}</p>
                        </div>

                        <p class="text-xs opacity-50 mt-1">{{ formatTime(message.timestamp) }}</p>
                    </div>
                </div>

                <!-- Loading indicator -->
                <div v-if="isLoading" class="flex justify-start">
                    <div class="bg-gray-700 rounded-2xl px-4 py-3">
                        <div class="flex space-x-1">
                            <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0ms;"></div>
                            <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 150ms;"></div>
                            <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 300ms;"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Quick suggestions (when has messages) -->
        <div v-if="messages.length > 0 && !isLoading" class="px-4 pb-2">
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="suggestion in suggestions.slice(0, 4)"
                    :key="suggestion.label"
                    @click="useSuggestion(suggestion)"
                    class="px-2 py-1 bg-gray-700/50 hover:bg-gray-700 text-gray-400 text-xs rounded-full transition-colors"
                >
                    {{ suggestion.label }}
                </button>
            </div>
        </div>

        <!-- Input -->
        <div class="p-4 border-t border-gray-700">
            <div class="flex items-end space-x-2">
                <textarea
                    v-model="inputMessage"
                    @keydown="handleKeydown"
                    :placeholder="t('verification.ai.placeholder')"
                    :disabled="isLoading"
                    rows="2"
                    class="flex-1 bg-gray-700 border border-gray-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 resize-none focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent disabled:opacity-50"
                ></textarea>
                <button
                    @click="sendMessage()"
                    :disabled="!inputMessage.trim() || isLoading"
                    class="p-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
