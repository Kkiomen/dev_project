<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useFiltersStore } from '@/stores/filters';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    field: {
        type: Object,
        default: null,
    },
    position: {
        type: Object,
        default: () => ({ x: 0, y: 0 }),
    },
    isFirst: {
        type: Boolean,
        default: false,
    },
    isLast: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'close',
    'rename',
    'edit-type',
    'manage-options',
    'move-left',
    'move-right',
    'delete',
    'sort',
    'filter',
]);

const filtersStore = useFiltersStore();
const menuRef = ref(null);

// Computed
const currentSort = computed(() => {
    if (!props.field) return null;
    return filtersStore.getSortForField(props.field.id);
});

const isSortedAsc = computed(() => currentSort.value?.direction === 'asc');
const isSortedDesc = computed(() => currentSort.value?.direction === 'desc');

const canSort = computed(() => {
    if (!props.field) return false;
    // JSON and attachment fields cannot be sorted
    return !['json', 'attachment'].includes(props.field.type);
});

const sortLabels = computed(() => {
    if (!props.field) return { asc: 'A → Z', desc: 'Z → A' };

    switch (props.field.type) {
        case 'text':
        case 'url':
        case 'select':
        case 'multi_select':
            return { asc: 'A → Z', desc: 'Z → A' };
        case 'number':
            return { asc: '1 → 9', desc: '9 → 1' };
        case 'date':
        case 'datetime':
            return { asc: 'Najstarsze', desc: 'Najnowsze' };
        case 'checkbox':
            return { asc: 'Nie → Tak', desc: 'Tak → Nie' };
        default:
            return { asc: 'Rosnąco', desc: 'Malejąco' };
    }
});

// Methods
const handleClickOutside = (event) => {
    if (menuRef.value && !menuRef.value.contains(event.target)) {
        emit('close');
    }
};

const sortAsc = () => {
    if (!props.field) return;
    filtersStore.setSort(props.field.id, 'asc');
    emit('sort', props.field.id, 'asc');
    emit('close');
};

const sortDesc = () => {
    if (!props.field) return;
    filtersStore.setSort(props.field.id, 'desc');
    emit('sort', props.field.id, 'desc');
    emit('close');
};

const clearSort = () => {
    if (!props.field) return;
    filtersStore.setSort(props.field.id, null);
    emit('sort', props.field.id, null);
    emit('close');
};

const addFilter = () => {
    if (!props.field) return;
    emit('filter', props.field);
    emit('close');
};

const hasOptions = () => {
    return props.field?.type === 'select' || props.field?.type === 'multi_select';
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            ref="menuRef"
            :style="{ top: position.y + 'px', left: position.x + 'px' }"
            class="fixed z-50 w-64 bg-white rounded-xl shadow-xl border border-gray-200 py-2 overflow-hidden"
        >
            <!-- Sorting section -->
            <template v-if="canSort">
                <div class="px-3 py-1.5">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Sortuj</span>
                </div>

                <button
                    @click="sortAsc"
                    class="w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 transition-colors"
                    :class="isSortedAsc ? 'text-blue-600 bg-blue-50' : 'text-gray-700'"
                >
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/>
                        </svg>
                        <span>{{ sortLabels.asc }}</span>
                    </div>
                    <svg v-if="isSortedAsc" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </button>

                <button
                    @click="sortDesc"
                    class="w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 transition-colors"
                    :class="isSortedDesc ? 'text-blue-600 bg-blue-50' : 'text-gray-700'"
                >
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/>
                        </svg>
                        <span>{{ sortLabels.desc }}</span>
                    </div>
                    <svg v-if="isSortedDesc" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </button>

                <button
                    v-if="currentSort"
                    @click="clearSort"
                    class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-500 hover:bg-gray-50 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span>Usuń sortowanie</span>
                </button>

                <hr class="my-2 border-gray-100">
            </template>

            <!-- Filter section -->
            <div class="px-3 py-1.5">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Filtruj</span>
            </div>

            <button
                @click="addFilter"
                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                <span>Filtruj po tym polu</span>
            </button>

            <hr class="my-2 border-gray-100">

            <!-- Field settings section -->
            <div class="px-3 py-1.5">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Ustawienia pola</span>
            </div>

            <button
                @click="emit('rename')"
                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
                <span>Zmień nazwę</span>
            </button>

            <button
                @click="emit('edit-type')"
                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span>Zmień typ</span>
            </button>

            <button
                v-if="hasOptions()"
                @click="emit('manage-options')"
                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span>Zarządzaj opcjami</span>
            </button>

            <hr class="my-2 border-gray-100">

            <!-- Position section -->
            <button
                @click="emit('move-left')"
                :disabled="isFirst"
                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                <span>Przesuń w lewo</span>
            </button>

            <button
                @click="emit('move-right')"
                :disabled="isLast"
                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span>Przesuń w prawo</span>
            </button>

            <hr class="my-2 border-gray-100">

            <!-- Delete -->
            <button
                @click="emit('delete')"
                :disabled="field?.is_primary"
                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                <span>Usuń pole</span>
            </button>
        </div>
    </Teleport>
</template>
