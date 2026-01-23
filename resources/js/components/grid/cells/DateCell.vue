<script setup>
import { ref, computed, watch, nextTick } from 'vue';

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

const formattedValue = computed(() => {
    if (!props.value) return '';
    try {
        return new Date(props.value).toLocaleDateString('pl-PL');
    } catch {
        return props.value;
    }
});

watch(() => props.editing, async (editing) => {
    if (editing) {
        await nextTick();
        inputRef.value?.focus();
    }
});
</script>

<template>
    <div class="min-h-[32px] px-2 py-1">
        <input
            v-if="editing"
            ref="inputRef"
            type="date"
            :value="value ? value.substring(0, 10) : ''"
            @input="emit('update:value', $event.target.value)"
            @keydown.enter="emit('save')"
            @keydown.escape="emit('cancel')"
            @blur="emit('save')"
            class="w-full px-0 py-0 text-sm border-0 focus:ring-0 bg-transparent"
        />
        <span v-else class="text-sm text-gray-900">
            {{ formattedValue }}
        </span>
    </div>
</template>
