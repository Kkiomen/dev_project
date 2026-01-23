<script setup>
import { ref, reactive, watch } from 'vue';
import Modal from '@/components/common/Modal.vue';
import Button from '@/components/common/Button.vue';
import Input from '@/components/common/Input.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    editingField: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'save']);

const fieldTypes = [
    { value: 'text', icon: 'Aa', label: 'Tekst', description: 'Jednoliniowy tekst', example: 'Jan Kowalski' },
    { value: 'number', icon: '#', label: 'Liczba', description: 'Liczby', example: '42, 3.14' },
    { value: 'date', icon: '📅', label: 'Data', description: 'Tylko data', example: '2024-01-15' },
    { value: 'datetime', icon: '🕐', label: 'Data i czas', description: 'Data z godziną', example: '2024-01-15 14:30' },
    { value: 'checkbox', icon: '☑️', label: 'Checkbox', description: 'Tak/Nie', example: '✓ lub puste' },
    { value: 'select', icon: '▼', label: 'Wybór', description: 'Jedna opcja', example: 'Status: Nowy' },
    { value: 'multi_select', icon: '≡', label: 'Multi-wybór', description: 'Wiele opcji', example: 'Tagi: A, B' },
    { value: 'attachment', icon: '📎', label: 'Załącznik', description: 'Pliki', example: 'foto.jpg' },
    { value: 'url', icon: '🔗', label: 'URL', description: 'Link', example: 'https://...' },
    { value: 'json', icon: '{ }', label: 'JSON', description: 'Dane JSON', example: '{"key": "val"}' },
];

const colors = ['#EF4444', '#F97316', '#EAB308', '#22C55E', '#06B6D4', '#3B82F6', '#8B5CF6', '#EC4899'];

const form = reactive({
    name: '',
    type: 'text',
    choices: [],
});

watch(() => props.show, (show) => {
    if (show) {
        if (props.editingField) {
            form.name = props.editingField.name;
            form.type = props.editingField.type;
            form.choices = [...(props.editingField.options?.choices || [])];
        } else {
            form.name = '';
            form.type = 'text';
            form.choices = [];
        }
    }
});

const addChoice = () => {
    form.choices.push({
        id: 'new_' + Date.now(),
        name: '',
        color: colors[form.choices.length % colors.length],
    });
};

const removeChoice = (index) => {
    form.choices.splice(index, 1);
};

const handleSubmit = () => {
    if (!form.name.trim()) return;

    const payload = {
        name: form.name,
        type: form.type,
    };

    if (form.type === 'select' || form.type === 'multi_select') {
        payload.options = {
            choices: form.choices
                .filter(c => c.name.trim())
                .map(c => ({
                    id: c.id.startsWith('new_') ? undefined : c.id,
                    name: c.name,
                    color: c.color,
                })),
        };
    }

    emit('save', payload);
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">
            {{ editingField ? 'Edytuj pole' : 'Dodaj nowe pole' }}
        </h3>

        <form @submit.prevent="handleSubmit">
            <!-- Field name -->
            <div class="mb-6">
                <Input
                    v-model="form.name"
                    label="Nazwa pola"
                    placeholder="Np. Imię i nazwisko"
                    required
                />
            </div>

            <!-- Field type selection -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">Wybierz typ pola</label>
                <div class="grid grid-cols-3 gap-3">
                    <div
                        v-for="ft in fieldTypes"
                        :key="ft.value"
                        @click="form.type = ft.value"
                        :class="[
                            form.type === ft.value
                                ? 'ring-2 ring-blue-500 border-blue-500 bg-blue-50'
                                : 'border-gray-200 hover:border-gray-300',
                        ]"
                        class="relative border rounded-lg p-4 cursor-pointer transition-all"
                    >
                        <div class="flex flex-col items-center text-center">
                            <span class="text-2xl mb-2">{{ ft.icon }}</span>
                            <span class="text-sm font-medium text-gray-900">{{ ft.label }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Select/Multi-select options -->
            <div v-if="form.type === 'select' || form.type === 'multi_select'" class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Opcje wyboru</label>
                <div class="space-y-2 mb-3">
                    <div
                        v-for="(choice, index) in form.choices"
                        :key="index"
                        class="flex items-center space-x-2"
                    >
                        <input
                            type="color"
                            v-model="choice.color"
                            class="w-8 h-8 rounded border-0 cursor-pointer"
                        />
                        <input
                            type="text"
                            v-model="choice.name"
                            placeholder="Nazwa opcji"
                            class="flex-1 rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                        <button
                            type="button"
                            @click="removeChoice(index)"
                            class="text-gray-400 hover:text-red-500"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <button
                    type="button"
                    @click="addChoice"
                    class="text-sm text-blue-600 hover:text-blue-700"
                >
                    + Dodaj opcję
                </button>
            </div>

            <div class="flex justify-end space-x-3">
                <Button variant="secondary" @click="emit('close')">
                    Anuluj
                </Button>
                <Button type="submit">
                    {{ editingField ? 'Zapisz zmiany' : 'Dodaj pole' }}
                </Button>
            </div>
        </form>
    </Modal>
</template>
