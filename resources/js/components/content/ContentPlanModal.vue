<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useContentPlanStore } from '@/stores/contentPlan';
import { useBrandsStore } from '@/stores/brands';
import { storeToRefs } from 'pinia';
import Modal from '@/components/common/Modal.vue';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'generated']);

const { t } = useI18n();
const contentPlanStore = useContentPlanStore();
const brandsStore = useBrandsStore();

const { currentBrand } = storeToRefs(brandsStore);
const { generatingPlan, error } = storeToRefs(contentPlanStore);

const period = ref('week');
const startDate = ref('');

const minDate = computed(() => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    return tomorrow.toISOString().split('T')[0];
});

const handleGenerate = async () => {
    if (!currentBrand.value) return;

    try {
        const result = await contentPlanStore.generatePlan(
            currentBrand.value.id,
            period.value,
            startDate.value || null
        );

        emit('generated', result);
        emit('close');
    } catch (e) {
        // Error is handled in store
    }
};

const handleClose = () => {
    if (!generatingPlan.value) {
        emit('close');
    }
};
</script>

<template>
    <Modal :show="show" max-width="md" @close="handleClose">
        <div class="text-center mb-6">
            <div class="mx-auto w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900">
                {{ t('contentPlan.generateTitle') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500">
                {{ t('contentPlan.generateDescription') }}
            </p>
        </div>

        <!-- Period selection -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                {{ t('contentPlan.period') }}
            </label>
            <div class="grid grid-cols-2 gap-3">
                <button
                    @click="period = 'week'"
                    class="px-4 py-3 rounded-lg border-2 text-sm font-medium transition-colors"
                    :class="{
                        'border-blue-500 bg-blue-50 text-blue-700': period === 'week',
                        'border-gray-200 hover:border-gray-300': period !== 'week',
                    }"
                    :disabled="generatingPlan"
                >
                    <div class="font-semibold">{{ t('contentPlan.week') }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ t('contentPlan.weekDescription') }}</div>
                </button>
                <button
                    @click="period = 'month'"
                    class="px-4 py-3 rounded-lg border-2 text-sm font-medium transition-colors"
                    :class="{
                        'border-blue-500 bg-blue-50 text-blue-700': period === 'month',
                        'border-gray-200 hover:border-gray-300': period !== 'month',
                    }"
                    :disabled="generatingPlan"
                >
                    <div class="font-semibold">{{ t('contentPlan.month') }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ t('contentPlan.monthDescription') }}</div>
                </button>
            </div>
        </div>

        <!-- Start date -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                {{ t('contentPlan.startDate') }}
                <span class="text-gray-400 font-normal">({{ t('common.optional') }})</span>
            </label>
            <input
                v-model="startDate"
                type="date"
                :min="minDate"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                :disabled="generatingPlan"
            />
            <p class="mt-1 text-xs text-gray-500">
                {{ t('contentPlan.startDateHint') }}
            </p>
        </div>

        <!-- Error message -->
        <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-sm text-red-600">{{ error }}</p>
        </div>

        <!-- Current brand info -->
        <div v-if="currentBrand" class="mb-6 p-3 bg-gray-50 rounded-lg">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm text-gray-600">
                    {{ t('contentPlan.generatingFor', { brand: currentBrand.name }) }}
                </span>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3">
            <button
                @click="handleClose"
                class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900"
                :disabled="generatingPlan"
            >
                {{ t('common.cancel') }}
            </button>
            <Button
                @click="handleGenerate"
                :loading="generatingPlan"
                :disabled="!currentBrand"
            >
                <template v-if="generatingPlan">
                    {{ t('contentPlan.generating') }}
                </template>
                <template v-else>
                    {{ t('contentPlan.generate') }}
                </template>
            </Button>
        </div>
    </Modal>
</template>
