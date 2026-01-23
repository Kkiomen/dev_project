<script setup>
import { useConfirm } from '@/composables/useConfirm';
import Modal from './Modal.vue';
import Button from './Button.vue';

const {
    isOpen,
    title,
    message,
    confirmText,
    cancelText,
    variant,
    handleConfirm,
    handleCancel,
} = useConfirm();

const getVariantClasses = () => {
    switch (variant.value) {
        case 'danger':
            return 'bg-red-600 hover:bg-red-700 focus:ring-red-500';
        case 'warning':
            return 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500';
        case 'info':
            return 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500';
        default:
            return 'bg-red-600 hover:bg-red-700 focus:ring-red-500';
    }
};

const getIcon = () => {
    switch (variant.value) {
        case 'danger':
            return '⚠️';
        case 'warning':
            return '⚡';
        case 'info':
            return 'ℹ️';
        default:
            return '⚠️';
    }
};
</script>

<template>
    <Modal :show="isOpen" max-width="sm" @close="handleCancel">
        <div class="sm:flex sm:items-start">
            <div
                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10"
                :class="{
                    'bg-red-100': variant === 'danger',
                    'bg-yellow-100': variant === 'warning',
                    'bg-blue-100': variant === 'info',
                }"
            >
                <span class="text-xl">{{ getIcon() }}</span>
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    {{ title }}
                </h3>
                <div class="mt-2">
                    <p class="text-sm text-gray-500">
                        {{ message }}
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-3">
            <button
                type="button"
                :class="getVariantClasses()"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 sm:w-auto sm:text-sm"
                @click="handleConfirm"
            >
                {{ confirmText }}
            </button>
            <button
                type="button"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm"
                @click="handleCancel"
            >
                {{ cancelText }}
            </button>
        </div>
    </Modal>
</template>
