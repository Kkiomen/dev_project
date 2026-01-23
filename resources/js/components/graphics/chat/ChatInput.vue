<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
    placeholder: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['send']);

const inputText = ref('');
const textareaRef = ref(null);

const handleSubmit = () => {
    if (!inputText.value.trim() || props.disabled) return;

    emit('send', inputText.value.trim());
    inputText.value = '';

    // Reset textarea height
    if (textareaRef.value) {
        textareaRef.value.style.height = 'auto';
    }
};

const handleKeydown = (e) => {
    // Submit on Enter (without Shift)
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSubmit();
    }
};

// Auto-resize textarea
const handleInput = () => {
    if (textareaRef.value) {
        textareaRef.value.style.height = 'auto';
        textareaRef.value.style.height = Math.min(textareaRef.value.scrollHeight, 120) + 'px';
    }
};

const placeholderText = props.placeholder || t('graphics.aiChat.placeholder');
</script>

<template>
    <div class="border-t border-gray-200 p-3">
        <div class="flex gap-2">
            <textarea
                ref="textareaRef"
                v-model="inputText"
                :placeholder="placeholderText"
                :disabled="disabled"
                rows="1"
                class="flex-1 resize-none rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 disabled:bg-gray-50 disabled:text-gray-500"
                @keydown="handleKeydown"
                @input="handleInput"
            />
            <button
                type="button"
                :disabled="disabled || !inputText.trim()"
                class="flex-shrink-0 rounded-lg px-3 py-2 transition-colors disabled:cursor-not-allowed"
                :style="{
                    backgroundColor: (disabled || !inputText.trim()) ? '#d1d5db' : '#9333ea',
                    color: 'white'
                }"
                @click="handleSubmit"
            >
                <!-- Send icon -->
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            </button>
        </div>
        <p class="mt-1.5 text-xs text-gray-400">
            {{ t('graphics.aiChat.enterHint') }}
        </p>
    </div>
</template>
