<script setup>
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAdminUsersStore } from '@/stores/adminUsers';
import { useAuthStore } from '@/stores/auth';
import { useToast } from '@/composables/useToast';
import Modal from '@/components/common/Modal.vue';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    user: { type: Object, default: null },
});

const emit = defineEmits(['close', 'updated', 'deleted']);

const { t } = useI18n();
const adminUsersStore = useAdminUsersStore();
const authStore = useAuthStore();
const toast = useToast();

const activeTab = ref('profile');
const saving = ref(false);

const form = ref({
    name: '',
    email: '',
    is_admin: false,
});

const passwordForm = ref({
    password: '',
    password_confirmation: '',
});

const notifications = ref([]);
const notificationsLoading = ref(false);

const isSelf = computed(() => authStore.user?.id === props.user?.id);

watch(() => props.show, (val) => {
    if (val && props.user) {
        form.value = {
            name: props.user.name || '',
            email: props.user.email || '',
            is_admin: props.user.is_admin || false,
        };
        passwordForm.value = { password: '', password_confirmation: '' };
        activeTab.value = 'profile';
        notifications.value = [];
    }
});

const loadNotifications = async () => {
    if (!props.user) return;
    notificationsLoading.value = true;
    try {
        const data = await adminUsersStore.fetchUserNotifications(props.user.id);
        notifications.value = data;
    } catch (error) {
        console.error('Failed to load notifications:', error);
    } finally {
        notificationsLoading.value = false;
    }
};

watch(activeTab, (tab) => {
    if (tab === 'notifications' && notifications.value.length === 0) {
        loadNotifications();
    }
});

const handleProfileSubmit = async () => {
    saving.value = true;
    try {
        await adminUsersStore.updateUser(props.user.id, form.value);
        toast.success(t('admin.userUpdated'));
        emit('updated');
    } catch (error) {
        const message = error.response?.data?.message || error.response?.data?.errors;
        if (message) {
            toast.error(typeof message === 'string' ? message : Object.values(message).flat().join(', '));
        }
    } finally {
        saving.value = false;
    }
};

const handlePasswordSubmit = async () => {
    saving.value = true;
    try {
        await adminUsersStore.updatePassword(props.user.id, passwordForm.value);
        toast.success(t('admin.passwordChanged'));
        passwordForm.value = { password: '', password_confirmation: '' };
    } catch (error) {
        const message = error.response?.data?.message || error.response?.data?.errors;
        if (message) {
            toast.error(typeof message === 'string' ? message : Object.values(message).flat().join(', '));
        }
    } finally {
        saving.value = false;
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString();
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="$emit('close')">
        <!-- Header -->
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-gray-900">{{ t('admin.editUser') }}</h2>
            </div>
            <button
                @click="$emit('close')"
                class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-200 mb-5">
            <button
                v-for="tab in ['profile', 'password', 'notifications']"
                :key="tab"
                @click="activeTab = tab"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
                :class="activeTab === tab
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            >
                {{ t(`admin.${tab}`) }}
            </button>
        </div>

        <!-- Profile Tab -->
        <form v-if="activeTab === 'profile'" @submit.prevent="handleProfileSubmit" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('admin.name') }}</label>
                <input
                    v-model="form.name"
                    type="text"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    required
                />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('admin.email') }}</label>
                <input
                    v-model="form.email"
                    type="email"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    required
                />
            </div>
            <div class="flex items-center gap-3">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input v-model="form.is_admin" type="checkbox" class="sr-only peer" />
                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
                <span class="text-sm font-medium text-gray-700">{{ t('admin.isAdmin') }}</span>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <Button variant="secondary" @click="$emit('close')">{{ t('common.cancel') }}</Button>
                <Button type="submit" :loading="saving">{{ t('common.save') }}</Button>
            </div>
        </form>

        <!-- Password Tab -->
        <form v-else-if="activeTab === 'password'" @submit.prevent="handlePasswordSubmit" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('admin.newPassword') }}</label>
                <input
                    v-model="passwordForm.password"
                    type="password"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    required
                    minlength="8"
                />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('admin.confirmPassword') }}</label>
                <input
                    v-model="passwordForm.password_confirmation"
                    type="password"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    required
                    minlength="8"
                />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <Button variant="secondary" @click="$emit('close')">{{ t('common.cancel') }}</Button>
                <Button type="submit" :loading="saving" :disabled="!passwordForm.password || passwordForm.password !== passwordForm.password_confirmation">
                    {{ t('admin.changePassword') }}
                </Button>
            </div>
        </form>

        <!-- Notifications Tab -->
        <div v-else-if="activeTab === 'notifications'">
            <div v-if="notificationsLoading" class="flex justify-center py-10">
                <svg class="animate-spin h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <div v-else-if="notifications.length === 0" class="text-center py-10">
                <svg class="mx-auto w-10 h-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <p class="text-sm text-gray-500">{{ t('admin.noNotifications') }}</p>
            </div>

            <div v-else class="space-y-2 max-h-80 overflow-y-auto">
                <div
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="p-3 rounded-lg border text-sm"
                    :class="notification.read_at ? 'bg-white border-gray-100' : 'bg-blue-50 border-blue-100'"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-medium text-gray-900">{{ notification.title }}</p>
                            <p class="text-gray-500 mt-0.5">{{ notification.message }}</p>
                        </div>
                        <span
                            v-if="!notification.read_at"
                            class="flex-shrink-0 w-2 h-2 mt-1.5 rounded-full bg-blue-500"
                        ></span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5">{{ formatDate(notification.created_at) }}</p>
                </div>
            </div>
        </div>
    </Modal>
</template>
