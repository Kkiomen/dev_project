import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useNotificationsStore = defineStore('notifications', () => {
    const notifications = ref([]);
    const unreadCount = ref(0);
    const loading = ref(false);
    const initialized = ref(false);

    const hasUnread = computed(() => unreadCount.value > 0);

    const fetchNotifications = async () => {
        if (loading.value) return;

        loading.value = true;
        try {
            const response = await window.axios.get('/api/v1/notifications');
            notifications.value = response.data.notifications;
            unreadCount.value = response.data.unread_count;
            initialized.value = true;
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        } finally {
            loading.value = false;
        }
    };

    const addNotification = (notification) => {
        // Add to the beginning of the list
        notifications.value.unshift(notification);
        unreadCount.value++;
    };

    const markAsRead = async (notificationId) => {
        try {
            await window.axios.post(`/api/v1/notifications/${notificationId}/mark-read`);

            const notification = notifications.value.find((n) => n.id === notificationId);
            if (notification && !notification.read_at) {
                notification.read_at = new Date().toISOString();
                unreadCount.value = Math.max(0, unreadCount.value - 1);
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    };

    const markAllAsRead = async () => {
        try {
            await window.axios.post('/api/v1/notifications/mark-all-read');

            notifications.value.forEach((n) => {
                if (!n.read_at) {
                    n.read_at = new Date().toISOString();
                }
            });
            unreadCount.value = 0;
        } catch (error) {
            console.error('Failed to mark all notifications as read:', error);
        }
    };

    const setupWebSocket = (userId) => {
        if (!window.Echo || !userId) return;

        window.Echo.private(`user.${userId}`).listen('.notification.created', (e) => {
            addNotification(e);
        });
    };

    const cleanupWebSocket = (userId) => {
        if (!window.Echo || !userId) return;
        window.Echo.leave(`user.${userId}`);
    };

    return {
        notifications,
        unreadCount,
        loading,
        initialized,
        hasUnread,
        fetchNotifications,
        addNotification,
        markAsRead,
        markAllAsRead,
        setupWebSocket,
        cleanupWebSocket,
    };
});
