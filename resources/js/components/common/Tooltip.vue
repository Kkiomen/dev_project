<script setup>
defineProps({
    text: {
        type: String,
        required: true,
    },
    position: {
        type: String,
        default: 'top',
        validator: (v) => ['top', 'bottom', 'left', 'right'].includes(v),
    },
});

const positionClasses = {
    top: 'bottom-full left-1/2 -translate-x-1/2 mb-2',
    bottom: 'top-full left-1/2 -translate-x-1/2 mt-2',
    left: 'right-full top-1/2 -translate-y-1/2 mr-2',
    right: 'left-full top-1/2 -translate-y-1/2 ml-2',
};
</script>

<template>
    <div class="relative group inline-flex">
        <slot />
        <div
            :class="[
                'absolute z-50 px-2.5 py-1.5 text-xs font-medium text-white bg-gray-800 border border-gray-700 rounded-lg shadow-lg',
                'opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150 pointer-events-none whitespace-nowrap',
                positionClasses[position],
            ]"
        >
            {{ text }}
        </div>
    </div>
</template>
