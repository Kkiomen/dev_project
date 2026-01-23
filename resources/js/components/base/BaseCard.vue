<script setup>
import { RouterLink } from 'vue-router';

defineProps({
    base: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['edit', 'delete']);
</script>

<template>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
        <RouterLink
            :to="{ name: 'base', params: { baseId: base.id } }"
            class="block p-6"
        >
            <div class="flex items-center space-x-4">
                <div
                    class="w-12 h-12 rounded-lg flex items-center justify-center text-white text-xl"
                    :style="{ backgroundColor: base.color || '#3B82F6' }"
                >
                    {{ base.icon || '🗃' }}
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-medium text-gray-900 truncate">
                        {{ base.name }}
                    </h3>
                    <p v-if="base.description" class="text-sm text-gray-500 truncate">
                        {{ base.description }}
                    </p>
                    <p class="text-sm text-gray-400 mt-1">
                        {{ base.tables_count || 0 }} tabel
                    </p>
                </div>
            </div>
        </RouterLink>

        <div class="px-6 py-3 border-t border-gray-100 flex justify-end space-x-2">
            <button
                @click.prevent="emit('edit', base)"
                class="text-sm text-gray-500 hover:text-gray-700"
            >
                Edytuj
            </button>
            <button
                @click.prevent="emit('delete', base)"
                class="text-sm text-red-500 hover:text-red-700"
            >
                Usuń
            </button>
        </div>
    </div>
</template>
