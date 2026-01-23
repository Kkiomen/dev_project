import { ref } from 'vue';

const isOpen = ref(false);
const title = ref('');
const message = ref('');
const confirmText = ref('Potwierdź');
const cancelText = ref('Anuluj');
const variant = ref('danger'); // 'danger' | 'warning' | 'info'
let resolvePromise = null;

export function useConfirm() {
    const confirm = (options) => {
        return new Promise((resolve) => {
            if (typeof options === 'string') {
                message.value = options;
                title.value = 'Potwierdzenie';
            } else {
                title.value = options.title || 'Potwierdzenie';
                message.value = options.message || '';
                confirmText.value = options.confirmText || 'Potwierdź';
                cancelText.value = options.cancelText || 'Anuluj';
                variant.value = options.variant || 'danger';
            }

            resolvePromise = resolve;
            isOpen.value = true;
        });
    };

    const handleConfirm = () => {
        isOpen.value = false;
        if (resolvePromise) {
            resolvePromise(true);
            resolvePromise = null;
        }
    };

    const handleCancel = () => {
        isOpen.value = false;
        if (resolvePromise) {
            resolvePromise(false);
            resolvePromise = null;
        }
    };

    return {
        isOpen,
        title,
        message,
        confirmText,
        cancelText,
        variant,
        confirm,
        handleConfirm,
        handleCancel,
    };
}
