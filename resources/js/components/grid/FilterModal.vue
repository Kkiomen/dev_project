<script setup>
import { ref, computed, watch } from 'vue';
import { useFiltersStore } from '@/stores/filters';
import { getOperatorsForFieldType, operators, getDefaultOperator } from '@/config/filterOperators';
import Modal from '@/components/common/Modal.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    fields: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['close', 'apply']);

const filtersStore = useFiltersStore();

// Local state for editing
const localConditions = ref([]);
const localConjunction = ref('and');

// Initialize local state when modal opens
watch(() => props.show, (isOpen) => {
    if (isOpen) {
        localConditions.value = JSON.parse(JSON.stringify(filtersStore.conditions));
        localConjunction.value = filtersStore.conjunction;
        if (localConditions.value.length === 0) {
            addCondition();
        }
    }
});

// Computed
const filterableFields = computed(() => {
    return props.fields.filter(f => f.type !== 'attachment');
});

const hasConditions = computed(() => localConditions.value.length > 0);

// Methods
const addCondition = () => {
    if (filterableFields.value.length === 0) return;
    const firstField = filterableFields.value[0];
    localConditions.value.push({
        id: crypto.randomUUID(),
        field_id: firstField.id,
        field_type: firstField.type,
        operator: getDefaultOperator(firstField.type),
        value: null,
        value_end: null,
    });
};

const removeCondition = (index) => {
    localConditions.value.splice(index, 1);
};

const updateConditionField = (index, fieldId) => {
    const field = filterableFields.value.find(f => f.id === fieldId);
    if (field) {
        localConditions.value[index].field_id = fieldId;
        localConditions.value[index].field_type = field.type;
        localConditions.value[index].operator = getDefaultOperator(field.type);
        localConditions.value[index].value = null;
        localConditions.value[index].value_end = null;
    }
};

const getFieldById = (fieldId) => {
    return filterableFields.value.find(f => f.id === fieldId);
};

const getAvailableOperators = (fieldType) => {
    return getOperatorsForFieldType(fieldType);
};

const getOperatorConfig = (operatorValue) => {
    return operators[operatorValue] || {};
};

const getInputType = (fieldType) => {
    switch (fieldType) {
        case 'number': return 'number';
        case 'date': return 'date';
        case 'datetime': return 'datetime-local';
        default: return 'text';
    }
};

const clearAll = () => {
    localConditions.value = [];
    addCondition();
};

const applyFilters = () => {
    // Filter out empty conditions
    const validConditions = localConditions.value.filter(c => {
        const op = getOperatorConfig(c.operator);
        if (!op.requiresValue) return true;
        if (op.isRange) return c.value !== null && c.value_end !== null;
        return c.value !== null && c.value !== '';
    });

    // Update store
    filtersStore.conditions = validConditions.map(c => {
        const result = {
            id: c.id,
            field_id: c.field_id,
            field_type: c.field_type,
            operator: c.operator,
            value: c.value,
        };
        const op = getOperatorConfig(c.operator);
        if (op.isRange && c.value_end !== null) {
            result.value = [c.value, c.value_end];
        }
        return result;
    });
    filtersStore.conjunction = localConjunction.value;

    emit('apply');
    emit('close');
};

const close = () => {
    emit('close');
};

const toggleChoice = (condition, choiceId) => {
    if (!Array.isArray(condition.value)) {
        condition.value = [];
    }
    const index = condition.value.indexOf(choiceId);
    if (index === -1) {
        condition.value.push(choiceId);
    } else {
        condition.value.splice(index, 1);
    }
};

const isChoiceSelected = (condition, choiceId) => {
    return Array.isArray(condition.value) && condition.value.includes(choiceId);
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="close">
        <div class="flex flex-col max-h-[80vh]">
            <!-- Header -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Filtruj dane</h2>
                        <p class="text-sm text-gray-500">Dodaj warunki aby przefiltrować widoczne rekordy</p>
                    </div>
                </div>
                <button
                    @click="close"
                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Conjunction selector -->
            <div v-if="localConditions.length > 1" class="py-3 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-gray-700">Pokaż rekordy pasujące do:</span>
                    <div class="flex bg-gray-100 rounded-lg p-1">
                        <button
                            @click="localConjunction = 'and'"
                            class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors"
                            :class="localConjunction === 'and'
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-600 hover:text-gray-900'"
                        >
                            Wszystkich warunków
                        </button>
                        <button
                            @click="localConjunction = 'or'"
                            class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors"
                            :class="localConjunction === 'or'
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-600 hover:text-gray-900'"
                        >
                            Dowolnego warunku
                        </button>
                    </div>
                </div>
            </div>

            <!-- Conditions list -->
            <div class="flex-1 overflow-y-auto py-4 space-y-3">
                <TransitionGroup name="list">
                    <div
                        v-for="(condition, index) in localConditions"
                        :key="condition.id"
                        class="bg-gray-50 rounded-lg p-4 relative group"
                    >
                        <!-- Row number -->
                        <div class="absolute -left-2 top-4 w-6 h-6 bg-blue-100 text-blue-600 rounded-full text-xs font-medium flex items-center justify-center">
                            {{ index + 1 }}
                        </div>

                        <div class="grid grid-cols-12 gap-3 items-start ml-4">
                            <!-- Field selector -->
                            <div class="col-span-4">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Pole</label>
                                <select
                                    :value="condition.field_id"
                                    @change="updateConditionField(index, $event.target.value)"
                                    class="w-full text-sm border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option
                                        v-for="field in filterableFields"
                                        :key="field.id"
                                        :value="field.id"
                                    >
                                        {{ field.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Operator selector -->
                            <div class="col-span-3">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Warunek</label>
                                <select
                                    v-model="condition.operator"
                                    class="w-full text-sm border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option
                                        v-for="op in getAvailableOperators(condition.field_type)"
                                        :key="op.value"
                                        :value="op.value"
                                    >
                                        {{ op.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Value input -->
                            <div class="col-span-4">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Wartość</label>

                                <!-- No value needed -->
                                <div
                                    v-if="!getOperatorConfig(condition.operator).requiresValue"
                                    class="h-[38px] flex items-center text-sm text-gray-400 italic"
                                >
                                    —
                                </div>

                                <!-- Select/Multi-select choices with multiple selection -->
                                <template v-else-if="['select', 'multi_select'].includes(condition.field_type) && getOperatorConfig(condition.operator).isMultiple">
                                    <div class="flex flex-wrap gap-1 p-2 bg-white border border-gray-300 rounded-lg min-h-[38px]">
                                        <button
                                            v-for="choice in getFieldById(condition.field_id)?.options?.choices || []"
                                            :key="choice.id"
                                            type="button"
                                            @click="toggleChoice(condition, choice.id)"
                                            class="px-2 py-0.5 text-xs rounded-full border transition-colors"
                                            :class="isChoiceSelected(condition, choice.id)
                                                ? 'border-blue-500 bg-blue-50 text-blue-700'
                                                : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300'"
                                        >
                                            {{ choice.name }}
                                        </button>
                                    </div>
                                </template>

                                <!-- Single select choice -->
                                <template v-else-if="['select', 'multi_select'].includes(condition.field_type)">
                                    <select
                                        v-model="condition.value"
                                        class="w-full text-sm border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
                                    >
                                        <option :value="null">-- wybierz --</option>
                                        <option
                                            v-for="choice in getFieldById(condition.field_id)?.options?.choices || []"
                                            :key="choice.id"
                                            :value="choice.id"
                                        >
                                            {{ choice.name }}
                                        </option>
                                    </select>
                                </template>

                                <!-- Range input (between operator) -->
                                <template v-else-if="getOperatorConfig(condition.operator).isRange">
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="condition.value"
                                            :type="getInputType(condition.field_type)"
                                            class="flex-1 text-sm border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Od"
                                        />
                                        <span class="text-gray-400">—</span>
                                        <input
                                            v-model="condition.value_end"
                                            :type="getInputType(condition.field_type)"
                                            class="flex-1 text-sm border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Do"
                                        />
                                    </div>
                                </template>

                                <!-- Standard input -->
                                <template v-else>
                                    <input
                                        v-model="condition.value"
                                        :type="getInputType(condition.field_type)"
                                        class="w-full text-sm border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="Wpisz wartość..."
                                    />
                                </template>
                            </div>

                            <!-- Remove button -->
                            <div class="col-span-1 flex justify-end">
                                <button
                                    @click="removeCondition(index)"
                                    class="mt-6 p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                                    :class="{ 'opacity-50 cursor-not-allowed': localConditions.length === 1 }"
                                    :disabled="localConditions.length === 1"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- Add condition button -->
                <button
                    @click="addCondition"
                    class="w-full py-3 border-2 border-dashed border-gray-300 rounded-lg text-sm text-gray-500 hover:text-blue-600 hover:border-blue-300 hover:bg-blue-50 transition-colors flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Dodaj kolejny warunek
                </button>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                <button
                    @click="clearAll"
                    class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    Wyczyść wszystko
                </button>
                <div class="flex gap-3">
                    <button
                        @click="close"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Anuluj
                    </button>
                    <button
                        @click="applyFilters"
                        class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Zastosuj filtry
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.list-enter-active,
.list-leave-active {
    transition: all 0.3s ease;
}
.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateX(-30px);
}
</style>
