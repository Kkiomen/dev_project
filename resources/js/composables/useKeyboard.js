import { ref, onMounted, onUnmounted } from 'vue';

export function useKeyboard(options = {}) {
    const {
        onArrowUp,
        onArrowDown,
        onArrowLeft,
        onArrowRight,
        onEnter,
        onEscape,
        onDelete,
        onTab,
        enabled = ref(true),
    } = options;

    const handleKeydown = (event) => {
        if (!enabled.value) return;

        // Don't handle if in input/textarea
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)) {
            if (event.key === 'Escape' && onEscape) {
                onEscape(event);
            }
            if (event.key === 'Enter' && onEnter) {
                onEnter(event);
            }
            if (event.key === 'Tab' && onTab) {
                onTab(event);
            }
            return;
        }

        switch (event.key) {
            case 'ArrowUp':
                event.preventDefault();
                onArrowUp?.(event);
                break;
            case 'ArrowDown':
                event.preventDefault();
                onArrowDown?.(event);
                break;
            case 'ArrowLeft':
                event.preventDefault();
                onArrowLeft?.(event);
                break;
            case 'ArrowRight':
                event.preventDefault();
                onArrowRight?.(event);
                break;
            case 'Enter':
                event.preventDefault();
                onEnter?.(event);
                break;
            case 'Escape':
                event.preventDefault();
                onEscape?.(event);
                break;
            case 'Delete':
            case 'Backspace':
                event.preventDefault();
                onDelete?.(event);
                break;
            case 'Tab':
                if (onTab) {
                    event.preventDefault();
                    onTab(event);
                }
                break;
        }
    };

    onMounted(() => {
        document.addEventListener('keydown', handleKeydown);
    });

    onUnmounted(() => {
        document.removeEventListener('keydown', handleKeydown);
    });

    return {
        handleKeydown,
    };
}
