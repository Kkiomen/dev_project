<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    modelValue: { type: String, default: null },
    isOverdue: { type: Boolean, default: false },
    isDueSoon: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();
const isEditing = ref(false);
const inputRef = ref(null);

const localDate = ref(props.modelValue ? props.modelValue.split('T')[0] : '');

watch(() => props.modelValue, (newValue) => {
    localDate.value = newValue ? newValue.split('T')[0] : '';
});

const formattedDate = computed(() => {
    if (!props.modelValue) return null;

    const date = new Date(props.modelValue);
    const now = new Date();
    const diffDays = Math.floor((date - now) / (1000 * 60 * 60 * 24));

    if (diffDays < 0) {
        return t('devTasks.dueDate.overdueDays', { days: Math.abs(diffDays) });
    } else if (diffDays === 0) {
        return t('devTasks.dueDate.today');
    } else if (diffDays === 1) {
        return t('devTasks.dueDate.tomorrow');
    } else if (diffDays <= 7) {
        return t('devTasks.dueDate.inDays', { days: diffDays });
    } else {
        return date.toLocaleDateString();
    }
});

const statusClass = computed(() => {
    if (props.isOverdue) {
        return 'bg-red-100 text-red-700 border-red-200';
    } else if (props.isDueSoon) {
        return 'bg-amber-100 text-amber-700 border-amber-200';
    } else if (props.modelValue) {
        return 'bg-gray-100 text-gray-700 border-gray-200';
    }
    return 'bg-gray-50 text-gray-500 border-gray-200 border-dashed';
});

const iconColor = computed(() => {
    if (props.isOverdue) return 'text-red-500';
    if (props.isDueSoon) return 'text-amber-500';
    return 'text-gray-400';
});

const handleEdit = () => {
    isEditing.value = true;
    setTimeout(() => {
        inputRef.value?.focus();
        inputRef.value?.showPicker?.();
    }, 50);
};

const handleSave = () => {
    const newDate = localDate.value ? `${localDate.value}T23:59:59.000Z` : null;
    emit('update:modelValue', newDate);
    isEditing.value = false;
};

const handleClear = () => {
    localDate.value = '';
    emit('update:modelValue', null);
    isEditing.value = false;
};

const handleCancel = () => {
    localDate.value = props.modelValue ? props.modelValue.split('T')[0] : '';
    isEditing.value = false;
};
</script>

<template>
    <div class="due-date-picker">
        <label class="block text-xs font-medium text-gray-500 mb-1.5">
            {{ t('devTasks.dueDate.label') }}
        </label>

        <!-- Display mode -->
        <div
            v-if="!isEditing"
            @click="handleEdit"
            class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors hover:border-gray-300"
            :class="statusClass"
        >
            <svg class="w-4 h-4 flex-shrink-0" :class="iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>

            <span v-if="modelValue" class="text-sm font-medium">
                {{ formattedDate }}
            </span>
            <span v-else class="text-sm">
                {{ t('devTasks.dueDate.setDueDate') }}
            </span>

            <svg v-if="isOverdue" class="w-4 h-4 ml-auto text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
        </div>

        <!-- Edit mode -->
        <div v-else class="space-y-2">
            <input
                ref="inputRef"
                v-model="localDate"
                type="date"
                class="w-full text-sm border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
                @keyup.enter="handleSave"
                @keyup.escape="handleCancel"
            />
            <div class="flex items-center gap-2">
                <button
                    @click="handleSave"
                    class="flex-1 px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                >
                    {{ t('common.save') }}
                </button>
                <button
                    v-if="modelValue"
                    @click="handleClear"
                    class="px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                >
                    {{ t('common.clear') }}
                </button>
                <button
                    @click="handleCancel"
                    class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    {{ t('common.cancel') }}
                </button>
            </div>
        </div>
    </div>
</template>
