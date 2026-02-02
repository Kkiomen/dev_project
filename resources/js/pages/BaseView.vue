<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useBasesStore } from '@/stores/bases';
import { useTablesStore } from '@/stores/tables';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import Button from '@/components/common/Button.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Modal from '@/components/common/Modal.vue';
import Input from '@/components/common/Input.vue';

const props = defineProps({
    baseId: {
        type: String,
        required: true,
    },
});

const { t } = useI18n();
const router = useRouter();
const basesStore = useBasesStore();
const tablesStore = useTablesStore();
const toast = useToast();
const { confirm } = useConfirm();

const showCreateTableModal = ref(false);
const showRenameTableModal = ref(false);
const newTableName = ref('');
const renameTableName = ref('');
const renamingTable = ref(null);
const creating = ref(false);
const renaming = ref(false);
const editingTableId = ref(null);
const editInputRef = ref(null);

// Map old icon names to emoji
const iconMap = {
    'database': '🗃️',
    'chart': '📊',
    'note': '📝',
    'star': '⭐',
    'briefcase': '💼',
    'wrench': '🔧',
    'sparkles': '✨',
    'lightbulb': '💡',
};

const displayIcon = computed(() => {
    if (!basesStore.currentBase) return '🗃️';
    const icon = basesStore.currentBase.icon || '🗃️';
    return iconMap[icon] || icon;
});

const fetchData = async () => {
    try {
        await basesStore.fetchBase(props.baseId);
        await tablesStore.fetchTables(props.baseId);
    } catch (error) {
        console.error('Failed to fetch base:', error);
    }
};

onMounted(fetchData);
watch(() => props.baseId, fetchData);

const createTable = async () => {
    if (!newTableName.value.trim()) return;

    creating.value = true;
    try {
        const table = await tablesStore.createTable(props.baseId, {
            name: newTableName.value,
        });
        showCreateTableModal.value = false;
        newTableName.value = '';
        toast.success(t('table.tableCreated'));
        router.push({ name: 'table.grid', params: { tableId: table.id } });
    } catch (error) {
        console.error('Failed to create table:', error);
        toast.error(t('table.createTableError'));
    } finally {
        creating.value = false;
    }
};

const startRenameTable = (table) => {
    renamingTable.value = table;
    renameTableName.value = table.name;
    showRenameTableModal.value = true;
};

const saveRenameTable = async () => {
    if (!renameTableName.value.trim() || !renamingTable.value) return;

    renaming.value = true;
    try {
        await tablesStore.updateTable(renamingTable.value.id, {
            name: renameTableName.value,
        });
        showRenameTableModal.value = false;
        renamingTable.value = null;
        renameTableName.value = '';
        toast.success(t('table.tableRenamed'));
    } catch (error) {
        console.error('Failed to rename table:', error);
        toast.error(t('table.renameTableError'));
    } finally {
        renaming.value = false;
    }
};

const startInlineEdit = async (table) => {
    editingTableId.value = table.id;
    renameTableName.value = table.name;
    await nextTick();
    editInputRef.value?.focus();
    editInputRef.value?.select();
};

const saveInlineEdit = async (table) => {
    if (!renameTableName.value.trim()) {
        editingTableId.value = null;
        return;
    }

    if (renameTableName.value === table.name) {
        editingTableId.value = null;
        return;
    }

    try {
        await tablesStore.updateTable(table.id, {
            name: renameTableName.value,
        });
        toast.success(t('table.tableRenamed'));
    } catch (error) {
        console.error('Failed to rename table:', error);
        toast.error(t('table.renameTableError'));
    } finally {
        editingTableId.value = null;
    }
};

const cancelInlineEdit = () => {
    editingTableId.value = null;
    renameTableName.value = '';
};

const deleteTable = async (table) => {
    const confirmed = await confirm({
        title: t('table.deleteTable'),
        message: t('table.deleteTableMessage', { name: table.name }),
        confirmText: t('common.delete'),
        variant: 'danger',
    });

    if (!confirmed) return;

    try {
        await tablesStore.deleteTable(table.id);
        toast.success(t('table.tableDeleted'));
    } catch (error) {
        console.error('Failed to delete table:', error);
        toast.error(t('table.deleteTableError'));
    }
};

const navigateToTable = (table) => {
    if (editingTableId.value === table.id) return;
    router.push({ name: 'table.grid', params: { tableId: table.id } });
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString();
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <!-- Breadcrumb -->
                <nav class="flex items-center gap-2 text-sm mb-4">
                    <RouterLink to="/data" class="text-gray-500 hover:text-gray-700 transition-colors">
                        {{ t('navigation.data') }}
                    </RouterLink>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    <span class="text-gray-900 font-medium">{{ basesStore.currentBase?.name || '...' }}</span>
                </nav>

                <!-- Loading -->
                <div v-if="basesStore.loading" class="py-8">
                    <LoadingSpinner size="lg" />
                </div>

                <!-- Header Content -->
                <template v-else-if="basesStore.currentBase">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div
                                class="w-14 h-14 rounded-xl flex items-center justify-center text-2xl shadow-sm"
                                :style="{ backgroundColor: basesStore.currentBase.color || '#3B82F6' }"
                            >
                                {{ displayIcon }}
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">
                                    {{ basesStore.currentBase.name }}
                                </h1>
                                <p v-if="basesStore.currentBase.description" class="text-gray-500 mt-0.5">
                                    {{ basesStore.currentBase.description }}
                                </p>
                            </div>
                        </div>
                        <Button @click="showCreateTableModal = true" class="shrink-0">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            {{ t('table.newTable') }}
                        </Button>
                    </div>
                </template>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Loading -->
            <div v-if="tablesStore.loading" class="flex items-center justify-center py-20">
                <LoadingSpinner size="lg" />
            </div>

            <!-- Empty state -->
            <div v-else-if="tablesStore.tables.length === 0" class="text-center py-20">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 max-w-lg mx-auto">
                    <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ t('table.noTables') }}</h3>
                    <p class="text-gray-500 mb-8">{{ t('table.noTablesDescription') }}</p>
                    <Button @click="showCreateTableModal = true" size="lg">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        {{ t('table.createFirstTable') }}
                    </Button>
                </div>
            </div>

            <!-- Tables list -->
            <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">
                        {{ t('table.tables') }} ({{ tablesStore.tables.length }})
                    </h2>
                </div>
                <div class="divide-y divide-gray-100">
                    <div
                        v-for="table in tablesStore.tables"
                        :key="table.id"
                        class="group flex items-center gap-4 px-6 py-4 hover:bg-gray-50 transition-colors"
                        :class="{ 'cursor-pointer': editingTableId !== table.id }"
                        @click="navigateToTable(table)"
                    >
                        <!-- Icon -->
                        <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </div>

                        <!-- Name and stats -->
                        <div class="flex-1 min-w-0">
                            <div v-if="editingTableId === table.id" class="flex items-center gap-2" @click.stop>
                                <input
                                    ref="editInputRef"
                                    v-model="renameTableName"
                                    type="text"
                                    class="flex-1 px-3 py-1.5 text-base font-medium border border-blue-500 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    @keyup.enter="saveInlineEdit(table)"
                                    @keyup.escape="cancelInlineEdit"
                                    @blur="saveInlineEdit(table)"
                                />
                            </div>
                            <template v-else>
                                <h3 class="text-base font-medium text-gray-900 group-hover:text-blue-600 transition-colors">
                                    {{ table.name }}
                                </h3>
                                <div class="flex items-center gap-4 mt-1 text-sm text-gray-500">
                                    <span class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/>
                                        </svg>
                                        {{ table.fields_count || 0 }} {{ t('table.fields') }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                        </svg>
                                        {{ table.rows_count || 0 }} {{ t('table.records') }}
                                    </span>
                                    <span class="hidden sm:flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        {{ formatDate(table.updated_at) }}
                                    </span>
                                </div>
                            </template>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity" @click.stop>
                            <button
                                @click="startInlineEdit(table)"
                                class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                :title="t('table.renameTable')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button
                                @click="deleteTable(table)"
                                class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                :title="t('common.delete')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                            <RouterLink
                                :to="{ name: 'table.grid', params: { tableId: table.id } }"
                                class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                :title="t('table.openTable')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </RouterLink>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Table Modal -->
    <Modal :show="showCreateTableModal" max-width="md" @close="showCreateTableModal = false">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">{{ t('table.newTable') }}</h3>
            <form @submit.prevent="createTable">
                <Input
                    v-model="newTableName"
                    :label="t('table.tableName')"
                    :placeholder="t('table.tableNamePlaceholder')"
                    required
                    autofocus
                />
                <div class="mt-6 flex justify-end gap-3">
                    <Button variant="secondary" @click="showCreateTableModal = false">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="submit" :loading="creating">
                        {{ t('common.create') }}
                    </Button>
                </div>
            </form>
        </div>
    </Modal>

    <!-- Rename Table Modal (alternative to inline edit) -->
    <Modal :show="showRenameTableModal" max-width="md" @close="showRenameTableModal = false">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">{{ t('table.renameTable') }}</h3>
            <form @submit.prevent="saveRenameTable">
                <Input
                    v-model="renameTableName"
                    :label="t('table.tableName')"
                    required
                    autofocus
                />
                <div class="mt-6 flex justify-end gap-3">
                    <Button variant="secondary" @click="showRenameTableModal = false">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="submit" :loading="renaming">
                        {{ t('common.save') }}
                    </Button>
                </div>
            </form>
        </div>
    </Modal>
</template>
