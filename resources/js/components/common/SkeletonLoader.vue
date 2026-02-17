<script setup>
defineProps({
    variant: {
        type: String,
        default: 'card-grid',
        validator: (v) => ['card-grid', 'stats', 'lines'].includes(v),
    },
    count: {
        type: Number,
        default: 6,
    },
});
</script>

<template>
    <!-- Card Grid variant -->
    <div v-if="variant === 'card-grid'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div
            v-for="i in count"
            :key="i"
            class="rounded-xl bg-gray-900 border border-gray-800 overflow-hidden"
        >
            <div class="aspect-[16/9] bg-gray-800 animate-pulse" />
            <div class="p-4 space-y-3">
                <div class="h-4 bg-gray-800 rounded animate-pulse w-3/4" />
                <div class="h-3 bg-gray-800 rounded animate-pulse w-full" />
                <div class="h-3 bg-gray-800 rounded animate-pulse w-1/2" />
                <div class="flex items-center justify-between pt-1">
                    <div class="flex gap-1">
                        <div v-for="j in 3" :key="j" class="w-6 h-6 rounded bg-gray-800 animate-pulse" />
                    </div>
                    <div class="h-3 bg-gray-800 rounded animate-pulse w-20" />
                </div>
            </div>
        </div>
    </div>

    <!-- Stats variant -->
    <div v-else-if="variant === 'stats'" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div
            v-for="i in count"
            :key="i"
            class="rounded-xl bg-gray-900 border border-gray-800 p-4"
        >
            <div class="h-3 bg-gray-800 rounded animate-pulse w-24 mb-3" />
            <div class="h-7 bg-gray-800 rounded animate-pulse w-16" />
        </div>
    </div>

    <!-- Lines variant -->
    <div v-else-if="variant === 'lines'" class="space-y-3">
        <div
            v-for="i in count"
            :key="i"
            class="h-4 bg-gray-800 rounded animate-pulse"
            :class="i % 3 === 0 ? 'w-1/2' : i % 2 === 0 ? 'w-3/4' : 'w-full'"
        />
    </div>
</template>
