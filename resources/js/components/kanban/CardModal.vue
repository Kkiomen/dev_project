<script setup>
import Modal from '@/components/common/Modal.vue';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    card: {
        type: Object,
        default: null,
    },
    fields: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'update-field', 'delete']);

const getFieldValue = (fieldId) => {
    return props.card?.values?.[fieldId] ?? null;
};

const handleUpdate = (fieldId, event) => {
    let value = event.target.value;

    const field = props.fields.find(f => f.id === fieldId);
    if (field?.type === 'number') {
        value = value ? parseFloat(value) : null;
    } else if (field?.type === 'checkbox') {
        value = event.target.checked;
    } else if (value === '') {
        value = null;
    }

    emit('update-field', fieldId, value);
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">Szczegoly rekordu</h3>
            <button @click="emit('close')" class="text-gray-400 hover:text-gray-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="space-y-4 max-h-[60vh] overflow-y-auto">
            <div v-for="field in fields" :key="field.id">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ field.name }}
                </label>

                <!-- Text/URL input -->
                <input
                    v-if="field.type === 'text' || field.type === 'url'"
                    type="text"
                    :value="getFieldValue(field.id) || ''"
                    @change="handleUpdate(field.id, $event)"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                />

                <!-- Number input -->
                <input
                    v-else-if="field.type === 'number'"
                    type="number"
                    :value="getFieldValue(field.id) || ''"
                    @change="handleUpdate(field.id, $event)"
                    step="any"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                />

                <!-- Date input -->
                <input
                    v-else-if="field.type === 'date'"
                    type="datetime-local"
                    :value="(getFieldValue(field.id) || '').replace(' ', 'T').substring(0, 16)"
                    @change="handleUpdate(field.id, $event)"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                />

                <!-- Checkbox -->
                <input
                    v-else-if="field.type === 'checkbox'"
                    type="checkbox"
                    :checked="getFieldValue(field.id)"
                    @change="handleUpdate(field.id, $event)"
                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                />

                <!-- Select -->
                <select
                    v-else-if="field.type === 'select'"
                    @change="handleUpdate(field.id, $event)"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                >
                    <option value="">-- Wybierz --</option>
                    <option
                        v-for="choice in (field.options?.choices || [])"
                        :key="choice.id"
                        :value="choice.id"
                        :selected="getFieldValue(field.id) === choice.id"
                    >
                        {{ choice.name }}
                    </option>
                </select>

                <!-- Read-only for other types -->
                <div v-else class="text-sm text-gray-500">
                    {{ JSON.stringify(getFieldValue(field.id)) || '-' }}
                </div>
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-between">
            <Button variant="danger" @click="emit('delete')">
                Usun rekord
            </Button>
            <Button @click="emit('close')">
                Zamknij
            </Button>
        </div>
    </Modal>
</template>
