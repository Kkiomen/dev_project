<script setup>
import { ref, computed, onMounted, watch } from 'vue';
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
const newTableName = ref('');
const creating = ref(false);

// Map old icon names to emoji
const iconMap = {
    'database': '🗃',
    'chart': '📊',
    'note': '📝',
    'star': '⭐',
    'briefcase': '💼',
    'wrench': '🔧',
    'sparkles': '🌟',
    'lightbulb': '💡',
};

const displayIcon = computed(() => {
    if (!basesStore.currentBase) return '🗃';
    const icon = basesStore.currentBase.icon || '🗃';
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
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Breadcrumb -->
        <div class="mb-6">
            <RouterLink to="/dashboard" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>{{ t('navigation.dashboard') }}</span>
            </RouterLink>
        </div>

        <!-- Loading -->
        <div v-if="basesStore.loading" class="py-12">
            <LoadingSpinner size="lg" />
        </div>

        <!-- Content -->
        <template v-else-if="basesStore.currentBase">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center space-x-4">
                    <div
                        class="w-12 h-12 rounded-lg flex items-center justify-center text-white text-xl"
                        :style="{ backgroundColor: basesStore.currentBase.color || '#3B82F6' }"
                    >
                        {{ displayIcon }}
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            {{ basesStore.currentBase.name }}
                        </h1>
                        <p v-if="basesStore.currentBase.description" class="text-sm text-gray-500">
                            {{ basesStore.currentBase.description }}
                        </p>
                    </div>
                </div>
                <Button @click="showCreateTableModal = true">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ t('table.newTable') }}
                </Button>
            </div>

            <!-- Tables list -->
            <div v-if="tablesStore.tables.length === 0" class="text-center py-12 bg-white rounded-lg border border-gray-200">
                <svg
                    class="mx-auto h-12 w-12 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
                    />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">{{ t('table.noTables') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ t('table.noTablesDescription') }}</p>
                <div class="mt-6">
                    <Button @click="showCreateTableModal = true">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        {{ t('table.newTable') }}
                    </Button>
                </div>
            </div>

            <div v-else class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-200">
                <div
                    v-for="table in tablesStore.tables"
                    :key="table.id"
                    class="flex items-center justify-between p-4 hover:bg-gray-50"
                >
                    <RouterLink
                        :to="{ name: 'table.grid', params: { tableId: table.id } }"
                        class="flex-1 min-w-0"
                    >
                        <h3 class="text-base font-medium text-gray-900">
                            {{ table.name }}
                        </h3>
                        <p class="text-sm text-gray-500">
                            {{ table.fields_count || 0 }} {{ t('table.fields') }} • {{ table.rows_count || 0 }} {{ t('table.records') }}
                        </p>
                    </RouterLink>

                    <div class="flex items-center space-x-2">
                        <button
                            @click="deleteTable(table)"
                            class="px-3 py-1 text-sm text-red-600 hover:text-red-700 hover:bg-red-50 rounded"
                        >
                            {{ t('common.delete') }}
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Create Table Modal -->
    <Modal :show="showCreateTableModal" max-width="md" @close="showCreateTableModal = false">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('table.newTable') }}</h3>
        <form @submit.prevent="createTable">
            <Input
                v-model="newTableName"
                :label="t('table.tableName')"
                :placeholder="t('table.tableNamePlaceholder')"
                required
            />
            <div class="mt-6 flex justify-end space-x-3">
                <Button variant="secondary" @click="showCreateTableModal = false">
                    {{ t('common.cancel') }}
                </Button>
                <Button type="submit" :loading="creating">
                    {{ t('common.create') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>
