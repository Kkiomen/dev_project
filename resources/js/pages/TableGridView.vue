<script setup>
import { ref, onMounted, watch, computed, onUnmounted } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useTablesStore } from '@/stores/tables';
import { useFieldsStore } from '@/stores/fields';
import { useRowsStore } from '@/stores/rows';
import { useFiltersStore } from '@/stores/filters';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import GridTable from '@/components/grid/GridTable.vue';

const { t } = useI18n();

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
const pollingInterval = ref(null);

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

// Silent refresh for polling (no loading spinner)
const refreshData = async () => {
    try {
        const table = await tablesStore.refreshTable(props.tableId);
        if (table) {
            fieldsStore.setFields(table.fields || []);
            rowsStore.setRows(table.rows || []);
        }
    } catch (error) {
        console.error('Failed to refresh table:', error);
    }
};

const startPolling = () => {
    stopPolling();
    pollingInterval.value = setInterval(refreshData, 5000);
};

const stopPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
        pollingInterval.value = null;
    }
};

onMounted(async () => {
    await fetchData();
    startPolling();
});

watch(() => props.tableId, async () => {
    stopPolling();
    await fetchData();
    startPolling();
});

// Cleanup on unmount
onUnmounted(() => {
    stopPolling();
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
            <div class="bg-white border-b border-gray-200 px-3 sm:px-4 py-3">
                <div class="flex items-center space-x-3 sm:space-x-4">
                    <RouterLink to="/data" class="text-gray-500 hover:text-gray-700 flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </RouterLink>

                    <div class="flex items-center space-x-2 min-w-0">
                        <RouterLink
                            :to="{ name: 'base', params: { baseId: tablesStore.currentTable.base_id } }"
                            class="text-sm font-medium text-gray-500 hover:text-gray-700 truncate hidden sm:inline"
                        >
                            {{ t('table.base') }}
                        </RouterLink>
                        <span class="text-gray-300 hidden sm:inline">/</span>
                        <span class="text-sm font-semibold text-gray-900 truncate">
                            {{ tablesStore.currentTable.name }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="bg-white border-b border-gray-200 px-3 sm:px-4 py-2">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-4">
                    <div class="flex items-center gap-2 sm:gap-4 flex-wrap">
                        <span class="text-sm text-gray-500 whitespace-nowrap">
                            {{ rowsStore.rows.length }} {{ t('table.records') }}
                            <template v-if="filtersStore.hasActiveFilters">
                                {{ t('table.filtered') }}
                            </template>
                        </span>
                        <!-- Search -->
                        <div class="relative flex-1 sm:flex-none">
                            <input
                                type="text"
                                v-model="searchQuery"
                                @input="handleSearch(searchQuery)"
                                :placeholder="t('table.searchPlaceholder')"
                                class="w-full sm:w-auto text-sm border-gray-300 rounded-md pl-8 pr-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
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
                    <div class="flex items-center">
                        <Button @click="handleAddRow" class="w-full sm:w-auto justify-center">
                            <svg class="w-4 h-4 sm:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span class="hidden sm:inline">{{ t('table.addRow') }}</span>
                        </Button>
                    </div>
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
