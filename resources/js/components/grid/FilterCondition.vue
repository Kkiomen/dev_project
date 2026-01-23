<script setup>
import { ref, computed, watch } from 'vue';
import { getOperatorsForFieldType, operators, getDefaultOperator } from '@/config/filterOperators';

const props = defineProps({
    condition: {
        type: Object,
        required: true,
    },
    fields: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['update', 'remove']);

// Local state
const selectedFieldId = ref(props.condition.field_id);
const selectedOperator = ref(props.condition.operator);
const filterValue = ref(props.condition.value);
const filterValueEnd = ref(props.condition.value?.[1] ?? null); // For between operator

// Computed
const selectedField = computed(() => {
    return props.fields.find(f => f.id === selectedFieldId.value);
});

const availableOperators = computed(() => {
    if (!selectedField.value) return [];
    return getOperatorsForFieldType(selectedField.value.type);
});

const currentOperator = computed(() => {
    return operators[selectedOperator.value];
});

const showValueInput = computed(() => {
    return currentOperator.value?.requiresValue ?? true;
});

const isRangeOperator = computed(() => {
    return currentOperator.value?.isRange ?? false;
});

const isMultipleSelect = computed(() => {
    return currentOperator.value?.isMultiple ?? false;
});

const inputType = computed(() => {
    if (!selectedField.value) return 'text';
    const type = selectedField.value.type;

    if (type === 'number') return 'number';
    if (type === 'date') return 'date';
    if (type === 'datetime') return 'datetime-local';
    return 'text';
});

const hasChoices = computed(() => {
    return ['select', 'multi_select'].includes(selectedField.value?.type);
});

const choices = computed(() => {
    return selectedField.value?.options?.choices || [];
});

// Watchers
watch(selectedFieldId, (newFieldId) => {
    const field = props.fields.find(f => f.id === newFieldId);
    if (field) {
        selectedOperator.value = getDefaultOperator(field.type);
        filterValue.value = null;
        filterValueEnd.value = null;
        emitUpdate();
    }
});

watch(selectedOperator, () => {
    // Reset value when operator changes
    if (!currentOperator.value?.requiresValue) {
        filterValue.value = null;
        filterValueEnd.value = null;
    }
    emitUpdate();
});

watch([filterValue, filterValueEnd], () => {
    emitUpdate();
});

// Methods
const emitUpdate = () => {
    let value = filterValue.value;

    if (isRangeOperator.value && filterValue.value && filterValueEnd.value) {
        value = [filterValue.value, filterValueEnd.value];
    }

    emit('update', {
        field_id: selectedFieldId.value,
        field_type: selectedField.value?.type,
        operator: selectedOperator.value,
        value: value,
    });
};

const handleRemove = () => {
    emit('remove');
};

const toggleChoice = (choiceId) => {
    if (!Array.isArray(filterValue.value)) {
        filterValue.value = [];
    }
    const index = filterValue.value.indexOf(choiceId);
    if (index === -1) {
        filterValue.value = [...filterValue.value, choiceId];
    } else {
        filterValue.value = filterValue.value.filter(id => id !== choiceId);
    }
};

const isChoiceSelected = (choiceId) => {
    if (!Array.isArray(filterValue.value)) return false;
    return filterValue.value.includes(choiceId);
};
</script>

<template>
    <div class="flex items-start gap-2 p-2 bg-gray-50 rounded-lg">
        <!-- Field selector -->
        <select
            v-model="selectedFieldId"
            class="flex-shrink-0 w-36 text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500"
        >
            <option
                v-for="field in fields"
                :key="field.id"
                :value="field.id"
            >
                {{ field.name }}
            </option>
        </select>

        <!-- Operator selector -->
        <select
            v-model="selectedOperator"
            class="flex-shrink-0 w-40 text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500"
        >
            <option
                v-for="op in availableOperators"
                :key="op.value"
                :value="op.value"
            >
                {{ op.label }}
            </option>
        </select>

        <!-- Value input -->
        <div v-if="showValueInput" class="flex-1 min-w-0">
            <!-- Select/Multi-select choices -->
            <template v-if="hasChoices && isMultipleSelect">
                <div class="flex flex-wrap gap-1">
                    <button
                        v-for="choice in choices"
                        :key="choice.id"
                        type="button"
                        @click="toggleChoice(choice.id)"
                        class="px-2 py-1 text-xs rounded-full border transition-colors"
                        :class="isChoiceSelected(choice.id)
                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                            : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400'"
                    >
                        {{ choice.name }}
                    </button>
                </div>
            </template>

            <!-- Single select choice -->
            <template v-else-if="hasChoices && !isMultipleSelect">
                <select
                    v-model="filterValue"
                    class="w-full text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500"
                >
                    <option :value="null">-- wybierz --</option>
                    <option
                        v-for="choice in choices"
                        :key="choice.id"
                        :value="choice.id"
                    >
                        {{ choice.name }}
                    </option>
                </select>
            </template>

            <!-- Range input (between operator) -->
            <template v-else-if="isRangeOperator">
                <div class="flex items-center gap-2">
                    <input
                        v-model="filterValue"
                        :type="inputType"
                        class="flex-1 text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500"
                        placeholder="Od"
                    />
                    <span class="text-gray-500 text-sm">-</span>
                    <input
                        v-model="filterValueEnd"
                        :type="inputType"
                        class="flex-1 text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500"
                        placeholder="Do"
                    />
                </div>
            </template>

            <!-- Standard input -->
            <template v-else>
                <input
                    v-model="filterValue"
                    :type="inputType"
                    class="w-full text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500"
                    placeholder="Wartość..."
                />
            </template>
        </div>

        <!-- Remove button -->
        <button
            type="button"
            @click="handleRemove"
            class="flex-shrink-0 p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition-colors"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</template>
