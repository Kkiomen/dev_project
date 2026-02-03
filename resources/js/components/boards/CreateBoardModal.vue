<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoardsStore } from '@/stores/boards';
import Modal from '@/components/common/Modal.vue';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    board: { type: Object, default: null },
});

const emit = defineEmits(['close', 'created']);

const { t } = useI18n();
const boardsStore = useBoardsStore();

const saving = ref(false);
const form = ref({
    name: '',
    description: '',
    color: '#3B82F6',
});

const colors = [
    { value: '#3B82F6', name: 'Blue' },
    { value: '#EF4444', name: 'Red' },
    { value: '#10B981', name: 'Green' },
    { value: '#F59E0B', name: 'Amber' },
    { value: '#8B5CF6', name: 'Purple' },
    { value: '#EC4899', name: 'Pink' },
    { value: '#06B6D4', name: 'Cyan' },
    { value: '#6B7280', name: 'Gray' },
];

watch(() => props.show, (val) => {
    if (val && props.board) {
        form.value = {
            name: props.board.name || '',
            description: props.board.description || '',
            color: props.board.color || '#3B82F6',
        };
    } else if (val) {
        form.value = { name: '', description: '', color: '#3B82F6' };
    }
});

const handleSubmit = async () => {
    if (!form.value.name.trim()) return;

    saving.value = true;
    try {
        let result;
        if (props.board) {
            result = await boardsStore.updateBoard(props.board.id, form.value);
        } else {
            result = await boardsStore.createBoard(form.value);
        }
        emit('created', result);
    } catch (error) {
        console.error('Failed to save board:', error);
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Modal :show="show" max-width="md" @close="$emit('close')">
        <!-- Header -->
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
                <div
                    class="w-9 h-9 rounded-lg flex items-center justify-center transition-colors"
                    :style="{ backgroundColor: form.color + '18' }"
                >
                    <svg class="w-4.5 h-4.5" :style="{ color: form.color }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ board ? t('boards.editBoard') : t('boards.newBoard') }}
                </h2>
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

        <form @submit.prevent="handleSubmit" class="space-y-5">
            <!-- Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    {{ t('boards.boardName') }}
                </label>
                <input
                    v-model="form.name"
                    type="text"
                    :placeholder="t('boards.boardNamePlaceholder')"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    required
                />
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    {{ t('boards.boardDescription') }}
                </label>
                <textarea
                    v-model="form.description"
                    :placeholder="t('boards.boardDescriptionPlaceholder')"
                    rows="3"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                />
            </div>

            <!-- Color -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2.5">
                    {{ t('boards.boardColor') }}
                </label>
                <div class="flex gap-3">
                    <button
                        v-for="color in colors"
                        :key="color.value"
                        type="button"
                        @click="form.color = color.value"
                        class="w-9 h-9 rounded-xl transition-all duration-150 hover:scale-110 relative group"
                        :class="form.color === color.value ? 'ring-2 ring-offset-2 ring-blue-500 scale-110' : 'hover:shadow-md'"
                        :style="{ backgroundColor: color.value }"
                        :title="color.name"
                    >
                        <svg
                            v-if="form.color === color.value"
                            class="w-4 h-4 text-white absolute inset-0 m-auto drop-shadow"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Preview -->
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                <div class="flex items-center gap-2.5">
                    <div
                        class="h-1.5 w-12 rounded-full transition-colors"
                        :style="{ backgroundColor: form.color }"
                    />
                    <span class="text-sm font-medium text-gray-700 truncate">
                        {{ form.name || t('boards.boardNamePlaceholder') }}
                    </span>
                </div>
                <p v-if="form.description" class="text-xs text-gray-400 mt-1.5 ml-[3.625rem] line-clamp-1">
                    {{ form.description }}
                </p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-2">
                <Button variant="secondary" @click="$emit('close')">
                    {{ t('common.cancel') }}
                </Button>
                <Button type="submit" :loading="saving" :disabled="!form.name.trim()">
                    {{ board ? t('common.save') : t('common.create') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>
