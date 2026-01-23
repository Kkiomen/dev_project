<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useTablesStore } from '@/stores/tables';
import { useFieldsStore } from '@/stores/fields';
import { useRowsStore } from '@/stores/rows';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import KanbanBoard from '@/components/kanban/KanbanBoard.vue';

const { t } = useI18n();

const props = defineProps({
    tableId: {
        type: String,
        required: true,
    },
});

const router = useRouter();
const route = useRoute();
const tablesStore = useTablesStore();
const fieldsStore = useFieldsStore();
const rowsStore = useRowsStore();

const loading = ref(true);
const groupByFieldId = ref(null);

const selectFields = computed(() => fieldsStore.selectFields);

const fetchData = async () => {
    loading.value = true;
    try {
        const table = await tablesStore.fetchTable(props.tableId);
        fieldsStore.setFields(table.fields || []);
        rowsStore.setRows(table.rows || []);

        // Set initial groupByFieldId from query or first select field
        const queryGroupBy = route.query.group_by;
        if (queryGroupBy && selectFields.value.find(f => f.id === queryGroupBy)) {
            groupByFieldId.value = queryGroupBy;
        } else if (selectFields.value.length > 0) {
            groupByFieldId.value = selectFields.value[0].id;
        }
    } catch (error) {
        console.error('Failed to fetch table:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchData);
watch(() => props.tableId, fetchData);

const changeGroupBy = (fieldId) => {
    groupByFieldId.value = fieldId;
    router.replace({
        query: { ...route.query, group_by: fieldId },
    });
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
                            {{ t('table.base') }}
                        </RouterLink>
                        <span class="text-gray-300">/</span>
                        <span class="text-sm font-semibold text-gray-900">
                            {{ tablesStore.currentTable.name }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <!-- Group by selector -->
                    <div v-if="selectFields.length > 0" class="flex items-center space-x-2">
                        <span class="text-sm text-gray-500">{{ t('table.groupBy') }}</span>
                        <select
                            :value="groupByFieldId"
                            @change="changeGroupBy($event.target.value)"
                            class="text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option
                                v-for="field in selectFields"
                                :key="field.id"
                                :value="field.id"
                            >
                                {{ field.name }}
                            </option>
                        </select>
                    </div>

                    <!-- View switcher -->
                    <div class="flex bg-gray-100 rounded-lg p-1">
                        <RouterLink
                            :to="{ name: 'table.grid', params: { tableId } }"
                            class="px-3 py-1 text-sm font-medium rounded-md text-gray-600 hover:text-gray-900"
                        >
                            {{ t('table.viewGrid') }}
                        </RouterLink>
                        <span class="px-3 py-1 text-sm font-medium rounded-md bg-white shadow text-gray-900">
                            {{ t('table.viewKanban') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- No select fields message -->
            <div v-if="selectFields.length === 0" class="flex-1 flex items-center justify-center bg-gray-50">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">{{ t('table.noGroupingField') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ t('kanban.addSelectField') }}
                    </p>
                    <div class="mt-6">
                        <RouterLink
                            :to="{ name: 'table.grid', params: { tableId } }"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                        >
                            {{ t('kanban.backToGrid') }}
                        </RouterLink>
                    </div>
                </div>
            </div>

            <!-- Kanban Board -->
            <KanbanBoard
                v-else-if="groupByFieldId"
                :table-id="tableId"
                :group-by-field-id="groupByFieldId"
                @change-group-by="changeGroupBy"
            />
        </template>
    </div>
</template>
