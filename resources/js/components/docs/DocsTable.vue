<script setup>
defineProps({
    headers: {
        type: Array,
        required: true,
    },
    rows: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <div class="overflow-x-auto mb-4">
        <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg overflow-hidden">
            <thead class="bg-gray-50">
                <tr>
                    <th
                        v-for="(header, index) in headers"
                        :key="index"
                        class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"
                    >
                        {{ header }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="(row, rowIndex) in rows" :key="rowIndex" class="hover:bg-gray-50">
                    <td
                        v-for="(cell, cellIndex) in row"
                        :key="cellIndex"
                        class="px-4 py-3 text-sm"
                        :class="cellIndex === 0 ? 'font-mono text-blue-600' : 'text-gray-600'"
                    >
                        {{ cell }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
