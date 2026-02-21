<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import Modal from '@/components/common/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    dateStr: { type: String, default: '' },
    planId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'created']);

const { t } = useI18n();
const managerStore = useManagerStore();
const toast = useToast();

const platforms = ['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube'];
const contentTypes = ['post', 'carousel', 'video', 'reel', 'story', 'article', 'thread', 'poll'];

const platform = ref('instagram');
const contentType = ref('post');
const pillar = ref('');
const time = ref('');
const topic = ref('');
const description = ref('');
const suggesting = ref(false);
const submitting = ref(false);

const pillars = computed(() => {
    const cp = managerStore.strategy?.content_pillars;
    if (!cp || !Array.isArray(cp)) return [];
    return cp.map(p => typeof p === 'string' ? p : (p.name || p.label || ''));
});

const canSubmit = computed(() => {
    return platform.value && contentType.value && !submitting.value;
});

const resetForm = () => {
    platform.value = 'instagram';
    contentType.value = 'post';
    pillar.value = '';
    time.value = '';
    topic.value = '';
    description.value = '';
    suggesting.value = false;
    submitting.value = false;
};

watch(() => props.show, (show) => {
    if (show) resetForm();
});

const handleSuggestTopic = async () => {
    if (!props.planId || suggesting.value) return;

    suggesting.value = true;
    try {
        const result = await managerStore.generateTopicProposition(props.planId, {
            platform: platform.value,
            content_type: contentType.value,
            date: props.dateStr,
            pillar: pillar.value || null,
        });

        if (result?.success) {
            topic.value = result.topic || '';
            description.value = result.description || '';
        } else if (result?.error_code === 'no_api_key') {
            toast.error(t('manager.addSlot.noApiKey'));
        } else {
            toast.error(result?.error || t('manager.addSlot.suggestError'));
        }
    } catch {
        toast.error(t('manager.addSlot.suggestError'));
    } finally {
        suggesting.value = false;
    }
};

const buildSlotData = () => ({
    scheduled_date: props.dateStr,
    scheduled_time: time.value || null,
    platform: platform.value,
    content_type: contentType.value,
    topic: topic.value || null,
    description: description.value || null,
    pillar: pillar.value || null,
});

const handleAddSlot = async () => {
    if (!props.planId || submitting.value) return;

    submitting.value = true;
    try {
        await managerStore.addPlanSlot(props.planId, buildSlotData());
        toast.success(t('manager.addSlot.success'));
        emit('created');
        emit('close');
    } catch {
        toast.error(t('manager.addSlot.error'));
    } finally {
        submitting.value = false;
    }
};

const handleAddAndGenerate = async () => {
    if (!props.planId || submitting.value) return;

    submitting.value = true;
    try {
        const slot = await managerStore.addPlanSlot(props.planId, buildSlotData());
        if (slot?.id) {
            await managerStore.generateSlotContent(props.planId, slot.id);
            toast.success(t('manager.addSlot.generateStarted'));
        } else {
            toast.success(t('manager.addSlot.success'));
        }
        emit('created');
        emit('close');
    } catch {
        toast.error(t('manager.addSlot.error'));
    } finally {
        submitting.value = false;
    }
};

const close = () => {
    if (submitting.value) return;
    emit('close');
};

const formattedDate = computed(() => {
    if (!props.dateStr) return '';
    const date = new Date(props.dateStr + 'T00:00:00');
    return date.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
});
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close" variant="dark">
        <div>
            <!-- Header -->
            <div class="p-5 border-b border-gray-800">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-white">{{ t('manager.addSlot.title') }}</h3>
                        <p v-if="formattedDate" class="text-sm text-gray-400 mt-0.5">{{ formattedDate }}</p>
                    </div>
                    <button
                        @click="close"
                        :disabled="submitting"
                        class="shrink-0 p-1.5 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-gray-800 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                <!-- Platform -->
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        {{ t('manager.addSlot.platform') }}
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="p in platforms"
                            :key="p"
                            @click="platform = p"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors capitalize"
                            :class="platform === p
                                ? 'bg-indigo-600 border-indigo-500 text-white'
                                : 'bg-gray-800 border-gray-700 text-gray-400 hover:border-gray-600 hover:text-gray-300'"
                        >
                            {{ p }}
                        </button>
                    </div>
                </div>

                <!-- Content Type -->
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        {{ t('manager.addSlot.contentType') }}
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="ct in contentTypes"
                            :key="ct"
                            @click="contentType = ct"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors capitalize"
                            :class="contentType === ct
                                ? 'bg-indigo-600 border-indigo-500 text-white'
                                : 'bg-gray-800 border-gray-700 text-gray-400 hover:border-gray-600 hover:text-gray-300'"
                        >
                            {{ ct }}
                        </button>
                    </div>
                </div>

                <!-- Pillar (optional) -->
                <div v-if="pillars.length > 0">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        {{ t('manager.addSlot.pillar') }}
                    </label>
                    <select
                        v-model="pillar"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                    >
                        <option value="">{{ t('manager.addSlot.pillarPlaceholder') }}</option>
                        <option v-for="p in pillars" :key="p" :value="p">{{ p }}</option>
                    </select>
                </div>

                <!-- Time -->
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        {{ t('manager.addSlot.time') }}
                    </label>
                    <input
                        v-model="time"
                        type="time"
                        class="w-full sm:w-40 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                    />
                </div>

                <!-- Topic + AI Suggest -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            {{ t('manager.addSlot.topic') }}
                        </label>
                        <button
                            @click="handleSuggestTopic"
                            :disabled="suggesting || !planId"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-md bg-indigo-600/20 text-indigo-400 hover:bg-indigo-600/30 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg v-if="suggesting" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                            </svg>
                            {{ suggesting ? t('manager.addSlot.suggesting') : t('manager.addSlot.suggestTopic') }}
                        </button>
                    </div>
                    <textarea
                        v-model="topic"
                        rows="2"
                        :placeholder="t('manager.addSlot.topicPlaceholder')"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none resize-none"
                    ></textarea>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        {{ t('manager.addSlot.description') }}
                    </label>
                    <textarea
                        v-model="description"
                        rows="3"
                        :placeholder="t('manager.addSlot.descriptionPlaceholder')"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none resize-none"
                    ></textarea>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-5 border-t border-gray-800 flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2 sm:gap-3">
                <button
                    @click="close"
                    :disabled="submitting"
                    class="px-4 py-2 text-sm font-medium rounded-lg text-gray-400 hover:text-gray-200 hover:bg-gray-800 transition-colors disabled:opacity-50 order-3 sm:order-1"
                >
                    {{ t('common.cancel') }}
                </button>
                <button
                    @click="handleAddSlot"
                    :disabled="!canSubmit"
                    class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed order-2"
                >
                    {{ t('manager.addSlot.addSlot') }}
                </button>
                <button
                    @click="handleAddAndGenerate"
                    :disabled="!canSubmit || !topic"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed order-1 sm:order-3"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                    </svg>
                    {{ t('manager.addSlot.addAndGenerate') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
