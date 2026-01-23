<script setup>
import { ref, reactive, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
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

const { t } = useI18n();

const fieldTypes = computed(() => [
    { value: 'text', icon: 'Aa', label: t('field.types.text'), description: t('field.typeDescriptions.text'), example: t('field.typeExamples.text') },
    { value: 'number', icon: '#', label: t('field.types.number'), description: t('field.typeDescriptions.number'), example: t('field.typeExamples.number') },
    { value: 'date', icon: '📅', label: t('field.types.date'), description: t('field.typeDescriptions.date'), example: t('field.typeExamples.date') },
    { value: 'datetime', icon: '🕐', label: t('field.types.datetime'), description: t('field.typeDescriptions.datetime'), example: t('field.typeExamples.datetime') },
    { value: 'checkbox', icon: '☑️', label: t('field.types.checkbox'), description: t('field.typeDescriptions.checkbox'), example: t('field.typeExamples.checkbox') },
    { value: 'select', icon: '▼', label: t('field.types.select'), description: t('field.typeDescriptions.select'), example: t('field.typeExamples.select') },
    { value: 'multi_select', icon: '≡', label: t('field.types.multi_select'), description: t('field.typeDescriptions.multi_select'), example: t('field.typeExamples.multi_select') },
    { value: 'attachment', icon: '📎', label: t('field.types.attachment'), description: t('field.typeDescriptions.attachment'), example: t('field.typeExamples.attachment') },
    { value: 'url', icon: '🔗', label: t('field.types.url'), description: t('field.typeDescriptions.url'), example: t('field.typeExamples.url') },
    { value: 'json', icon: '{ }', label: t('field.types.json'), description: t('field.typeDescriptions.json'), example: t('field.typeExamples.json') },
]);

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
            {{ editingField ? t('field.edit') : t('field.add') }}
        </h3>

        <form @submit.prevent="handleSubmit">
            <!-- Field name -->
            <div class="mb-6">
                <Input
                    v-model="form.name"
                    :label="t('field.name')"
                    :placeholder="t('field.namePlaceholder')"
                    required
                />
            </div>

            <!-- Field type selection -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">{{ t('field.type') }}</label>
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
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('field.options') }}</label>
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
                            :placeholder="t('field.optionName')"
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
                    {{ t('field.addOption') }}
                </button>
            </div>

            <div class="flex justify-end space-x-3">
                <Button variant="secondary" @click="emit('close')">
                    {{ t('common.cancel') }}
                </Button>
                <Button type="submit">
                    {{ editingField ? t('field.saveChanges') : t('field.addField') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>
