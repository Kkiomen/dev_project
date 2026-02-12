<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const message = ref('');
</script>

<template>
    <div class="min-h-full bg-gray-950 flex flex-col">
        <!-- Header -->
        <div class="border-b border-gray-800 px-4 sm:px-6 lg:px-8 py-4 shrink-0">
            <h1 class="text-lg font-bold text-white">{{ t('manager.aiChat.title') }}</h1>
            <p class="text-sm text-gray-400">{{ t('manager.aiChat.subtitle') }}</p>
        </div>

        <!-- Chat area -->
        <div class="flex-1 flex flex-col items-center justify-center p-4 sm:p-6 lg:p-8">
            <div class="w-16 h-16 rounded-full bg-indigo-500/10 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.aiChat.welcomeTitle') }}</h3>
            <p class="text-sm text-gray-400 max-w-md text-center mb-8">{{ t('manager.aiChat.welcomeDescription') }}</p>

            <!-- Suggested prompts -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-lg w-full">
                <button
                    v-for="prompt in [
                        t('manager.aiChat.suggestions.strategy'),
                        t('manager.aiChat.suggestions.content'),
                        t('manager.aiChat.suggestions.performance'),
                        t('manager.aiChat.suggestions.ideas'),
                    ]"
                    :key="prompt"
                    class="px-4 py-3 text-sm text-left rounded-xl bg-gray-900 border border-gray-800 text-gray-300 hover:border-indigo-500/50 hover:text-white transition-all"
                >
                    {{ prompt }}
                </button>
            </div>
        </div>

        <!-- Input area -->
        <div class="border-t border-gray-800 px-4 sm:px-6 lg:px-8 py-4 shrink-0">
            <div class="flex gap-3 max-w-3xl mx-auto">
                <input
                    v-model="message"
                    type="text"
                    :placeholder="t('manager.aiChat.inputPlaceholder')"
                    class="flex-1 px-4 py-2.5 bg-gray-900 border border-gray-700 rounded-xl text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition"
                />
                <button
                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-xl transition-colors disabled:opacity-50"
                    :disabled="!message.trim()"
                >
                    {{ t('manager.aiChat.send') }}
                </button>
            </div>
        </div>
    </div>
</template>
