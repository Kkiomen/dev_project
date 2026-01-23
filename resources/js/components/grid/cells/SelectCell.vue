<script setup>
import { ref, watch, nextTick } from 'vue';

const props = defineProps({
    value: {
        type: Object,
        default: null,
    },
    choices: {
        type: Array,
        default: () => [],
    },
    editing: {
        type: Boolean,
        default: false,
    },
    editingValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:value', 'save', 'cancel']);

const selectRef = ref(null);

watch(() => props.editing, async (isEditing) => {
    if (isEditing) {
        await nextTick();
        selectRef.value?.focus();
    }
});
</script>

<template>
    <div class="min-h-[32px] px-2 py-1">
        <select
            v-if="editing"
            ref="selectRef"
            :value="editingValue"
            @change="emit('update:value', $event.target.value); emit('save')"
            @keydown.escape="emit('cancel')"
            class="w-full px-0 py-0 text-sm border-0 focus:ring-0 bg-transparent"
        >
            <option value="">-- Wybierz --</option>
            <option v-for="choice in choices" :key="choice.id" :value="choice.id">
                {{ choice.name }}
            </option>
        </select>
        <div v-else-if="value && value.name">
            <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :style="{
                    backgroundColor: (value.color || '#6B7280') + '20',
                    color: value.color || '#6B7280',
                }"
            >
                {{ value.name }}
            </span>
        </div>
    </div>
</template>
