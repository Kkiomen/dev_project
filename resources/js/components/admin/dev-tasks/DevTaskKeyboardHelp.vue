<script setup>
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const { t } = useI18n();

const shortcuts = [
    { category: 'navigation', items: [
        { keys: ['j'], action: 'nextTask' },
        { keys: ['k'], action: 'prevTask' },
        { keys: ['Enter'], action: 'openTask' },
        { keys: ['Escape'], action: 'closePanel' },
    ]},
    { category: 'actions', items: [
        { keys: ['n'], action: 'newTask' },
        { keys: ['e'], action: 'editTask' },
        { keys: ['d'], action: 'deleteTask' },
        { keys: ['c'], action: 'addComment' },
    ]},
    { category: 'status', items: [
        { keys: ['1'], action: 'moveToBacklog' },
        { keys: ['2'], action: 'moveToInProgress' },
        { keys: ['3'], action: 'moveToReview' },
        { keys: ['4'], action: 'moveToDone' },
    ]},
    { category: 'other', items: [
        { keys: ['?'], action: 'showHelp' },
        { keys: ['/'], action: 'focusSearch' },
        { keys: ['f'], action: 'openFilters' },
    ]},
];
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                @click.self="$emit('close')"
                class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
            >
                <Transition
                    enter-active-class="transition-all duration-200"
                    leave-active-class="transition-all duration-200"
                    enter-from-class="opacity-0 scale-95"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="show"
                        class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[80vh] overflow-hidden"
                    >
                        <!-- Header -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                    </svg>
                                </div>
                                <h2 class="text-lg font-semibold text-gray-900">
                                    {{ t('devTasks.shortcuts.title') }}
                                </h2>
                            </div>
                            <button
                                @click="$emit('close')"
                                class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="p-6 overflow-y-auto">
                            <div class="grid grid-cols-2 gap-6">
                                <div
                                    v-for="section in shortcuts"
                                    :key="section.category"
                                >
                                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                                        {{ t(`devTasks.shortcuts.categories.${section.category}`) }}
                                    </h3>
                                    <div class="space-y-2">
                                        <div
                                            v-for="shortcut in section.items"
                                            :key="shortcut.action"
                                            class="flex items-center justify-between py-1"
                                        >
                                            <span class="text-sm text-gray-700">
                                                {{ t(`devTasks.shortcuts.actions.${shortcut.action}`) }}
                                            </span>
                                            <div class="flex items-center gap-1">
                                                <kbd
                                                    v-for="key in shortcut.keys"
                                                    :key="key"
                                                    class="inline-flex items-center justify-center min-w-[24px] h-6 px-1.5 text-xs font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded shadow-sm"
                                                >
                                                    {{ key }}
                                                </kbd>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                            <p class="text-xs text-gray-500 text-center">
                                {{ t('devTasks.shortcuts.hint') }}
                            </p>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
