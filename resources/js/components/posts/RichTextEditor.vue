<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '',
    },
    rows: {
        type: Number,
        default: 6,
    },
    maxLength: {
        type: Number,
        default: null,
    },
    showCharCount: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();
const textareaRef = ref(null);
const showEmojiPicker = ref(false);

const content = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const charCount = computed(() => props.modelValue?.length || 0);
const isOverLimit = computed(() => props.maxLength && charCount.value > props.maxLength);

// Unicode character maps for bold and italic
const boldMap = {
    'A': '𝗔', 'B': '𝗕', 'C': '𝗖', 'D': '𝗗', 'E': '𝗘', 'F': '𝗙', 'G': '𝗚', 'H': '𝗛', 'I': '𝗜', 'J': '𝗝',
    'K': '𝗞', 'L': '𝗟', 'M': '𝗠', 'N': '𝗡', 'O': '𝗢', 'P': '𝗣', 'Q': '𝗤', 'R': '𝗥', 'S': '𝗦', 'T': '𝗧',
    'U': '𝗨', 'V': '𝗩', 'W': '𝗪', 'X': '𝗫', 'Y': '𝗬', 'Z': '𝗭',
    'a': '𝗮', 'b': '𝗯', 'c': '𝗰', 'd': '𝗱', 'e': '𝗲', 'f': '𝗳', 'g': '𝗴', 'h': '𝗵', 'i': '𝗶', 'j': '𝗷',
    'k': '𝗸', 'l': '𝗹', 'm': '𝗺', 'n': '𝗻', 'o': '𝗼', 'p': '𝗽', 'q': '𝗾', 'r': '𝗿', 's': '𝘀', 't': '𝘁',
    'u': '𝘂', 'v': '𝘃', 'w': '𝘄', 'x': '𝘅', 'y': '𝘆', 'z': '𝘇',
    '0': '𝟬', '1': '𝟭', '2': '𝟮', '3': '𝟯', '4': '𝟰', '5': '𝟱', '6': '𝟲', '7': '𝟳', '8': '𝟴', '9': '𝟵',
};

const italicMap = {
    'A': '𝘈', 'B': '𝘉', 'C': '𝘊', 'D': '𝘋', 'E': '𝘌', 'F': '𝘍', 'G': '𝘎', 'H': '𝘏', 'I': '𝘐', 'J': '𝘑',
    'K': '𝘒', 'L': '𝘓', 'M': '𝘔', 'N': '𝘕', 'O': '𝘖', 'P': '𝘗', 'Q': '𝘘', 'R': '𝘙', 'S': '𝘚', 'T': '𝘛',
    'U': '𝘜', 'V': '𝘝', 'W': '𝘞', 'X': '𝘟', 'Y': '𝘠', 'Z': '𝘡',
    'a': '𝘢', 'b': '𝘣', 'c': '𝘤', 'd': '𝘥', 'e': '𝘦', 'f': '𝘧', 'g': '𝘨', 'h': '𝘩', 'i': '𝘪', 'j': '𝘫',
    'k': '𝘬', 'l': '𝘭', 'm': '𝘮', 'n': '𝘯', 'o': '𝘰', 'p': '𝘱', 'q': '𝘲', 'r': '𝘳', 's': '𝘴', 't': '𝘵',
    'u': '𝘶', 'v': '𝘷', 'w': '𝘸', 'x': '𝘹', 'y': '𝘺', 'z': '𝘻',
};

// Reverse maps for converting back to normal
const reverseMap = {};
Object.entries(boldMap).forEach(([normal, styled]) => { reverseMap[styled] = normal; });
Object.entries(italicMap).forEach(([normal, styled]) => { reverseMap[styled] = normal; });

// Common emojis for social media
const commonEmojis = [
    '😀', '😊', '😍', '🥰', '😎', '🤩', '😂', '🤣',
    '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '💕',
    '👍', '👏', '🙌', '💪', '🎉', '🎊', '✨', '⭐',
    '🔥', '💯', '✅', '🚀', '💡', '📌', '📢', '🎯',
    '📸', '🎬', '🎥', '📱', '💻', '🌟', '🌈', '☀️',
];

const insertText = (text) => {
    const textarea = textareaRef.value;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const before = content.value.substring(0, start);
    const after = content.value.substring(end);

    content.value = before + text + after;

    // Set cursor position after inserted text
    setTimeout(() => {
        textarea.selectionStart = textarea.selectionEnd = start + text.length;
        textarea.focus();
    }, 0);
};

const insertEmoji = (emoji) => {
    insertText(emoji);
    showEmojiPicker.value = false;
};

// Convert selected text using a character map
const convertSelection = (charMap) => {
    const textarea = textareaRef.value;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = content.value.substring(start, end);

    if (!selectedText) return;

    // Convert each character
    const converted = selectedText.split('').map(char => {
        // If already styled, convert back to normal first
        if (reverseMap[char]) {
            const normal = reverseMap[char];
            return charMap[normal] || normal;
        }
        return charMap[char] || char;
    }).join('');

    const beforeText = content.value.substring(0, start);
    const afterText = content.value.substring(end);
    content.value = beforeText + converted + afterText;

    setTimeout(() => {
        textarea.selectionStart = start;
        textarea.selectionEnd = start + converted.length;
        textarea.focus();
    }, 0);
};

// Convert styled text back to normal
const removeFormatting = () => {
    const textarea = textareaRef.value;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = content.value.substring(start, end);

    if (!selectedText) return;

    const converted = selectedText.split('').map(char => reverseMap[char] || char).join('');

    const beforeText = content.value.substring(0, start);
    const afterText = content.value.substring(end);
    content.value = beforeText + converted + afterText;

    setTimeout(() => {
        textarea.selectionStart = start;
        textarea.selectionEnd = start + converted.length;
        textarea.focus();
    }, 0);
};

const makeBold = () => convertSelection(boldMap);
const makeItalic = () => convertSelection(italicMap);

const insertHashtag = () => {
    insertText('#');
};

const insertMention = () => {
    insertText('@');
};

const insertLineBreak = () => {
    insertText('\n\n');
};

const insertBulletList = () => {
    const textarea = textareaRef.value;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const lines = content.value.substring(0, start).split('\n');
    const isStartOfLine = lines[lines.length - 1].trim() === '';

    if (isStartOfLine) {
        insertText('• ');
    } else {
        insertText('\n• ');
    }
};
</script>

<template>
    <div class="rich-text-editor">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-1 p-2 bg-gray-50 border border-gray-300 border-b-0 rounded-t-lg">
            <!-- Bold -->
            <button
                type="button"
                @click="makeBold"
                class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors font-bold"
                :title="t('editor.bold')"
            >
                B
            </button>

            <!-- Italic -->
            <button
                type="button"
                @click="makeItalic"
                class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors italic"
                :title="t('editor.italic')"
            >
                I
            </button>

            <!-- Remove formatting -->
            <button
                type="button"
                @click="removeFormatting"
                class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                :title="t('editor.removeFormatting')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            <!-- Hashtag -->
            <button
                type="button"
                @click="insertHashtag"
                class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                :title="t('editor.hashtag')"
            >
                <span class="font-bold text-sm">#</span>
            </button>

            <!-- Mention -->
            <button
                type="button"
                @click="insertMention"
                class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                :title="t('editor.mention')"
            >
                <span class="font-bold text-sm">@</span>
            </button>

            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            <!-- Bullet list -->
            <button
                type="button"
                @click="insertBulletList"
                class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                :title="t('editor.bulletList')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <!-- Line break -->
            <button
                type="button"
                @click="insertLineBreak"
                class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                :title="t('editor.lineBreak')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a4 4 0 014 4v2a4 4 0 01-4 4H3"/>
                </svg>
            </button>

            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            <!-- Emoji picker -->
            <div class="relative">
                <button
                    type="button"
                    @click="showEmojiPicker = !showEmojiPicker"
                    class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                    :title="t('editor.emoji')"
                >
                    <span class="text-base">😊</span>
                </button>

                <div
                    v-if="showEmojiPicker"
                    class="absolute top-full left-0 mt-1 p-2 bg-white rounded-lg shadow-lg border border-gray-200 z-50 w-64"
                >
                    <div class="grid grid-cols-8 gap-1">
                        <button
                            v-for="emoji in commonEmojis"
                            :key="emoji"
                            type="button"
                            @click="insertEmoji(emoji)"
                            class="p-1 text-xl hover:bg-gray-100 rounded"
                        >
                            {{ emoji }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex-1"></div>

            <!-- Character count -->
            <div v-if="showCharCount" class="text-sm" :class="isOverLimit ? 'text-red-600 font-medium' : 'text-gray-500'">
                {{ charCount }}
                <template v-if="maxLength">/ {{ maxLength }}</template>
                {{ t('posts.characters') }}
            </div>
        </div>

        <!-- Help text -->
        <div class="px-3 py-1.5 bg-gray-50 border-x border-gray-300 text-xs text-gray-500">
            {{ t('editor.selectTextHint') }}
        </div>

        <!-- Textarea -->
        <textarea
            ref="textareaRef"
            v-model="content"
            :rows="rows"
            :placeholder="placeholder"
            class="w-full px-3 py-2 border border-gray-300 rounded-b-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
            :class="{ 'border-red-300': isOverLimit }"
        ></textarea>

        <!-- Backdrop for emoji picker -->
        <div
            v-if="showEmojiPicker"
            class="fixed inset-0 z-40"
            @click="showEmojiPicker = false"
        ></div>
    </div>
</template>
