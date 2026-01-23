<script setup>
defineProps({
    method: {
        type: String,
        required: true,
    },
    path: {
        type: String,
        required: true,
    },
});

const methodColors = {
    GET: 'bg-green-100 text-green-700 border-green-200',
    POST: 'bg-blue-100 text-blue-700 border-blue-200',
    PUT: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    PATCH: 'bg-orange-100 text-orange-700 border-orange-200',
    DELETE: 'bg-red-100 text-red-700 border-red-200',
};
</script>

<template>
    <div class="flex items-center gap-3 p-3 bg-gray-50 border border-gray-200 rounded-lg mb-4 font-mono text-sm">
        <span
            class="px-2.5 py-1 text-xs font-bold rounded border"
            :class="methodColors[method] || 'bg-gray-100 text-gray-700'"
        >
            {{ method }}
        </span>
        <span class="text-gray-700 break-all">{{ path }}</span>
    </div>
</template>
