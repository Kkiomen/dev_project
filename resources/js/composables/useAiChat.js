import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useGraphicsStore } from '@/stores/graphics';

// Storage key for persisting chat history
const STORAGE_KEY_PREFIX = 'ai_chat_history_';

export function useAiChat() {
    const messages = ref([]);
    const isLoading = ref(false);
    const error = ref(null);
    const graphicsStore = useGraphicsStore();

    // Generate unique ID for messages
    const generateId = () => `msg_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

    // Get storage key for current template
    const getStorageKey = () => {
        const templateId = graphicsStore.currentTemplate?.id;
        return templateId ? `${STORAGE_KEY_PREFIX}${templateId}` : null;
    };

    // Load messages from localStorage
    const loadMessages = () => {
        const key = getStorageKey();
        if (!key) return;

        try {
            const stored = localStorage.getItem(key);
            if (stored) {
                messages.value = JSON.parse(stored);
            }
        } catch (e) {
            console.error('Failed to load chat history:', e);
        }
    };

    // Save messages to localStorage
    const saveMessages = () => {
        const key = getStorageKey();
        if (!key) return;

        try {
            // Keep only last 50 messages to avoid storage limits
            const toSave = messages.value.slice(-50);
            localStorage.setItem(key, JSON.stringify(toSave));
        } catch (e) {
            console.error('Failed to save chat history:', e);
        }
    };

    // Watch for template changes and load history
    watch(
        () => graphicsStore.currentTemplate?.id,
        (newId) => {
            if (newId) {
                loadMessages();
            } else {
                messages.value = [];
            }
        },
        { immediate: true }
    );

    // Save messages whenever they change
    watch(messages, saveMessages, { deep: true });

    /**
     * Send a message to the AI chat.
     */
    const sendMessage = async (userMessage) => {
        if (!userMessage.trim() || !graphicsStore.currentTemplate) {
            return;
        }

        // Add user message to history
        messages.value.push({
            id: generateId(),
            role: 'user',
            content: userMessage.trim(),
            timestamp: Date.now(),
        });

        isLoading.value = true;
        error.value = null;

        try {
            // Prepare history for API (only last 20 messages)
            const history = messages.value.slice(-21, -1).map(m => ({
                role: m.role,
                content: m.content,
            }));

            const response = await axios.post(
                `/api/v1/templates/${graphicsStore.currentTemplate.id}/ai/chat`,
                {
                    message: userMessage.trim(),
                    history,
                }
            );

            const { reply, actions } = response.data;

            // Add assistant message
            messages.value.push({
                id: generateId(),
                role: 'assistant',
                content: reply,
                actions: actions || [],
                timestamp: Date.now(),
            });

            // Apply any actions to the store
            if (actions && actions.length > 0) {
                await applyActions(actions);
            }

            return response.data;
        } catch (err) {
            error.value = err.response?.data?.error || err.message || 'Request failed';

            // Add error message to chat
            messages.value.push({
                id: generateId(),
                role: 'assistant',
                content: error.value,
                isError: true,
                timestamp: Date.now(),
            });

            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * Apply AI actions to the graphics store.
     */
    const applyActions = async (actions) => {
        for (const action of actions) {
            try {
                switch (action.type) {
                    case 'modify_layer':
                        await handleModifyLayer(action.data);
                        break;

                    case 'add_layer':
                        await handleAddLayer(action.data);
                        break;

                    case 'delete_layer':
                        await handleDeleteLayer(action.data);
                        break;

                    case 'update_template':
                        await handleUpdateTemplate(action.data);
                        break;

                    case 'api_info':
                        // No action needed - info is in the reply
                        break;

                    case 'error':
                        console.warn('AI action error:', action.message);
                        break;
                }
            } catch (err) {
                console.error(`Failed to apply action ${action.type}:`, err);
            }
        }
    };

    /**
     * Handle modify_layer action.
     */
    const handleModifyLayer = async (data) => {
        const { layerId, changes } = data;

        if (!layerId || !changes) return;

        // Update locally first for immediate feedback
        graphicsStore.updateLayerLocally(layerId, changes);
    };

    /**
     * Handle add_layer action.
     */
    const handleAddLayer = async (data) => {
        const { name, type, x, y, width, height, properties } = data;

        await graphicsStore.addLayer(type, {
            name,
            x,
            y,
            width,
            height,
            properties,
        });
    };

    /**
     * Handle delete_layer action.
     */
    const handleDeleteLayer = async (data) => {
        const { layerId } = data;

        if (!layerId) return;

        await graphicsStore.deleteLayer(layerId);
    };

    /**
     * Handle update_template action.
     */
    const handleUpdateTemplate = async (data) => {
        const { changes } = data;

        if (!changes || !graphicsStore.currentTemplate) return;

        // Update template locally
        if (changes.background_color) {
            graphicsStore.currentTemplate.background_color = changes.background_color;
        }
        if (changes.width) {
            graphicsStore.currentTemplate.width = changes.width;
        }
        if (changes.height) {
            graphicsStore.currentTemplate.height = changes.height;
        }

        graphicsStore.isDirty = true;
    };

    /**
     * Clear chat history.
     */
    const clearHistory = () => {
        messages.value = [];
        error.value = null;

        // Also clear from localStorage
        const key = getStorageKey();
        if (key) {
            localStorage.removeItem(key);
        }
    };

    /**
     * Get last message.
     */
    const lastMessage = computed(() => {
        return messages.value[messages.value.length - 1] || null;
    });

    /**
     * Check if chat has messages.
     */
    const hasMessages = computed(() => messages.value.length > 0);

    return {
        messages,
        isLoading,
        error,
        sendMessage,
        clearHistory,
        lastMessage,
        hasMessages,
    };
}
