<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import Modal from '@/components/common/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    slot: { type: Object, default: null },
    planId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'updated']);

const { t } = useI18n();
const managerStore = useManagerStore();
const toast = useToast();
const generating = ref(false);

const statusColorMap = {
    planned: 'bg-gray-500/20 text-gray-300 border-gray-500/30',
    content_ready: 'bg-blue-500/20 text-blue-400 border-blue-500/30',
    media_ready: 'bg-purple-500/20 text-purple-400 border-purple-500/30',
    approved: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
    published: 'bg-green-500/20 text-green-400 border-green-500/30',
    skipped: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
};

const platformColorMap = {
    instagram: 'bg-pink-500/20 text-pink-400',
    facebook: 'bg-blue-500/20 text-blue-400',
    tiktok: 'bg-gray-500/20 text-gray-300',
    linkedin: 'bg-sky-500/20 text-sky-400',
    x: 'bg-gray-500/20 text-gray-300',
    youtube: 'bg-red-500/20 text-red-400',
};

const statusColor = computed(() => {
    return statusColorMap[props.slot?.status] || statusColorMap.planned;
});

const platformColor = computed(() => {
    return platformColorMap[props.slot?.platform] || 'bg-gray-500/20 text-gray-300';
});

const isPlanned = computed(() => props.slot?.status === 'planned');

const formattedDate = computed(() => {
    if (!props.slot?.scheduled_date) return '';
    const date = new Date(props.slot.scheduled_date);
    return date.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
});

const formattedTime = computed(() => {
    if (!props.slot?.scheduled_time) return '';
    return props.slot.scheduled_time.substring(0, 5);
});

const handleGenerateContent = async () => {
    if (!props.planId || !props.slot?.id) return;

    generating.value = true;
    try {
        await managerStore.generateSlotContent(props.planId, props.slot.id);
        toast.success(t('manager.slotModal.generateSuccess'));
        emit('updated');
        emit('close');
    } catch {
        toast.error(t('manager.slotModal.generateError'));
    } finally {
        generating.value = false;
    }
};

const close = () => {
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div v-if="slot" class="bg-gray-900 -m-6 rounded-xl">
            <!-- Header -->
            <div class="p-5 border-b border-gray-800">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold text-white truncate">
                            {{ slot.topic || t('manager.slotModal.title') }}
                        </h3>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                :class="statusColor"
                            >
                                {{ t(`manager.slotModal.statusLabels.${slot.status}`) }}
                            </span>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                :class="platformColor"
                            >
                                {{ slot.platform }}
                            </span>
                        </div>
                    </div>
                    <button
                        @click="close"
                        class="shrink-0 p-1.5 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-gray-800 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="p-5 space-y-4">
                <!-- Schedule info -->
                <div v-if="formattedDate" class="flex items-center gap-2 text-sm">
                    <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    <span class="text-gray-300">{{ formattedDate }}</span>
                    <span v-if="formattedTime" class="text-gray-500">{{ formattedTime }}</span>
                </div>

                <!-- Content type & Pillar -->
                <div class="grid grid-cols-2 gap-3">
                    <div v-if="slot.content_type" class="rounded-lg bg-gray-800/50 border border-gray-700/50 p-3">
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                            {{ t('manager.slotModal.contentType') }}
                        </p>
                        <p class="text-sm text-gray-200 capitalize">{{ slot.content_type }}</p>
                    </div>
                    <div v-if="slot.pillar" class="rounded-lg bg-gray-800/50 border border-gray-700/50 p-3">
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                            {{ t('manager.slotModal.pillar') }}
                        </p>
                        <p class="text-sm text-gray-200">{{ slot.pillar }}</p>
                    </div>
                </div>

                <!-- Description -->
                <div v-if="slot.description" class="rounded-lg bg-gray-800/50 border border-gray-700/50 p-3">
                    <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                        {{ t('manager.slotModal.description') }}
                    </p>
                    <p class="text-sm text-gray-300 leading-relaxed">{{ slot.description }}</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-5 border-t border-gray-800 flex items-center justify-end gap-3">
                <button
                    @click="close"
                    class="px-4 py-2 text-sm font-medium rounded-lg text-gray-400 hover:text-gray-200 hover:bg-gray-800 transition-colors"
                >
                    {{ t('manager.slotModal.title') }}
                </button>

                <button
                    v-if="isPlanned"
                    @click="handleGenerateContent"
                    :disabled="generating"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <svg v-if="generating" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                    </svg>
                    {{ generating ? t('manager.slotModal.generating') : t('manager.slotModal.generateContent') }}
                </button>

                <button
                    v-else
                    @click="close"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    {{ t('manager.slotModal.viewContent') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
