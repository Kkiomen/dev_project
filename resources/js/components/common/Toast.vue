<script setup>
import { useToast } from '@/composables/useToast';

const { toasts, remove } = useToast();

const getIcon = (type) => {
    switch (type) {
        case 'success':
            return '✓';
        case 'error':
            return '✕';
        case 'info':
            return 'ℹ';
        default:
            return '•';
    }
};

const getStyles = (type) => {
    switch (type) {
        case 'success':
            return { backgroundColor: '#22c55e', color: '#ffffff' };
        case 'error':
            return { backgroundColor: '#ef4444', color: '#ffffff' };
        case 'info':
            return { backgroundColor: '#3b82f6', color: '#ffffff' };
        default:
            return { backgroundColor: '#374151', color: '#ffffff' };
    }
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed bottom-4 right-4 z-50 flex flex-col space-y-2">
            <transition-group name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    :style="getStyles(toast.type)"
                    class="px-4 py-2 rounded-lg shadow-lg flex items-center space-x-2 text-sm font-medium cursor-pointer transform transition-all duration-300"
                    @click="remove(toast.id)"
                >
                    <span class="w-5 h-5 flex items-center justify-center">{{ getIcon(toast.type) }}</span>
                    <span>{{ toast.message }}</span>
                </div>
            </transition-group>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from {
    opacity: 0;
    transform: translateX(100px);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(100px);
}
</style>
