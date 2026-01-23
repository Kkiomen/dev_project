import { ref, getCurrentInstance } from 'vue';

const isOpen = ref(false);
const title = ref('');
const message = ref('');
const confirmText = ref('');
const cancelText = ref('');
const variant = ref('danger'); // 'danger' | 'warning' | 'info'
let resolvePromise = null;

export function useConfirm() {
    const getT = () => {
        const instance = getCurrentInstance();
        if (instance) {
            return instance.appContext.config.globalProperties.$t;
        }
        return (key, params) => key;
    };
    
    const confirm = (options) => {
        const t = getT();
        return new Promise((resolve) => {
            if (typeof options === 'string') {
                message.value = options;
                title.value = t('common.confirmAction');
            } else {
                title.value = options.title || t('common.confirmAction');
                message.value = options.message || '';
                confirmText.value = options.confirmText || t('common.confirm');
                cancelText.value = options.cancelText || t('common.cancel');
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
