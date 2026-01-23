<script setup>
import { ref, watch, computed } from 'vue';
import Modal from '@/components/common/Modal.vue';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    value: {
        type: [Object, Array, String],
        default: null,
    },
});

const emit = defineEmits(['close', 'save']);

const jsonText = ref('');
const error = ref('');

const isValid = computed(() => {
    if (!jsonText.value.trim()) return true;
    try {
        JSON.parse(jsonText.value);
        return true;
    } catch {
        return false;
    }
});

watch(() => props.show, (show) => {
    if (show) {
        error.value = '';
        if (props.value === null || props.value === undefined) {
            jsonText.value = '';
        } else if (typeof props.value === 'string') {
            try {
                const parsed = JSON.parse(props.value);
                jsonText.value = JSON.stringify(parsed, null, 2);
            } catch {
                jsonText.value = props.value;
            }
        } else {
            jsonText.value = JSON.stringify(props.value, null, 2);
        }
    }
});

const formatJson = () => {
    try {
        const parsed = JSON.parse(jsonText.value);
        jsonText.value = JSON.stringify(parsed, null, 2);
        error.value = '';
    } catch (e) {
        error.value = 'Nieprawidłowy JSON: ' + e.message;
    }
};

const minifyJson = () => {
    try {
        const parsed = JSON.parse(jsonText.value);
        jsonText.value = JSON.stringify(parsed);
        error.value = '';
    } catch (e) {
        error.value = 'Nieprawidłowy JSON: ' + e.message;
    }
};

const handleSave = () => {
    if (!jsonText.value.trim()) {
        emit('save', null);
        return;
    }

    try {
        const parsed = JSON.parse(jsonText.value);
        emit('save', parsed);
    } catch (e) {
        error.value = 'Nieprawidłowy JSON: ' + e.message;
    }
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Edytuj JSON</h3>
            <div class="flex items-center space-x-2">
                <button
                    type="button"
                    @click="formatJson"
                    class="px-2 py-1 text-xs text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded"
                >
                    Formatuj
                </button>
                <button
                    type="button"
                    @click="minifyJson"
                    class="px-2 py-1 text-xs text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded"
                >
                    Minimalizuj
                </button>
            </div>
        </div>

        <div class="mb-4">
            <textarea
                v-model="jsonText"
                rows="15"
                class="w-full px-3 py-2 font-mono text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': !isValid && jsonText.trim() }"
                placeholder='{"key": "value"}'
                spellcheck="false"
            ></textarea>
            <p v-if="error" class="mt-1 text-sm text-red-500">{{ error }}</p>
            <p v-else-if="!isValid && jsonText.trim()" class="mt-1 text-sm text-red-500">
                Nieprawidłowa składnia JSON
            </p>
        </div>

        <div class="flex justify-end space-x-3">
            <Button variant="secondary" @click="emit('close')">
                Anuluj
            </Button>
            <Button @click="handleSave" :disabled="!isValid">
                Zapisz
            </Button>
        </div>
    </Modal>
</template>
