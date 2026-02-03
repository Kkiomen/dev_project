<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoardsStore } from '@/stores/boards';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import Modal from '@/components/common/Modal.vue';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    card: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const { t } = useI18n();
const boardsStore = useBoardsStore();
const { confirm } = useConfirm();
const toast = useToast();

const saving = ref(false);
const form = ref({
    title: '',
    description: '',
    color: '',
    due_date: '',
    labels: [],
});
const newLabel = ref('');

watch(() => props.show, (val) => {
    if (val && props.card) {
        form.value = {
            title: props.card.title || '',
            description: props.card.description || '',
            color: props.card.color || '',
            due_date: props.card.due_date || '',
            labels: [...(props.card.labels || [])],
        };
    }
});

const handleSave = async () => {
    if (!form.value.title.trim()) return;

    saving.value = true;
    try {
        await boardsStore.updateCard(props.card.id, {
            ...form.value,
            color: form.value.color || null,
            due_date: form.value.due_date || null,
        });
        toast.success(t('boards.cardUpdated'));
        emit('close');
    } catch (error) {
        console.error('Failed to update card:', error);
    } finally {
        saving.value = false;
    }
};

const handleDelete = async () => {
    const confirmed = await confirm({
        title: t('boards.deleteCard'),
        message: t('boards.deleteCardConfirm', { title: props.card.title }),
        confirmText: t('common.delete'),
        variant: 'danger',
    });

    if (confirmed) {
        try {
            await boardsStore.deleteCard(props.card.id);
            toast.success(t('boards.cardDeleted'));
            emit('close');
        } catch (error) {
            console.error('Failed to delete card:', error);
        }
    }
};

const addLabel = () => {
    const label = newLabel.value.trim();
    if (label && !form.value.labels.includes(label)) {
        form.value.labels.push(label);
    }
    newLabel.value = '';
};

const removeLabel = (index) => {
    form.value.labels.splice(index, 1);
};

const cardColors = [
    '', '#EF4444', '#F59E0B', '#10B981',
    '#3B82F6', '#8B5CF6', '#EC4899',
];

const colorNames = {
    '': 'none',
    '#EF4444': 'red',
    '#F59E0B': 'amber',
    '#10B981': 'green',
    '#3B82F6': 'blue',
    '#8B5CF6': 'purple',
    '#EC4899': 'pink',
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="$emit('close')">
        <template v-if="card">
            <!-- Header -->
            <div class="flex items-start justify-between mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ t('boards.editCard') }}</h2>
                </div>
                <button
                    @click="$emit('close')"
                    class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="handleSave" class="space-y-5">
                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ t('boards.cardTitle') }}
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        :placeholder="t('boards.cardTitlePlaceholder')"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        required
                    />
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ t('boards.cardDescription') }}
                    </label>
                    <textarea
                        v-model="form.description"
                        :placeholder="t('boards.cardDescriptionPlaceholder')"
                        rows="4"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <!-- Due Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ t('boards.dueDate') }}
                            </span>
                        </label>
                        <input
                            v-model="form.due_date"
                            type="date"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        />
                    </div>

                    <!-- Color -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                                </svg>
                                {{ t('boards.cardColor') }}
                            </span>
                        </label>
                        <div class="flex gap-2.5">
                            <button
                                v-for="color in cardColors"
                                :key="color || 'none'"
                                type="button"
                                @click="form.color = color"
                                class="w-7 h-7 rounded-full transition-all duration-150 hover:scale-110 border-2"
                                :class="[
                                    form.color === color ? 'ring-2 ring-offset-2 ring-blue-500 scale-110' : '',
                                    !color ? 'border-gray-300 bg-white' : 'border-transparent',
                                ]"
                                :style="color ? { backgroundColor: color } : {}"
                                :title="colorNames[color] || ''"
                            >
                                <svg v-if="!color" class="w-4 h-4 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Labels -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            {{ t('boards.labels') }}
                        </span>
                    </label>
                    <div v-if="form.labels.length" class="flex flex-wrap gap-1.5 mb-3">
                        <span
                            v-for="(label, index) in form.labels"
                            :key="index"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-blue-50 text-blue-700 text-xs font-medium ring-1 ring-blue-200/50 group"
                        >
                            {{ label }}
                            <button
                                type="button"
                                @click="removeLabel(index)"
                                class="hover:text-blue-900 opacity-60 group-hover:opacity-100 transition-opacity"
                            >
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <input
                            v-model="newLabel"
                            type="text"
                            :placeholder="t('boards.addLabel')"
                            class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            @keyup.enter.prevent="addLabel"
                        />
                        <Button type="button" size="sm" variant="secondary" @click="addLabel">
                            {{ t('common.add') }}
                        </Button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                    <Button type="button" variant="danger" size="sm" @click="handleDelete">
                        <svg class="w-4 h-4 mr-1 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        {{ t('boards.deleteCard') }}
                    </Button>
                    <div class="flex gap-3">
                        <Button variant="secondary" @click="$emit('close')">
                            {{ t('common.cancel') }}
                        </Button>
                        <Button type="submit" :loading="saving">
                            {{ t('common.save') }}
                        </Button>
                    </div>
                </div>
            </form>
        </template>
    </Modal>
</template>
