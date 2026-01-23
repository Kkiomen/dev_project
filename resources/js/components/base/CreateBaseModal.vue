<script setup>
import { reactive, watch } from 'vue';
import Modal from '@/components/common/Modal.vue';
import Button from '@/components/common/Button.vue';
import Input from '@/components/common/Input.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    editingBase: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'save']);

const colors = [
    '#3B82F6', '#EF4444', '#22C55E', '#EAB308',
    '#8B5CF6', '#EC4899', '#06B6D4', '#F97316',
];

const icons = ['🗃', '📊', '📝', '⭐', '💼', '🔧', '🌟', '💡'];

const form = reactive({
    name: '',
    description: '',
    color: colors[0],
    icon: icons[0],
});

const loading = false;

watch(() => props.show, (show) => {
    if (show) {
        if (props.editingBase) {
            form.name = props.editingBase.name;
            form.description = props.editingBase.description || '';
            form.color = props.editingBase.color || colors[0];
            form.icon = props.editingBase.icon || icons[0];
        } else {
            form.name = '';
            form.description = '';
            form.color = colors[0];
            form.icon = icons[0];
        }
    }
});

const handleSubmit = () => {
    if (!form.name.trim()) return;
    emit('save', { ...form });
};
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">
            {{ editingBase ? 'Edytuj bazę' : 'Utwórz nową bazę' }}
        </h3>

        <form @submit.prevent="handleSubmit">
            <div class="space-y-4">
                <Input
                    v-model="form.name"
                    label="Nazwa"
                    placeholder="Np. Projekt CRM"
                    required
                />

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Opis (opcjonalnie)
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        placeholder="Krótki opis bazy danych..."
                    ></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Kolor
                    </label>
                    <div class="flex space-x-2">
                        <button
                            v-for="color in colors"
                            :key="color"
                            type="button"
                            @click="form.color = color"
                            class="w-8 h-8 rounded-lg transition-transform"
                            :class="{ 'ring-2 ring-offset-2 ring-gray-400 scale-110': form.color === color }"
                            :style="{ backgroundColor: color }"
                        ></button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Ikona
                    </label>
                    <div class="flex space-x-2">
                        <button
                            v-for="icon in icons"
                            :key="icon"
                            type="button"
                            @click="form.icon = icon"
                            class="w-10 h-10 rounded-lg border-2 text-xl flex items-center justify-center transition-all"
                            :class="form.icon === icon ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                        >
                            {{ icon }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <Button variant="secondary" @click="emit('close')">
                    Anuluj
                </Button>
                <Button type="submit" :loading="loading">
                    {{ editingBase ? 'Zapisz zmiany' : 'Utwórz bazę' }}
                </Button>
            </div>
        </form>
    </Modal>
</template>
