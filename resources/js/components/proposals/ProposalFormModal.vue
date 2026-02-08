<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useProposalsStore } from '@/stores/proposals';
import { useBrandsStore } from '@/stores/brands';
import Modal from '@/components/common/Modal.vue';
import ProposalKeywordBadge from './ProposalKeywordBadge.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    proposal: { type: Object, default: null },
});

const emit = defineEmits(['close', 'save']);
const { t } = useI18n();
const proposalsStore = useProposalsStore();
const brandsStore = useBrandsStore();

const form = ref({
    scheduled_date: '',
    scheduled_time: '',
    title: '',
    keywords: [],
    notes: '',
});

const keywordInput = ref('');
const nativeDateInput = ref(null);
const saving = ref(false);
const nextFreeDate = ref(null);
const loadingNextFree = ref(false);
const nextFreeFetched = ref(false);

watch(() => props.show, async (val) => {
    if (val) {
        if (props.proposal) {
            form.value = {
                scheduled_date: props.proposal.scheduled_date || '',
                scheduled_time: props.proposal.scheduled_time || '',
                title: props.proposal.title || '',
                keywords: [...(props.proposal.keywords || [])],
                notes: props.proposal.notes || '',
            };
            syncDateDisplay(props.proposal.scheduled_date || '');
            nextFreeDate.value = null;
            nextFreeFetched.value = false;
        } else {
            form.value = {
                scheduled_date: '',
                scheduled_time: '',
                title: '',
                keywords: [],
                notes: '',
            };
            dateDisplay.value = '';
            loadingNextFree.value = true;
            nextFreeDate.value = null;
            nextFreeFetched.value = false;
            try {
                nextFreeDate.value = await proposalsStore.fetchNextFreeDate(brandsStore.currentBrand?.id);
            } catch {
                nextFreeDate.value = null;
            } finally {
                loadingNextFree.value = false;
                nextFreeFetched.value = true;
            }
        }
        keywordInput.value = '';
    }
});

function addKeyword() {
    const kw = keywordInput.value.trim();
    if (kw && !form.value.keywords.includes(kw) && form.value.keywords.length < 20) {
        form.value.keywords.push(kw);
        keywordInput.value = '';
    }
}

function removeKeyword(kw) {
    form.value.keywords = form.value.keywords.filter(k => k !== kw);
}

function handleKeywordKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addKeyword();
    }
}

function useNextFreeDate() {
    if (nextFreeDate.value) {
        form.value.scheduled_date = nextFreeDate.value;
        syncDateDisplay(nextFreeDate.value);
    }
}

function formatHintDate(dateStr) {
    if (!dateStr) return '';
    const [y, m, d] = dateStr.split('-');
    return `${d}/${m}/${y}`;
}

// Display date in dd/mm/yyyy, store as yyyy-mm-dd
const dateDisplay = ref('');

function syncDateDisplay(isoDate) {
    if (!isoDate) {
        dateDisplay.value = '';
        return;
    }
    const [y, m, d] = isoDate.split('-');
    if (y && m && d) {
        dateDisplay.value = `${d}/${m}/${y}`;
    }
}

function onDateDisplayInput(e) {
    let val = e.target.value.replace(/[^0-9/]/g, '');

    // Auto-insert slashes
    const digits = val.replace(/\//g, '');
    if (digits.length >= 5) {
        val = digits.slice(0, 2) + '/' + digits.slice(2, 4) + '/' + digits.slice(4, 8);
    } else if (digits.length >= 3) {
        val = digits.slice(0, 2) + '/' + digits.slice(2);
    }

    dateDisplay.value = val;

    // Parse dd/mm/yyyy to yyyy-mm-dd
    const match = val.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (match) {
        form.value.scheduled_date = `${match[3]}-${match[2]}-${match[1]}`;
    } else {
        form.value.scheduled_date = '';
    }
}

function onNativeDateChange(e) {
    const val = e.target.value;
    if (val) {
        form.value.scheduled_date = val;
        syncDateDisplay(val);
    }
}

async function handleSubmit() {
    saving.value = true;
    try {
        emit('save', { ...form.value });
    } finally {
        saving.value = false;
    }
}

const isEdit = () => !!props.proposal;
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="space-y-5">
            <h3 class="text-lg font-semibold text-gray-900">
                {{ isEdit() ? t('postAutomation.proposals.form.editTitle') : t('postAutomation.proposals.form.createTitle') }}
            </h3>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Scheduled Date & Time -->
                <div>
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('postAutomation.proposals.form.scheduledDate') }}
                            </label>
                            <div class="relative">
                                <input
                                    :value="dateDisplay"
                                    @input="onDateDisplayInput"
                                    type="text"
                                    inputmode="numeric"
                                    placeholder="dd/mm/yyyy"
                                    maxlength="10"
                                    required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                                <input
                                    ref="nativeDateInput"
                                    :value="form.scheduled_date"
                                    @input="onNativeDateChange"
                                    type="date"
                                    class="absolute right-0 top-0 h-full w-9 opacity-0 cursor-pointer"
                                    tabindex="-1"
                                />
                            </div>
                        </div>
                        <div class="w-32">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('postAutomation.proposals.form.scheduledTime') }}
                            </label>
                            <input
                                v-model="form.scheduled_time"
                                type="time"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <!-- Next free date hint (create mode only) -->
                    <div v-if="!isEdit()" class="mt-1.5">
                        <span v-if="loadingNextFree" class="text-xs text-gray-400">
                            {{ t('postAutomation.proposals.form.loadingNextFree') }}
                        </span>
                        <span v-else-if="nextFreeDate" class="text-xs text-emerald-600 flex items-center gap-1.5">
                            {{ t('postAutomation.proposals.form.nextFreeDate', { date: formatHintDate(nextFreeDate) }) }}
                            <button
                                type="button"
                                @click="useNextFreeDate"
                                class="px-1.5 py-0.5 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded hover:bg-emerald-100"
                            >
                                {{ t('postAutomation.proposals.form.useThisDate') }}
                            </button>
                        </span>
                        <span v-else-if="nextFreeFetched && !nextFreeDate" class="text-xs text-gray-400">
                            {{ t('postAutomation.proposals.form.noFreeDate') }}
                        </span>
                    </div>
                </div>

                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('postAutomation.proposals.form.title') }}
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        required
                        maxlength="255"
                        :placeholder="t('postAutomation.proposals.form.titlePlaceholder')"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <!-- Keywords -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('postAutomation.proposals.form.keywords') }}
                    </label>
                    <div class="flex flex-wrap gap-1.5 mb-2" v-if="form.keywords.length">
                        <ProposalKeywordBadge
                            v-for="kw in form.keywords"
                            :key="kw"
                            :keyword="kw"
                            removable
                            @remove="removeKeyword"
                        />
                    </div>
                    <div class="flex gap-2">
                        <input
                            v-model="keywordInput"
                            type="text"
                            maxlength="100"
                            :placeholder="t('postAutomation.proposals.form.keywordPlaceholder')"
                            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            @keydown="handleKeywordKeydown"
                        />
                        <button
                            type="button"
                            @click="addKeyword"
                            :disabled="!keywordInput.trim()"
                            class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            +
                        </button>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('postAutomation.proposals.form.notes') }}
                    </label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        maxlength="5000"
                        :placeholder="t('postAutomation.proposals.form.notesPlaceholder')"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                    />
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                    >
                        {{ t('postAutomation.proposals.form.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="saving || !form.title.trim() || !form.scheduled_date"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ t('postAutomation.proposals.form.save') }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
