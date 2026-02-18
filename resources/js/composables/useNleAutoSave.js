import { watch, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVideoEditorStore } from '@/stores/videoEditorNew';
import { useToast } from '@/composables/useToast';

export function useNleAutoSave(delay = 2000) {
    const store = useVideoEditorStore();
    const { t } = useI18n();
    const toast = useToast();
    const lastSavedAt = ref(null);
    let timer = null;

    function scheduleSave() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(async () => {
            if (store.isDirty && store.composition && !store.saving) {
                await store.saveComposition();
                lastSavedAt.value = new Date();
            }
        }, delay);
    }

    const stopWatch = watch(
        () => store.isDirty,
        (dirty) => {
            if (dirty) scheduleSave();
        }
    );

    function saveNow() {
        if (timer) clearTimeout(timer);
        if (store.isDirty && store.composition && !store.saving) {
            store.saveComposition().then(() => {
                lastSavedAt.value = new Date();
                toast.success(t('nle.toolbar.saved'));
            });
        }
    }

    onUnmounted(() => {
        if (timer) clearTimeout(timer);
        stopWatch();
        // Save on unmount if dirty
        if (store.isDirty && store.composition && !store.saving) {
            store.saveComposition();
        }
    });

    return {
        lastSavedAt,
        saveNow,
    };
}
