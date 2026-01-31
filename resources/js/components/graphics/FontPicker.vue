<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useGoogleFonts } from '@/composables/useGoogleFonts';

const { t } = useI18n();
const { loadFont, loadFontWithFallback, searchFonts, isFontLoaded, isFontLoading, isFontFailed, systemFonts, popularFonts, defaultFallbackFont } = useGoogleFonts();

const props = defineProps({
    modelValue: {
        type: String,
        default: 'Arial',
    },
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref(null);
const searchInputRef = ref(null);
const highlightedIndex = ref(-1);
const customFontInput = ref('');
const isLoadingCustomFont = ref(false);
const customFontError = ref('');

// Filtered fonts based on search
const filteredFonts = computed(() => {
    const query = searchQuery.value.toLowerCase();
    if (!query) {
        return [
            { label: t('graphics.fonts.system'), fonts: systemFonts },
            { label: t('graphics.fonts.popular'), fonts: popularFonts },
        ];
    }

    const results = searchFonts(query);
    return [{ label: t('graphics.fonts.searchResults'), fonts: results }];
});

// Flat list for keyboard navigation
const flatFontList = computed(() => {
    return filteredFonts.value.flatMap((group) => group.fonts);
});

// Check if custom font option should be shown
const showCustomFontOption = computed(() => {
    const query = searchQuery.value.trim();
    if (!query) return false;
    // Show custom font option if search query doesn't match any existing font exactly
    const lowerQuery = query.toLowerCase();
    return !flatFontList.value.some(f => f.toLowerCase() === lowerQuery);
});

// Try to load a custom font from Google Fonts
const tryCustomFont = async () => {
    const fontName = searchQuery.value.trim();
    if (!fontName) return;

    isLoadingCustomFont.value = true;
    customFontError.value = '';

    try {
        const success = await loadFont(fontName);
        if (success) {
            emit('update:modelValue', fontName);
            isOpen.value = false;
            searchQuery.value = '';
        } else {
            customFontError.value = t('graphics.fonts.fontNotAvailable', { font: fontName });
        }
    } catch (error) {
        customFontError.value = t('graphics.fonts.fontNotAvailable', { font: fontName });
    } finally {
        isLoadingCustomFont.value = false;
    }
};

// Load the current font
watch(
    () => props.modelValue,
    (fontFamily) => {
        if (fontFamily) {
            loadFont(fontFamily);
        }
    },
    { immediate: true }
);

// Load font on hover for preview
const preloadFont = (fontFamily) => {
    loadFont(fontFamily);
};

// Select a font
const selectFont = async (fontFamily) => {
    await loadFont(fontFamily);
    emit('update:modelValue', fontFamily);
    isOpen.value = false;
    searchQuery.value = '';
    highlightedIndex.value = -1;
};

// Toggle dropdown
const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        nextTick(() => {
            searchInputRef.value?.focus();
        });
    }
};

// Close on click outside
const handleClickOutside = (event) => {
    if (containerRef.value && !containerRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

// Keyboard navigation
const handleKeydown = (event) => {
    if (!isOpen.value) {
        if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
            event.preventDefault();
            isOpen.value = true;
            nextTick(() => {
                searchInputRef.value?.focus();
            });
        }
        return;
    }

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            highlightedIndex.value = Math.min(highlightedIndex.value + 1, flatFontList.value.length - 1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0);
            break;
        case 'Enter':
            event.preventDefault();
            if (highlightedIndex.value >= 0 && highlightedIndex.value < flatFontList.value.length) {
                selectFont(flatFontList.value[highlightedIndex.value]);
            }
            break;
        case 'Escape':
            event.preventDefault();
            isOpen.value = false;
            highlightedIndex.value = -1;
            break;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

// Cleanup
import { onUnmounted } from 'vue';
onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="containerRef" class="relative" @keydown="handleKeydown">
        <!-- Trigger button -->
        <button
            type="button"
            @click="toggleDropdown"
            class="w-full pl-2.5 pr-2 py-2 bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs text-left focus:outline-none focus:border-blue-500 focus:bg-white transition-colors flex items-center justify-between"
        >
            <span :style="{ fontFamily: modelValue }" class="truncate">{{ modelValue }}</span>
            <svg
                class="w-4 h-4 text-gray-400 ml-2 flex-shrink-0 transition-transform"
                :class="{ 'rotate-180': isOpen }"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown -->
        <div
            v-show="isOpen"
            class="absolute z-50 mt-1 w-64 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden"
        >
            <!-- Search input -->
            <div class="p-2 border-b border-gray-100">
                <input
                    ref="searchInputRef"
                    v-model="searchQuery"
                    type="text"
                    :placeholder="t('graphics.fonts.searchFonts')"
                    class="w-full px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded text-xs text-gray-900 focus:outline-none focus:border-blue-500 focus:bg-white transition-colors"
                />
            </div>

            <!-- Font list -->
            <div class="max-h-64 overflow-y-auto">
                <!-- Custom font option - shown when search doesn't match existing fonts -->
                <template v-if="showCustomFontOption">
                    <div class="px-2 py-1 bg-blue-50 text-[10px] font-medium text-blue-600 uppercase tracking-wider sticky top-0">
                        {{ t('graphics.fonts.tryGoogleFont') }}
                    </div>
                    <button
                        @click="tryCustomFont"
                        :disabled="isLoadingCustomFont"
                        class="w-full px-3 py-2 text-left text-sm transition-colors flex items-center justify-between text-blue-700 hover:bg-blue-50 border-b border-gray-100"
                    >
                        <span class="truncate flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ t('graphics.fonts.loadFont', { font: searchQuery.trim() }) }}
                        </span>
                        <span v-if="isLoadingCustomFont" class="ml-2">
                            <svg class="w-3 h-3 animate-spin text-blue-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                        </span>
                    </button>
                    <div v-if="customFontError" class="px-3 py-1.5 text-xs text-red-600 bg-red-50">
                        {{ customFontError }}
                    </div>
                </template>

                <template v-for="(group, groupIndex) in filteredFonts" :key="groupIndex">
                    <div class="px-2 py-1 bg-gray-50 text-[10px] font-medium text-gray-500 uppercase tracking-wider sticky top-0">
                        {{ group.label }}
                    </div>
                    <button
                        v-for="(font, fontIndex) in group.fonts"
                        :key="font"
                        @click="selectFont(font)"
                        @mouseenter="preloadFont(font)"
                        :class="[
                            'w-full px-3 py-2 text-left text-sm transition-colors flex items-center justify-between',
                            font === modelValue ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50',
                            flatFontList.indexOf(font) === highlightedIndex ? 'bg-gray-100' : ''
                        ]"
                    >
                        <span :style="{ fontFamily: isFontLoaded(font) ? font : 'inherit' }" class="truncate">
                            {{ font }}
                        </span>
                        <span v-if="isFontLoading(font)" class="ml-2">
                            <svg class="w-3 h-3 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                        </span>
                        <svg
                            v-else-if="font === modelValue"
                            class="w-4 h-4 text-blue-600 flex-shrink-0 ml-2"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </template>

                <!-- No results -->
                <div v-if="flatFontList.length === 0" class="px-3 py-4 text-xs text-gray-500 text-center">
                    {{ t('graphics.fonts.noFontsFound') }}
                </div>
            </div>
        </div>
    </div>
</template>
