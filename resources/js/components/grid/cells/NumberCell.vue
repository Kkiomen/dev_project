<script setup>
import { ref, computed, watch, nextTick } from 'vue';

const props = defineProps({
    value: {
        type: [Number, String],
        default: null,
    },
    editing: {
        type: Boolean,
        default: false,
    },
    precision: {
        type: Number,
        default: 2,
    },
    step: {
        type: Number,
        default: 1,
    },
});

const emit = defineEmits(['update:value', 'save', 'cancel']);

const inputRef = ref(null);
const localValue = ref(props.value);

const formattedValue = computed(() => {
    if (props.value === null || props.value === undefined || props.value === '') return '';
    return Number(props.value).toLocaleString('pl-PL', {
        minimumFractionDigits: 0,
        maximumFractionDigits: props.precision,
    });
});

watch(() => props.value, (newVal) => {
    localValue.value = newVal;
});

watch(() => props.editing, async (editing) => {
    if (editing) {
        localValue.value = props.value;
        await nextTick();
        inputRef.value?.focus();
        inputRef.value?.select();
    }
});

const increment = () => {
    const current = parseFloat(localValue.value) || 0;
    localValue.value = current + props.step;
    emit('update:value', localValue.value);
};

const decrement = () => {
    const current = parseFloat(localValue.value) || 0;
    localValue.value = current - props.step;
    emit('update:value', localValue.value);
};

const handleInput = (e) => {
    localValue.value = e.target.value;
    emit('update:value', e.target.value);
};
</script>

<template>
    <div class="min-h-[32px] px-2 py-1">
        <div v-if="editing" class="flex items-center space-x-1">
            <button
                type="button"
                @click.stop="decrement"
                class="w-6 h-6 flex items-center justify-center rounded bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-bold"
            >
                −
            </button>
            <input
                ref="inputRef"
                type="number"
                :value="localValue"
                @input="handleInput"
                @keydown.enter.prevent="emit('save')"
                @keydown.escape="emit('cancel')"
                @blur="emit('save')"
                :step="step"
                class="flex-1 min-w-0 px-1 py-0 text-sm text-center border-0 focus:ring-0 bg-transparent tabular-nums"
            />
            <button
                type="button"
                @click.stop="increment"
                class="w-6 h-6 flex items-center justify-center rounded bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-bold"
            >
                +
            </button>
        </div>
        <span v-else class="text-sm text-gray-900 tabular-nums">
            {{ formattedValue }}
        </span>
    </div>
</template>
