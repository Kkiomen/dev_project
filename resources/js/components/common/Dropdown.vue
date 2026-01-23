<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'left',
        validator: (value) => ['left', 'right'].includes(value),
    },
    width: {
        type: String,
        default: '48',
    },
});

const open = ref(false);
const dropdownRef = ref(null);

const toggle = () => {
    open.value = !open.value;
};

const close = () => {
    open.value = false;
};

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        close();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <div @click="toggle">
            <slot name="trigger" />
        </div>

        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-show="open"
                class="absolute z-50 mt-2 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                :class="[
                    `w-${width}`,
                    align === 'left' ? 'left-0 origin-top-left' : 'right-0 origin-top-right',
                ]"
            >
                <div class="py-1" @click="close">
                    <slot name="content" />
                </div>
            </div>
        </Transition>
    </div>
</template>
