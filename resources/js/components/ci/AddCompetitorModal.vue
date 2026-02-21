<script setup>
import { ref, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal from '@/components/common/Modal.vue';

const { t } = useI18n();

const props = defineProps({
    show: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'save']);

const platforms = ['instagram', 'tiktok', 'linkedin', 'youtube', 'twitter'];

const form = reactive({
    name: '',
    notes: '',
    accounts: [{ platform: 'instagram', handle: '' }],
});

const addAccount = () => {
    form.accounts.push({ platform: 'instagram', handle: '' });
};

const removeAccount = (index) => {
    if (form.accounts.length > 1) {
        form.accounts.splice(index, 1);
    }
};

const handleSave = () => {
    if (!form.name.trim()) return;
    if (!form.accounts.some(a => a.handle.trim())) return;

    emit('save', {
        name: form.name.trim(),
        notes: form.notes.trim() || null,
        accounts: form.accounts
            .filter(a => a.handle.trim())
            .map(a => ({ platform: a.platform, handle: a.handle.trim().replace(/^@/, '') })),
    });
};

const resetForm = () => {
    form.name = '';
    form.notes = '';
    form.accounts = [{ platform: 'instagram', handle: '' }];
};

const handleClose = () => {
    resetForm();
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="handleClose" max-width="lg" variant="dark">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ t('ci.competitors.add') }}</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('ci.competitors.name') }}</label>
                    <input
                        v-model="form.name"
                        type="text"
                        :placeholder="t('ci.competitors.namePlaceholder')"
                        class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 focus:outline-none"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('ci.competitors.notes') }}</label>
                    <textarea
                        v-model="form.notes"
                        :placeholder="t('ci.competitors.notesPlaceholder')"
                        rows="2"
                        class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 focus:outline-none resize-none"
                    ></textarea>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-300">{{ t('ci.competitors.accounts') }}</label>
                        <button
                            @click="addAccount"
                            class="text-xs text-orange-400 hover:text-orange-300 transition-colors"
                        >
                            + {{ t('ci.competitors.addAccount') }}
                        </button>
                    </div>

                    <div class="space-y-2">
                        <div v-for="(account, index) in form.accounts" :key="index" class="flex gap-2">
                            <select
                                v-model="account.platform"
                                class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white focus:border-orange-500 focus:ring-1 focus:ring-orange-500 focus:outline-none"
                            >
                                <option v-for="p in platforms" :key="p" :value="p">{{ t(`ci.platforms.${p}`) }}</option>
                            </select>
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 text-sm">@</span>
                                <input
                                    v-model="account.handle"
                                    type="text"
                                    :placeholder="t('ci.competitors.handlePlaceholder')"
                                    class="w-full rounded-lg border border-gray-700 bg-gray-800 pl-7 pr-3 py-2 text-sm text-white placeholder-gray-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 focus:outline-none"
                                />
                            </div>
                            <button
                                v-if="form.accounts.length > 1"
                                @click="removeAccount(index)"
                                class="p-2 text-gray-500 hover:text-red-400 transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    @click="handleClose"
                    class="rounded-lg border border-gray-700 px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 transition-colors"
                >
                    {{ t('common.cancel') }}
                </button>
                <button
                    @click="handleSave"
                    :disabled="saving || !form.name.trim()"
                    class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-500 transition-colors disabled:opacity-50"
                >
                    {{ saving ? t('common.loading') : t('common.save') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
