<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
});

const isUser = computed(() => props.message.role === 'user');
const isError = computed(() => props.message.isError === true);

const formattedTime = computed(() => {
    if (!props.message.timestamp) return '';
    const date = new Date(props.message.timestamp);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
});

// Check if message has executed actions
const hasActions = computed(() => {
    return props.message.actions && props.message.actions.length > 0;
});

const actionSummaries = computed(() => {
    if (!props.message.actions) return [];

    return props.message.actions
        .filter(a => a.type !== 'api_info' && a.type !== 'error')
        .map(action => {
            switch (action.type) {
                case 'modify_layer':
                    return {
                        icon: 'edit',
                        text: t('graphics.aiChat.actions.layerModified', { name: action.data?.layerName || 'layer' }),
                    };
                case 'add_layer':
                    return {
                        icon: 'plus',
                        text: t('graphics.aiChat.actions.layerAdded', { name: action.data?.name || 'layer' }),
                    };
                case 'delete_layer':
                    return {
                        icon: 'trash',
                        text: t('graphics.aiChat.actions.layerDeleted', { name: action.data?.layerName || 'layer' }),
                    };
                case 'update_template':
                    return {
                        icon: 'settings',
                        text: t('graphics.aiChat.actions.templateUpdated'),
                    };
                default:
                    return null;
            }
        })
        .filter(Boolean);
});
</script>

<template>
    <div
        :class="[
            'flex',
            isUser ? 'justify-end' : 'justify-start',
        ]"
    >
        <div
            :class="[
                'max-w-[85%] rounded-lg px-3 py-2',
                isUser
                    ? 'bg-purple-600 text-white'
                    : isError
                        ? 'bg-red-50 text-red-700 border border-red-200'
                        : 'bg-gray-100 text-gray-900',
            ]"
        >
            <!-- Message content -->
            <div class="text-sm whitespace-pre-wrap break-words">
                {{ message.content }}
            </div>

            <!-- Action indicators -->
            <div
                v-if="hasActions && actionSummaries.length > 0"
                class="mt-2 pt-2 border-t"
                :class="isUser ? 'border-purple-500' : 'border-gray-200'"
            >
                <div
                    v-for="(action, index) in actionSummaries"
                    :key="index"
                    class="flex items-center gap-1.5 text-xs"
                    :class="isUser ? 'text-purple-200' : 'text-gray-500'"
                >
                    <!-- Edit icon -->
                    <svg v-if="action.icon === 'edit'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <!-- Plus icon -->
                    <svg v-else-if="action.icon === 'plus'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <!-- Trash icon -->
                    <svg v-else-if="action.icon === 'trash'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <!-- Settings icon -->
                    <svg v-else-if="action.icon === 'settings'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span>{{ action.text }}</span>
                </div>
            </div>

            <!-- Timestamp -->
            <div
                v-if="formattedTime"
                class="mt-1 text-[10px]"
                :class="isUser ? 'text-purple-300' : 'text-gray-400'"
            >
                {{ formattedTime }}
            </div>
        </div>
    </div>
</template>
