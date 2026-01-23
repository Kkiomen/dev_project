<script setup>
defineProps({
    row: {
        type: Object,
        required: true,
    },
    primaryValue: {
        type: String,
        default: 'Bez nazwy',
    },
    secondaryValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['click', 'dragstart']);
</script>

<template>
    <div
        class="bg-white rounded-lg shadow p-3 cursor-pointer hover:shadow-md transition-shadow"
        :data-row-id="row.id"
        draggable="true"
        @click="emit('click', row)"
        @dragstart="emit('dragstart', $event, row)"
    >
        <div class="font-medium text-gray-900 truncate">
            {{ primaryValue }}
        </div>
        <div v-if="secondaryValue" class="mt-2 text-sm text-gray-500 line-clamp-2">
            {{ secondaryValue }}
        </div>
    </div>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
