<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useBasesStore } from '@/stores/bases';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import Button from '@/components/common/Button.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import CreateBaseModal from '@/components/base/CreateBaseModal.vue';

const { t } = useI18n();

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
            toast.success(t('dashboard.baseUpdated'));
        } else {
            const newBase = await basesStore.createBase(data);
            toast.success(t('dashboard.baseCreated'));
            router.push({ name: 'base', params: { baseId: newBase.id } });
        }
        closeModal();
    } catch (error) {
        console.error('Failed to save base:', error);
        toast.error(t('dashboard.saveBaseError'));
    }
};

const handleDelete = async (base) => {
    const confirmed = await confirm({
        title: t('dashboard.deleteBaseTitle'),
        message: t('dashboard.deleteBaseMessage', { name: base.name }),
        confirmText: t('dashboard.deleteBaseConfirm'),
        variant: 'danger',
    });

    if (!confirmed) return;

    try {
        await basesStore.deleteBase(base.id);
        toast.success(t('dashboard.baseDeleted'));
    } catch (error) {
        console.error('Failed to delete base:', error);
        toast.error(t('dashboard.deleteBaseError'));
    }
};
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ t('dashboard.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('dashboard.subtitle') }}
                </p>
            </div>
            <Button @click="openCreateModal">
                {{ t('dashboard.newBase') }}
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
                {{ t('common.tryAgain') }}
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
            <h3 class="mt-2 text-sm font-medium text-gray-900">{{ t('dashboard.noBases') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ t('dashboard.noBasesDescription') }}</p>
            <div class="mt-6">
                <Button @click="openCreateModal">
                    {{ t('dashboard.newBase') }}
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
