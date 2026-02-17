<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal from '@/components/common/Modal.vue';

const props = defineProps({
    src: { type: String, required: true },
    alt: { type: String, default: '' },
    aspectClass: { type: String, default: 'aspect-[4/3]' },
    objectFit: { type: String, default: 'object-cover' },
});

const { t } = useI18n();
const showModal = ref(false);
</script>

<template>
    <div class="group/image relative rounded-lg overflow-hidden bg-gray-50" :class="aspectClass">
        <img :src="src" :alt="alt" class="w-full h-full" :class="objectFit" />

        <!-- Expand overlay icon -->
        <button
            @click.stop="showModal = true"
            class="absolute top-1.5 right-1.5 w-6 h-6 flex items-center justify-center rounded bg-black/40 text-white opacity-0 group-hover/image:opacity-100 transition-opacity cursor-pointer"
            :title="t('pipeline.output.expand')"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
            </svg>
        </button>

        <!-- Full-resolution modal -->
        <Modal :show="showModal" max-width="4xl" @close="showModal = false">
            <img :src="src" :alt="alt" class="w-full h-auto rounded-lg" />
        </Modal>
    </div>
</template>
