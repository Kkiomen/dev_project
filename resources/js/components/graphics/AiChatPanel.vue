<script setup>
import { ref, nextTick, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAiChat } from '@/composables/useAiChat';
import ChatMessage from './chat/ChatMessage.vue';
import ChatInput from './chat/ChatInput.vue';

const { t } = useI18n();

const emit = defineEmits(['close']);

const { messages, isLoading, sendMessage, clearHistory, hasMessages } = useAiChat();

const messagesContainer = ref(null);

// Auto-scroll to bottom when new messages arrive
watch(
    () => messages.value.length,
    async () => {
        await nextTick();
        scrollToBottom();
    }
);

const scrollToBottom = () => {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

const handleSend = async (text) => {
    try {
        await sendMessage(text);
    } catch (err) {
        // Error is already handled in composable
        console.error('Failed to send message:', err);
    }
};

const handleClearHistory = () => {
    clearHistory();
};

// Suggestions for empty state
const suggestions = [
    { key: 'createInstagram', text: t('graphics.aiChat.suggestions.createInstagram') },
    { key: 'changeText', text: t('graphics.aiChat.suggestions.changeText') },
    { key: 'addShape', text: t('graphics.aiChat.suggestions.addShape') },
    { key: 'apiHelp', text: t('graphics.aiChat.suggestions.apiHelp') },
];

const handleSuggestionClick = (text) => {
    handleSend(text);
};

onMounted(() => {
    scrollToBottom();
});
</script>

<template>
    <div class="flex flex-col h-full min-w-0 overflow-hidden">
        <!-- Header -->
        <div class="px-3 py-2.5 border-b border-gray-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <!-- AI Icon -->
                <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center">
                    <svg class="w-4 h-4" style="color: #9333ea" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
                <span class="text-xs font-medium text-gray-700 uppercase tracking-wide">
                    {{ t('graphics.aiChat.title') }}
                </span>
            </div>
            <div class="flex items-center gap-1">
                <!-- Clear history button -->
                <button
                    v-if="hasMessages"
                    @click="handleClearHistory"
                    class="p-1.5 text-gray-400 hover:text-gray-600 rounded transition-colors"
                    :title="t('graphics.aiChat.clearHistory')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
                <!-- Close button -->
                <button
                    @click="emit('close')"
                    class="p-1.5 text-gray-400 hover:text-gray-600 rounded transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Messages area -->
        <div
            ref="messagesContainer"
            class="flex-1 overflow-y-auto p-3 space-y-3 min-w-0"
        >
            <!-- Empty state with suggestions -->
            <div v-if="!hasMessages" class="h-full flex flex-col justify-center">
                <div class="text-center mb-4">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-purple-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ t('graphics.aiChat.welcomeMessage') }}
                    </p>
                </div>

                <!-- Suggestion buttons -->
                <div class="space-y-2">
                    <button
                        v-for="suggestion in suggestions"
                        :key="suggestion.key"
                        @click="handleSuggestionClick(suggestion.text)"
                        class="w-full text-left px-3 py-2 text-sm text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors border border-gray-200"
                    >
                        {{ suggestion.text }}
                    </button>
                </div>
            </div>

            <!-- Messages list -->
            <template v-else>
                <ChatMessage
                    v-for="message in messages"
                    :key="message.id"
                    :message="message"
                />

                <!-- Loading indicator -->
                <div v-if="isLoading" class="flex justify-start">
                    <div class="bg-gray-100 rounded-lg px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="flex gap-1">
                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                            </div>
                            <span class="text-sm text-gray-500">{{ t('graphics.aiChat.thinking') }}</span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Input area -->
        <ChatInput
            :disabled="isLoading"
            @send="handleSend"
        />
    </div>
</template>

<style scoped>
@keyframes bounce {
    0%, 60%, 100% {
        transform: translateY(0);
    }
    30% {
        transform: translateY(-4px);
    }
}

.animate-bounce {
    animation: bounce 1s ease-in-out infinite;
}
</style>
