<script setup>
import { ref, onMounted, watch, computed, onUnmounted } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useTablesStore } from '@/stores/tables';
import { useFieldsStore } from '@/stores/fields';
import { useRowsStore } from '@/stores/rows';
import { useFiltersStore } from '@/stores/filters';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import GridTable from '@/components/grid/GridTable.vue';

const props = defineProps({
    tableId: {
        type: String,
        required: true,
    },
});

const router = useRouter();
const tablesStore = useTablesStore();
const fieldsStore = useFieldsStore();
const rowsStore = useRowsStore();
const filtersStore = useFiltersStore();

const gridTableRef = ref(null);
const searchQuery = ref('');
const loading = ref(true);

const fetchData = async () => {
    loading.value = true;
    // Reset filters when switching tables
    filtersStore.reset();
    try {
        const table = await tablesStore.fetchTable(props.tableId);
        fieldsStore.setFields(table.fields || []);
        rowsStore.setRows(table.rows || []);
    } catch (error) {
        console.error('Failed to fetch table:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchData);
watch(() => props.tableId, fetchData);

// Cleanup on unmount
onUnmounted(() => {
    filtersStore.reset();
});

const handleAddRow = () => {
    gridTableRef.value?.addRow();
};

const handleSearch = (query) => {
    if (gridTableRef.value) {
        gridTableRef.value.searchQuery = query;
    }
};
</script>

<template>
    <div class="h-[calc(100vh-64px)] flex flex-col">
        <!-- Loading -->
        <div v-if="loading" class="flex-1 flex items-center justify-center">
            <LoadingSpinner size="lg" />
        </div>

        <template v-else-if="tablesStore.currentTable">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <RouterLink to="/dashboard" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </RouterLink>

                    <div class="flex items-center space-x-2">
                        <RouterLink
                            :to="{ name: 'base', params: { baseId: tablesStore.currentTable.base_id } }"
                            class="text-sm font-medium text-gray-500 hover:text-gray-700"
                        >
                            Baza
                        </RouterLink>
                        <span class="text-gray-300">/</span>
                        <span class="text-sm font-semibold text-gray-900">
                            {{ tablesStore.currentTable.name }}
                        </span>
                    </div>
                </div>

                <!-- View switcher -->
                <div class="flex items-center space-x-2">
                    <div class="flex bg-gray-100 rounded-lg p-1">
                        <span class="px-3 py-1 text-sm font-medium rounded-md bg-white shadow text-gray-900">
                            Grid
                        </span>
                        <RouterLink
                            :to="{ name: 'table.kanban', params: { tableId } }"
                            class="px-3 py-1 text-sm font-medium rounded-md text-gray-600 hover:text-gray-900"
                        >
                            Kanban
                        </RouterLink>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">
                        {{ rowsStore.rows.length }} rekordów
                        <template v-if="filtersStore.hasActiveFilters">
                            (przefiltrowane)
                        </template>
                    </span>
                    <!-- Search -->
                    <div class="relative">
                        <input
                            type="text"
                            v-model="searchQuery"
                            @input="handleSearch(searchQuery)"
                            placeholder="Szukaj..."
                            class="text-sm border-gray-300 rounded-md pl-8 pr-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                        />
                        <svg
                            class="w-4 h-4 absolute left-2 top-1/2 -translate-y-1/2 text-gray-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <Button @click="handleAddRow">
                        + Dodaj wiersz
                    </Button>
                </div>
            </div>

            <!-- Grid Table -->
            <GridTable
                ref="gridTableRef"
                :table-id="tableId"
            />
        </template>
    </div>
</template>
