<script setup>
import { computed, inject } from 'vue';

const dark = inject('onboardingDark', false);

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    type: {
        type: String,
        default: 'text',
    },
    placeholder: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const value = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val),
});
</script>

<template>
    <div>
        <label
            v-if="label"
            class="block text-sm font-medium mb-1"
            :class="dark ? 'text-gray-300' : 'text-gray-700'"
        >
            {{ label }}
        </label>
        <input
            v-model="value"
            :type="type"
            :placeholder="placeholder"
            :disabled="disabled"
            class="block w-full rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
            :class="[
                error ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '',
                dark
                    ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-500 disabled:bg-gray-900 disabled:text-gray-600'
                    : 'border-gray-300 disabled:bg-gray-50 disabled:text-gray-500',
            ]"
        />
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
