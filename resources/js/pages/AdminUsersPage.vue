<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAdminUsersStore } from '@/stores/adminUsers';
import { useAuthStore } from '@/stores/auth';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import Button from '@/components/common/Button.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import EditUserModal from '@/components/admin/EditUserModal.vue';

const { t } = useI18n();
const adminUsersStore = useAdminUsersStore();
const authStore = useAuthStore();
const { confirm } = useConfirm();
const toast = useToast();

const loading = ref(true);
const searchQuery = ref('');
const showEditModal = ref(false);
const selectedUser = ref(null);
const searchTimeout = ref(null);

const fetchData = async (page = 1) => {
    loading.value = true;
    try {
        await adminUsersStore.fetchUsers({
            page,
            search: searchQuery.value || undefined,
        });
    } catch (error) {
        console.error('Failed to fetch users:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => fetchData());

watch(searchQuery, () => {
    clearTimeout(searchTimeout.value);
    searchTimeout.value = setTimeout(() => fetchData(1), 300);
});

const openEditModal = (user) => {
    selectedUser.value = user;
    showEditModal.value = true;
};

const handleUserUpdated = () => {
    showEditModal.value = false;
    fetchData(adminUsersStore.pagination.currentPage);
};

const handleDelete = async (user, event) => {
    event.stopPropagation();

    if (authStore.user?.id === user.id) {
        toast.error(t('admin.preventSelfDelete'));
        return;
    }

    const confirmed = await confirm({
        title: t('admin.deleteUser'),
        message: t('admin.deleteUserConfirm', { name: user.name }),
        confirmText: t('common.delete'),
        variant: 'danger',
    });

    if (confirmed) {
        try {
            await adminUsersStore.deleteUser(user.id);
            toast.success(t('admin.userDeleted'));
        } catch (error) {
            const message = error.response?.data?.message;
            toast.error(message || 'Failed to delete user');
        }
    }
};

const goToPage = (page) => {
    if (page >= 1 && page <= adminUsersStore.pagination.lastPage) {
        fetchData(page);
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString();
};
</script>

<template>
    <div class="py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ t('admin.title') }}</h1>
                    <p class="text-gray-500 mt-1">{{ t('admin.subtitle') }}</p>
                </div>
            </div>

            <!-- Search -->
            <div class="mb-6">
                <div class="relative max-w-md">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('admin.searchUsers')"
                        class="block w-full pl-10 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    />
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="flex justify-center py-20">
                <LoadingSpinner />
            </div>

            <!-- Empty state -->
            <div v-else-if="adminUsersStore.users.length === 0" class="text-center py-20">
                <div class="mx-auto w-20 h-20 rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center mb-6">
                    <svg class="w-10 h-10 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">{{ t('admin.noUsers') }}</h3>
            </div>

            <!-- Users table -->
            <div v-else>
                <!-- Desktop table -->
                <div class="hidden md:block bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('admin.name') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('admin.email') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('admin.role') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('navigation.brands') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('admin.createdAt') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="user in adminUsersStore.users"
                                :key="user.id"
                                @click="openEditModal(user)"
                                class="hover:bg-gray-50 cursor-pointer transition-colors"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                            <span class="text-sm font-medium text-blue-600">{{ user.name?.charAt(0)?.toUpperCase() }}</span>
                                        </div>
                                        <span class="text-sm font-medium text-gray-900">{{ user.name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ user.email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                        :class="user.is_admin ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ user.is_admin ? t('admin.adminBadge') : t('admin.userBadge') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ user.brands_count || 0 }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(user.created_at) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <button
                                        v-if="authStore.user?.id !== user.id"
                                        @click="handleDelete(user, $event)"
                                        class="p-1.5 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition-all"
                                        :title="t('admin.deleteUser')"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile cards -->
                <div class="md:hidden space-y-3">
                    <div
                        v-for="user in adminUsersStore.users"
                        :key="user.id"
                        @click="openEditModal(user)"
                        class="bg-white rounded-xl border border-gray-200 p-4 cursor-pointer hover:shadow-md transition-all"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                    <span class="text-sm font-medium text-blue-600">{{ user.name?.charAt(0)?.toUpperCase() }}</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ user.name }}</p>
                                    <p class="text-xs text-gray-500">{{ user.email }}</p>
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0"
                                :class="user.is_admin ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ user.is_admin ? t('admin.adminBadge') : t('admin.userBadge') }}
                            </span>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400">
                            <span>{{ user.brands_count || 0 }} {{ t('navigation.brands') }}</span>
                            <span>{{ formatDate(user.created_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="adminUsersStore.pagination.lastPage > 1" class="mt-6 flex items-center justify-between">
                    <p class="text-sm text-gray-500">
                        {{ t('admin.totalUsers', { count: adminUsersStore.pagination.total }) }}
                    </p>
                    <div class="flex items-center gap-1">
                        <button
                            @click="goToPage(adminUsersStore.pagination.currentPage - 1)"
                            :disabled="adminUsersStore.pagination.currentPage <= 1"
                            class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ t('common.back') }}
                        </button>
                        <span class="px-3 py-1.5 text-sm text-gray-600">
                            {{ adminUsersStore.pagination.currentPage }} / {{ adminUsersStore.pagination.lastPage }}
                        </span>
                        <button
                            @click="goToPage(adminUsersStore.pagination.currentPage + 1)"
                            :disabled="adminUsersStore.pagination.currentPage >= adminUsersStore.pagination.lastPage"
                            class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ t('common.next') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit User Modal -->
        <EditUserModal
            :show="showEditModal"
            :user="selectedUser"
            @close="showEditModal = false"
            @updated="handleUserUpdated"
        />
    </div>
</template>
