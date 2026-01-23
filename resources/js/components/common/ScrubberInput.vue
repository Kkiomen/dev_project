<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: 0,
    },
    min: {
        type: Number,
        default: -Infinity,
    },
    max: {
        type: Number,
        default: Infinity,
    },
    step: {
        type: Number,
        default: 1,
    },
    sensitivity: {
        type: Number,
        default: 1,
    },
    decimals: {
        type: Number,
        default: 0,
    },
    suffix: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    inputClass: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const isDragging = ref(false);
const isHovering = ref(false);
const startX = ref(0);
const startValue = ref(0);
const inputRef = ref(null);

const currentValue = computed(() => {
    return parseFloat(props.modelValue) || 0;
});

const handleMouseDown = (e) => {
    if (props.disabled) return;
    if (e.button !== 0) return;

    // Don't start scrubbing if clicking directly on the input
    if (e.target === inputRef.value) return;

    e.preventDefault();
    isDragging.value = true;
    startX.value = e.clientX;
    startValue.value = currentValue.value;

    document.body.style.cursor = 'ew-resize';
    document.body.style.userSelect = 'none';

    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
};

const handleMouseMove = (e) => {
    if (!isDragging.value) return;

    const deltaX = e.clientX - startX.value;
    const deltaValue = deltaX * props.sensitivity * props.step;
    let newValue = startValue.value + deltaValue;

    // Apply constraints
    newValue = Math.max(props.min, Math.min(props.max, newValue));

    // Round to step/decimals
    if (props.decimals === 0) {
        newValue = Math.round(newValue / props.step) * props.step;
    } else {
        newValue = parseFloat(newValue.toFixed(props.decimals));
    }

    emit('update:modelValue', newValue);
};

const handleMouseUp = () => {
    isDragging.value = false;
    document.body.style.cursor = '';
    document.body.style.userSelect = '';

    document.removeEventListener('mousemove', handleMouseMove);
    document.removeEventListener('mouseup', handleMouseUp);
};

const handleInput = (e) => {
    let value = parseFloat(e.target.value);
    if (isNaN(value)) value = props.min > -Infinity ? props.min : 0;
    value = Math.max(props.min, Math.min(props.max, value));
    emit('update:modelValue', value);
};

const handleWheel = (e) => {
    if (props.disabled) return;
    if (document.activeElement !== inputRef.value) return;

    e.preventDefault();
    const delta = e.deltaY > 0 ? -props.step : props.step;
    let newValue = currentValue.value + delta;
    newValue = Math.max(props.min, Math.min(props.max, newValue));

    if (props.decimals === 0) {
        newValue = Math.round(newValue / props.step) * props.step;
    } else {
        newValue = parseFloat(newValue.toFixed(props.decimals));
    }

    emit('update:modelValue', newValue);
};
</script>

<template>
    <div
        class="scrubber-input relative"
        :class="{ 'opacity-50': disabled }"
        @mouseenter="isHovering = true"
        @mouseleave="isHovering = false"
    >
        <label
            v-if="label"
            class="scrubber-label flex items-center gap-1 text-[10px] text-gray-500 mb-1 select-none"
            :class="{ 'text-blue-600': isDragging }"
            @mousedown="handleMouseDown"
        >
            <!-- Drag indicator icon -->
            <svg
                class="w-3 h-3 opacity-40 transition-opacity"
                :class="{ 'opacity-100 text-blue-500': isHovering || isDragging }"
                viewBox="0 0 24 24"
                fill="currentColor"
            >
                <path d="M8 5h2v14H8zM14 5h2v14h-2z"/>
            </svg>
            <span>{{ label }}</span>
        </label>
        <div
            class="scrubber-field relative"
            @mousedown="handleMouseDown"
        >
            <input
                ref="inputRef"
                :value="modelValue"
                @input="handleInput"
                @wheel="handleWheel"
                type="number"
                :min="min"
                :max="max"
                :step="step"
                :disabled="disabled"
                :class="[
                    'w-full bg-gray-50 border border-gray-200 rounded text-gray-900 text-xs focus:outline-none focus:border-blue-500 focus:bg-white transition-colors',
                    suffix ? 'pr-7' : '',
                    inputClass || 'px-2.5 py-1.5',
                    isDragging ? 'border-blue-500 bg-blue-50' : ''
                ]"
            />
            <span
                v-if="suffix"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-[10px] pointer-events-none select-none"
            >
                {{ suffix }}
            </span>
            <!-- Drag zone indicator on the left side of input -->
            <div
                class="absolute left-0 top-0 bottom-0 w-6 flex items-center justify-center rounded-l transition-colors"
                :class="[
                    isHovering || isDragging ? 'bg-blue-100' : 'bg-transparent',
                ]"
            >
                <svg
                    class="w-3 h-3 text-gray-400 transition-colors"
                    :class="{ 'text-blue-500': isHovering || isDragging }"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                >
                    <path d="M8 5h2v14H8zM14 5h2v14h-2z"/>
                </svg>
            </div>
        </div>
    </div>
</template>

<style scoped>
.scrubber-input input[type="number"]::-webkit-inner-spin-button,
.scrubber-input input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.scrubber-input input[type="number"] {
    -moz-appearance: textfield;
    padding-left: 1.75rem; /* Space for drag zone */
}

.scrubber-label {
    cursor: ew-resize;
}

.scrubber-field {
    cursor: ew-resize;
}

.scrubber-field input {
    cursor: text;
}

.scrubber-field input:focus {
    cursor: text;
}
</style>
