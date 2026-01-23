<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    code: {
        type: String,
        required: true,
    },
    language: {
        type: String,
        default: 'json',
    },
});

const copied = ref(false);

const copyCode = async () => {
    try {
        await navigator.clipboard.writeText(props.code);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

const languageLabels = {
    json: 'JSON',
    bash: 'Bash',
    javascript: 'JavaScript',
    http: 'HTTP',
    text: 'Text',
};
</script>

<template>
    <div class="relative group rounded-lg overflow-hidden border border-gray-200 mb-4">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-2 bg-gray-100 border-b border-gray-200">
            <span class="text-xs font-medium text-gray-500">
                {{ languageLabels[language] || language.toUpperCase() }}
            </span>
            <button
                @click="copyCode"
                class="flex items-center gap-1.5 px-2 py-1 text-xs text-gray-500 hover:text-gray-700 bg-white hover:bg-gray-50 border border-gray-200 rounded transition-colors"
            >
                <svg v-if="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <svg v-else class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ copied ? t('docs.copied') : t('docs.copyCode') }}
            </button>
        </div>

        <!-- Code -->
        <div class="bg-gray-50 overflow-x-auto">
            <pre class="p-4 text-sm leading-relaxed"><code class="text-gray-800 font-mono whitespace-pre">{{ code }}</code></pre>
        </div>
    </div>
</template>
