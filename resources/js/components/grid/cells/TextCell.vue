<script setup>
import { ref, watch, nextTick } from 'vue';

const props = defineProps({
    value: {
        type: String,
        default: '',
    },
    editing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:value', 'save', 'cancel']);

const inputRef = ref(null);

watch(() => props.editing, async (editing) => {
    if (editing) {
        await nextTick();
        inputRef.value?.focus();
        inputRef.value?.select();
    }
});
</script>

<template>
    <div class="min-h-[32px] px-2 py-1">
        <input
            v-if="editing"
            ref="inputRef"
            type="text"
            :value="value"
            @input="emit('update:value', $event.target.value)"
            @keydown.enter.prevent="emit('save')"
            @keydown.escape="emit('cancel')"
            @blur="emit('save')"
            class="w-full px-0 py-0 text-sm border-0 focus:ring-0 bg-transparent"
        />
        <span v-else class="text-sm text-gray-900 truncate block">
            {{ value || '' }}
        </span>
    </div>
</template>
