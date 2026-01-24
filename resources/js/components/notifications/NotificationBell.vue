<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useNotificationsStore } from '@/stores/notifications';
import { useAuthStore } from '@/stores/auth';
import { formatDistanceToNow } from 'date-fns';
import { pl, enUS } from 'date-fns/locale';

const { t, locale } = useI18n();
const router = useRouter();
const notificationsStore = useNotificationsStore();
const authStore = useAuthStore();

const isOpen = ref(false);
const dropdownRef = ref(null);

const dateLocale = computed(() => (locale.value === 'pl' ? pl : enUS));

const notificationIcons = {
    post_generated: `<svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`,
    post_published: `<svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>`,
    approval_required: `<svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>`,
};

const getNotificationTitle = (notification) => {
    const typeMap = {
        post_generated: 'notifications.postGenerated.title',
        post_published: 'notifications.postPublished.title',
        approval_required: 'notifications.approvalRequired.title',
    };
    return t(typeMap[notification.type] || notification.type);
};

const getNotificationMessage = (notification) => {
    const data = notification.data || {};

    switch (notification.type) {
        case 'post_generated':
            return t('notifications.postGenerated.message', {
                title: data.post_title || '',
                brand: data.brand_name || '',
            });
        case 'post_published':
            return t('notifications.postPublished.message', {
                title: data.post_title || '',
                platform: data.platform || '',
            });
        case 'approval_required':
            return t('notifications.approvalRequired.message', {
                title: data.post_title || '',
            });
        default:
            return notification.message || '';
    }
};

const formatTime = (dateString) => {
    return formatDistanceToNow(new Date(dateString), {
        addSuffix: true,
        locale: dateLocale.value,
    });
};

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
};

const handleNotificationClick = async (notification) => {
    if (!notification.read_at) {
        await notificationsStore.markAsRead(notification.id);
    }

    // Navigate based on notification type
    if (notification.data?.post_id) {
        router.push({ name: 'post.edit', params: { postId: notification.data.post_id } });
    }

    isOpen.value = false;
};

const handleMarkAllRead = async () => {
    await notificationsStore.markAllAsRead();
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);

    // Fetch notifications if not initialized
    if (!notificationsStore.initialized) {
        notificationsStore.fetchNotifications();
    }

    // Setup WebSocket listener
    if (authStore.user?.id) {
        notificationsStore.setupWebSocket(authStore.user.id);
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);

    if (authStore.user?.id) {
        notificationsStore.cleanupWebSocket(authStore.user.id);
    }
});
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <!-- Bell Button -->
        <button
            @click="toggleDropdown"
            class="relative p-2 text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-full transition-colors"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                />
            </svg>

            <!-- Unread Badge -->
            <span
                v-if="notificationsStore.hasUnread"
                class="absolute top-0 right-0 flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full transform translate-x-1 -translate-y-1"
            >
                {{ notificationsStore.unreadCount > 9 ? '9+' : notificationsStore.unreadCount }}
            </span>
        </button>

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 z-50"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">
                        {{ t('notifications.title') }}
                    </h3>
                    <button
                        v-if="notificationsStore.hasUnread"
                        @click="handleMarkAllRead"
                        class="text-xs text-blue-600 hover:text-blue-700 font-medium"
                    >
                        {{ t('notifications.markAllRead') }}
                    </button>
                </div>

                <!-- Notifications List -->
                <div class="max-h-96 overflow-y-auto">
                    <div v-if="notificationsStore.loading" class="flex items-center justify-center py-8">
                        <svg class="w-6 h-6 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24">
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                    </div>

                    <div
                        v-else-if="notificationsStore.notifications.length === 0"
                        class="flex flex-col items-center justify-center py-8 text-gray-500"
                    >
                        <svg class="w-12 h-12 mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                            />
                        </svg>
                        <p class="text-sm">{{ t('notifications.empty') }}</p>
                    </div>

                    <div v-else>
                        <button
                            v-for="notification in notificationsStore.notifications"
                            :key="notification.id"
                            @click="handleNotificationClick(notification)"
                            class="w-full flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors text-left"
                            :class="{ 'bg-blue-50': !notification.read_at }"
                        >
                            <!-- Icon -->
                            <div
                                class="flex-shrink-0 mt-0.5"
                                v-html="notificationIcons[notification.type] || notificationIcons.post_generated"
                            />

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ getNotificationTitle(notification) }}
                                </p>
                                <p class="text-sm text-gray-600 line-clamp-2">
                                    {{ getNotificationMessage(notification) }}
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ formatTime(notification.created_at) }}
                                </p>
                            </div>

                            <!-- Unread indicator -->
                            <div v-if="!notification.read_at" class="flex-shrink-0">
                                <span class="block w-2 h-2 bg-blue-500 rounded-full" />
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
