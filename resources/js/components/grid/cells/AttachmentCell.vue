<script setup>
defineProps({
    value: {
        type: Array,
        default: () => [],
    },
    uploading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['upload', 'remove']);
</script>

<template>
    <div class="min-h-[32px] px-2 py-1">
        <div class="flex items-center space-x-1">
            <template v-for="(att, i) in (value || []).slice(0, 3)" :key="att.id">
                <div class="relative group w-7 h-7 rounded overflow-hidden bg-gray-100 flex-shrink-0">
                    <img
                        v-if="att.is_image"
                        :src="att.thumbnail_url || att.url"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <!-- Delete button on hover -->
                    <button
                        @click.stop="emit('remove', att.id)"
                        class="absolute inset-0 bg-red-500/80 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </template>

            <span
                v-if="(value || []).length > 3"
                class="text-xs text-gray-500"
            >
                +{{ (value || []).length - 3 }}
            </span>

            <!-- Upload button -->
            <label
                @click.stop
                class="cursor-pointer w-7 h-7 flex items-center justify-center border border-dashed border-gray-300 rounded hover:border-blue-500 hover:bg-blue-50 transition-colors"
            >
                <input
                    type="file"
                    class="hidden"
                    @change="emit('upload', $event)"
                    accept="image/*,application/pdf"
                    multiple
                />
                <svg v-if="!uploading" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <svg v-else class="w-4 h-4 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                </svg>
            </label>
        </div>
    </div>
</template>
