<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useBasesStore } from '@/stores/bases';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import Button from '@/components/common/Button.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import CreateBaseModal from '@/components/base/CreateBaseModal.vue';

const { t } = useI18n();
const router = useRouter();
const basesStore = useBasesStore();
const toast = useToast();
const { confirm } = useConfirm();

const showCreateModal = ref(false);
const editingBase = ref(null);
const searchQuery = ref('');
const viewMode = ref('grid'); // 'grid' or 'list'

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

const getDisplayIcon = (icon) => {
    return iconMap[icon] || icon || '🗃️';
};

const filteredBases = computed(() => {
    if (!searchQuery.value.trim()) {
        return basesStore.bases;
    }
    const query = searchQuery.value.toLowerCase();
    return basesStore.bases.filter(base =>
        base.name.toLowerCase().includes(query) ||
        (base.description && base.description.toLowerCase().includes(query))
    );
});

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
            toast.success(t('data.baseUpdated'));
        } else {
            const newBase = await basesStore.createBase(data);
            toast.success(t('data.baseCreated'));
            router.push({ name: 'base', params: { baseId: newBase.id } });
        }
        closeModal();
    } catch (error) {
        console.error('Failed to save base:', error);
        toast.error(t('data.saveBaseError'));
    }
};

const handleDelete = async (base) => {
    const confirmed = await confirm({
        title: t('data.deleteBaseTitle'),
        message: t('data.deleteBaseMessage', { name: base.name }),
        confirmText: t('common.delete'),
        variant: 'danger',
    });

    if (!confirmed) return;

    try {
        await basesStore.deleteBase(base.id);
        toast.success(t('data.baseDeleted'));
    } catch (error) {
        console.error('Failed to delete base:', error);
        toast.error(t('data.deleteBaseError'));
    }
};

const navigateToBase = (base) => {
    router.push({ name: 'base', params: { baseId: base.id } });
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
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ t('data.title') }}</h1>
                        <p class="mt-1 text-sm text-gray-500">{{ t('data.subtitle') }}</p>
                    </div>
                    <Button @click="openCreateModal" class="shrink-0">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        {{ t('data.newDatabase') }}
                    </Button>
                </div>

                <!-- Search and View Toggle -->
                <div class="mt-6 flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="relative flex-1 max-w-md">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('data.searchPlaceholder')"
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                    <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-lg">
                        <button
                            @click="viewMode = 'grid'"
                            :class="viewMode === 'grid' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                            class="p-2 rounded-md transition-all"
                            :title="t('data.gridView')"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                            </svg>
                        </button>
                        <button
                            @click="viewMode = 'list'"
                            :class="viewMode === 'list' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                            class="p-2 rounded-md transition-all"
                            :title="t('data.listView')"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Loading -->
            <div v-if="basesStore.loading" class="flex items-center justify-center py-20">
                <LoadingSpinner size="lg" />
            </div>

            <!-- Error -->
            <div v-else-if="basesStore.error" class="text-center py-20">
                <div class="bg-red-50 rounded-xl p-8 max-w-md mx-auto">
                    <svg class="w-12 h-12 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-red-600 mb-4">{{ basesStore.error }}</p>
                    <Button variant="secondary" @click="basesStore.fetchBases()">
                        {{ t('common.tryAgain') }}
                    </Button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else-if="basesStore.bases.length === 0" class="text-center py-20">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 max-w-lg mx-auto">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ t('data.noDatabases') }}</h3>
                    <p class="text-gray-500 mb-8">{{ t('data.noDatabasesDescription') }}</p>
                    <Button @click="openCreateModal" size="lg">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        {{ t('data.createFirstDatabase') }}
                    </Button>
                </div>
            </div>

            <!-- No results -->
            <div v-else-if="filteredBases.length === 0" class="text-center py-20">
                <div class="bg-white rounded-xl p-8 max-w-md mx-auto border border-gray-200">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ t('data.noResults') }}</h3>
                    <p class="text-gray-500">{{ t('data.noResultsDescription') }}</p>
                </div>
            </div>

            <!-- Grid View -->
            <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="base in filteredBases"
                    :key="base.id"
                    class="group bg-white rounded-xl border border-gray-200 hover:border-gray-300 hover:shadow-lg transition-all duration-200 overflow-hidden"
                >
                    <div
                        @click="navigateToBase(base)"
                        class="p-6 cursor-pointer"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="w-14 h-14 rounded-xl flex items-center justify-center text-2xl shrink-0 shadow-sm"
                                :style="{ backgroundColor: base.color || '#3B82F6' }"
                            >
                                {{ getDisplayIcon(base.icon) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-semibold text-gray-900 truncate group-hover:text-blue-600 transition-colors">
                                    {{ base.name }}
                                </h3>
                                <p v-if="base.description" class="text-sm text-gray-500 line-clamp-2 mt-1">
                                    {{ base.description }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center gap-4 text-sm text-gray-500">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                <span>{{ base.tables_count || 0 }} {{ t('data.tables') }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>{{ formatDate(base.updated_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                            @click.stop="openEditModal(base)"
                            class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-200 rounded-lg transition-colors"
                        >
                            {{ t('common.edit') }}
                        </button>
                        <button
                            @click.stop="handleDelete(base)"
                            class="px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                        >
                            {{ t('common.delete') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- List View -->
            <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ t('data.database') }}</th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider hidden sm:table-cell">{{ t('data.tables') }}</th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">{{ t('data.lastModified') }}</th>
                            <th class="text-right px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ t('data.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="base in filteredBases"
                            :key="base.id"
                            @click="navigateToBase(base)"
                            class="hover:bg-gray-50 cursor-pointer transition-colors"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="w-10 h-10 rounded-lg flex items-center justify-center text-lg shrink-0"
                                        :style="{ backgroundColor: base.color || '#3B82F6' }"
                                    >
                                        {{ getDisplayIcon(base.icon) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-900 truncate">{{ base.name }}</div>
                                        <div v-if="base.description" class="text-sm text-gray-500 truncate">{{ base.description }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 hidden sm:table-cell">
                                {{ base.tables_count || 0 }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 hidden md:table-cell">
                                {{ formatDate(base.updated_at) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        @click.stop="openEditModal(base)"
                                        class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                        :title="t('common.edit')"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button
                                        @click.stop="handleDelete(base)"
                                        class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                        :title="t('common.delete')"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
