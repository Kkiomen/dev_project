import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const isOpen = ref(false);
const title = ref('');
const message = ref('');
const confirmText = ref('');
const cancelText = ref('');
const variant = ref('danger'); // 'danger' | 'warning' | 'info'
let resolvePromise = null;

export function useConfirm() {
    // Get i18n instance - will work in component context
    let t = (key) => key;
    try {
        const i18n = useI18n();
        t = i18n.t;
    } catch (e) {
        // Fallback if called outside component
    }

    const confirm = (options) => {
        return new Promise((resolve) => {
            if (typeof options === 'string') {
                message.value = options;
                title.value = t('common.confirmAction');
                confirmText.value = t('common.confirm');
                cancelText.value = t('common.cancel');
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
