<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
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

const router = useRouter();
const basesStore = useBasesStore();
const tablesStore = useTablesStore();
const toast = useToast();
const { confirm } = useConfirm();

const showCreateTableModal = ref(false);
const newTableName = ref('');
const creating = ref(false);

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
        toast.success('Tabela utworzona');
        router.push({ name: 'table.grid', params: { tableId: table.id } });
    } catch (error) {
        console.error('Failed to create table:', error);
        toast.error('Nie udało się utworzyć tabeli');
    } finally {
        creating.value = false;
    }
};

const deleteTable = async (table) => {
    const confirmed = await confirm({
        title: 'Usuń tabelę',
        message: `Czy na pewno chcesz usunąć tabelę "${table.name}"? Wszystkie dane zostaną utracone.`,
        confirmText: 'Usuń',
        variant: 'danger',
    });

    if (!confirmed) return;

    try {
        await tablesStore.deleteTable(table.id);
        toast.success('Tabela usunięta');
    } catch (error) {
        console.error('Failed to delete table:', error);
        toast.error('Nie udało się usunąć tabeli');
    }
};
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Breadcrumb -->
        <div class="mb-6">
            <RouterLink to="/dashboard" class="text-sm text-gray-500 hover:text-gray-700">
                ← Dashboard
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
                        {{ basesStore.currentBase.icon || '🗃' }}
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
                    + Nowa tabela
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
                <h3 class="mt-2 text-sm font-medium text-gray-900">Brak tabel</h3>
                <p class="mt-1 text-sm text-gray-500">Zacznij od utworzenia nowej tabeli.</p>
                <div class="mt-6">
                    <Button @click="showCreateTableModal = true">
                        + Nowa tabela
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
                            {{ table.fields_count || 0 }} pól • {{ table.rows_count || 0 }} rekordów
                        </p>
                    </RouterLink>

                    <div class="flex items-center space-x-2">
                        <RouterLink
                            :to="{ name: 'table.grid', params: { tableId: table.id } }"
                            class="px-3 py-1 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded"
                        >
                            Grid
                        </RouterLink>
                        <RouterLink
                            :to="{ name: 'table.kanban', params: { tableId: table.id } }"
                            class="px-3 py-1 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded"
                        >
                            Kanban
                        </RouterLink>
                        <button
                            @click="deleteTable(table)"
                            class="px-3 py-1 text-sm text-red-600 hover:text-red-700 hover:bg-red-50 rounded"
                        >
                            Usuń
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Create Table Modal -->
    <Modal :show="showCreateTableModal" max-width="md" @close="showCreateTableModal = false">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Nowa tabela</h3>
        <form @submit.prevent="createTable">
            <Input
                v-model="newTableName"
                label="Nazwa tabeli"
                placeholder="Np. Kontakty"
                required
            />
            <div class="mt-6 flex justify-end space-x-3">
                <Button variant="secondary" @click="showCreateTableModal = false">
                    Anuluj
                </Button>
                <Button type="submit" :loading="creating">
                    Utwórz
                </Button>
            </div>
        </form>
    </Modal>
</template>
