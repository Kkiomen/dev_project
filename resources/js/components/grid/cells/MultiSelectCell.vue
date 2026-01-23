<script setup>
import { computed, ref, watch, nextTick } from 'vue';

const props = defineProps({
    value: {
        type: Array,
        default: () => [],
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
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['toggle', 'save', 'cancel']);

const containerRef = ref(null);
const selectedIds = computed(() => props.editingValue || []);
const displayValue = computed(() => {
    if (!Array.isArray(props.value)) return [];
    return props.value.filter(item => item && item.name);
});

watch(() => props.editing, async (isEditing) => {
    if (isEditing) {
        await nextTick();
        containerRef.value?.focus();
    }
});

const handleKeydown = (event) => {
    if (event.key === 'Escape') {
        emit('cancel');
    } else if (event.key === 'Enter') {
        event.preventDefault();
        emit('save');
    }
};
</script>

<template>
    <div class="min-h-[32px] px-2 py-1">
        <div
            v-if="editing"
            ref="containerRef"
            tabindex="0"
            class="p-1 outline-none"
            @keydown="handleKeydown"
        >
            <label
                v-for="choice in choices"
                :key="choice.id"
                class="flex items-center space-x-2 px-2 py-1 hover:bg-gray-100 rounded cursor-pointer"
            >
                <input
                    type="checkbox"
                    :checked="selectedIds.includes(choice.id)"
                    @change="emit('toggle', choice.id)"
                    class="w-3.5 h-3.5 text-blue-600 rounded border-gray-300"
                />
                <span
                    class="text-sm font-medium px-1.5 py-0.5 rounded"
                    :style="{
                        backgroundColor: (choice.color || '#6B7280') + '20',
                        color: choice.color || '#6B7280',
                    }"
                >
                    {{ choice.name }}
                </span>
            </label>
            <button
                @click="emit('save')"
                class="mt-2 w-full px-2 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700"
            >
                Zapisz
            </button>
        </div>
        <div v-else class="flex flex-wrap gap-1">
            <span
                v-for="item in displayValue"
                :key="item.id"
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :style="{
                    backgroundColor: (item.color || '#6B7280') + '20',
                    color: item.color || '#6B7280',
                }"
            >
                {{ item.name }}
            </span>
            <span v-if="displayValue.length === 0" class="text-gray-400 text-sm italic">
                kliknij aby wybrać
            </span>
        </div>
    </div>
</template>
