<template>
    <div class="flex flex-1 overflow-hidden">
        <!-- Left Sidebar: Media Panel -->
        <div
            v-if="showMediaPanel"
            class="w-64 border-r border-gray-700 bg-gray-900 flex flex-col shrink-0"
        >
            <slot name="media" />
        </div>

        <!-- Center: Preview -->
        <div class="flex-1 flex flex-col min-w-0 bg-gray-950">
            <slot name="preview" />
        </div>

        <!-- Right Sidebar: Inspector -->
        <div
            v-if="showInspector"
            class="w-72 border-l border-gray-700 bg-gray-900 flex flex-col shrink-0"
        >
            <slot name="inspector" />
        </div>
    </div>
</template>

<script setup>
defineProps({
    showMediaPanel: { type: Boolean, default: true },
    showInspector: { type: Boolean, default: true },
});
</script>
