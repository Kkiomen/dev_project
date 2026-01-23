<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBasesStore } from '@/stores/bases';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import Button from '@/components/common/Button.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import CreateBaseModal from '@/components/base/CreateBaseModal.vue';

const router = useRouter();
const basesStore = useBasesStore();
const toast = useToast();
const { confirm } = useConfirm();

const showCreateModal = ref(false);
const editingBase = ref(null);

onMounted(() => {
    basesStore.fetchBases();
});

const openCreateModal = () => {
    editingBase.value = null;
    showCreateModal.value = true;
};

const openEditModal = (base) => {
    editingBase.value = base;
    showCreateModal.value = true;
};

const closeModal = () => {
    showCreateModal.value = false;
    editingBase.value = null;
};

const handleSave = async (data) => {
    try {
        if (editingBase.value) {
            await basesStore.updateBase(editingBase.value.id, data);
            toast.success('Baza zaktualizowana');
        } else {
            const newBase = await basesStore.createBase(data);
            toast.success('Baza utworzona');
            router.push({ name: 'base', params: { baseId: newBase.id } });
        }
        closeModal();
    } catch (error) {
        console.error('Failed to save base:', error);
        toast.error('Nie udało się zapisać bazy');
    }
};

const handleDelete = async (base) => {
    const confirmed = await confirm({
        title: 'Usuń bazę danych',
        message: `Czy na pewno chcesz usunąć bazę "${base.name}"? Wszystkie tabele i dane zostaną bezpowrotnie usunięte.`,
        confirmText: 'Usuń bazę',
        variant: 'danger',
    });

    if (!confirmed) return;

    try {
        await basesStore.deleteBase(base.id);
        toast.success('Baza usunięta');
    } catch (error) {
        console.error('Failed to delete base:', error);
        toast.error('Nie udało się usunąć bazy');
    }
};
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Moje bazy danych</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Zarządzaj swoimi bazami danych i tabelami
                </p>
            </div>
            <Button @click="openCreateModal">
                + Nowa baza
            </Button>
        </div>

        <!-- Loading -->
        <div v-if="basesStore.loading" class="py-12">
            <LoadingSpinner size="lg" />
        </div>

        <!-- Error -->
        <div v-else-if="basesStore.error" class="text-center py-12">
            <p class="text-red-500">{{ basesStore.error }}</p>
            <Button variant="secondary" class="mt-4" @click="basesStore.fetchBases()">
                Spróbuj ponownie
            </Button>
        </div>

        <!-- Empty state -->
        <div v-else-if="basesStore.bases.length === 0" class="text-center py-12">
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
                    d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"
                />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Brak baz danych</h3>
            <p class="mt-1 text-sm text-gray-500">Zacznij od utworzenia nowej bazy danych.</p>
            <div class="mt-6">
                <Button @click="openCreateModal">
                    + Nowa baza
                </Button>
            </div>
        </div>

        <!-- Bases grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <BaseCard
                v-for="base in basesStore.bases"
                :key="base.id"
                :base="base"
                @edit="openEditModal"
                @delete="handleDelete"
            />
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <CreateBaseModal
        :show="showCreateModal"
        :editing-base="editingBase"
        @close="closeModal"
        @save="handleSave"
    />
</template>
