<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import Modal from '@/components/common/Modal.vue';
import { useToast } from '@/composables/useToast';
import { useBrandsStore } from '@/stores/brands';

const { t } = useI18n();
const toast = useToast();
const brandsStore = useBrandsStore();

const props = defineProps({
    show: { type: Boolean, default: false },
    type: { type: String, required: true, validator: v => ['text', 'image'].includes(v) },
});

const emit = defineEmits(['close']);

const saving = ref(false);
const loading = ref(false);
const prompt = ref('');
const variables = ref({});
const textareaRef = ref(null);

const title = computed(() => {
    return props.type === 'text'
        ? t('postAutomation.systemPrompt.textTitle')
        : t('postAutomation.systemPrompt.imageTitle');
});

const description = computed(() => {
    return props.type === 'text'
        ? t('postAutomation.systemPrompt.textDescription')
        : t('postAutomation.systemPrompt.imageDescription');
});

const variablesList = computed(() => {
    const list = [
        { key: 'brand_name', label: t('postAutomation.systemPrompt.vars.brandName'), value: variables.value.brand_name },
        { key: 'brand_description', label: t('postAutomation.systemPrompt.vars.brandDescription'), value: variables.value.brand_description },
        { key: 'industry', label: t('postAutomation.systemPrompt.vars.industry'), value: variables.value.industry },
        { key: 'tone', label: t('postAutomation.systemPrompt.vars.tone'), value: variables.value.tone },
        { key: 'language', label: t('postAutomation.systemPrompt.vars.language'), value: variables.value.language },
        { key: 'personality', label: t('postAutomation.systemPrompt.vars.personality'), value: variables.value.personality },
        { key: 'target_age_range', label: t('postAutomation.systemPrompt.vars.ageRange'), value: variables.value.target_age_range },
        { key: 'target_gender', label: t('postAutomation.systemPrompt.vars.gender'), value: variables.value.target_gender },
        { key: 'interests', label: t('postAutomation.systemPrompt.vars.interests'), value: variables.value.interests },
        { key: 'pain_points', label: t('postAutomation.systemPrompt.vars.painPoints'), value: variables.value.pain_points },
        { key: 'content_pillars', label: t('postAutomation.systemPrompt.vars.contentPillars'), value: variables.value.content_pillars },
    ];

    if (props.type === 'image') {
        list.push({ key: 'image_prompt', label: t('postAutomation.systemPrompt.vars.imagePrompt'), value: variables.value.image_prompt });
    }

    return list;
});

watch(() => props.show, async (value) => {
    if (value) {
        await loadPrompt();
    }
});

async function loadPrompt() {
    const brand = brandsStore.currentBrand;
    if (!brand) return;

    loading.value = true;
    try {
        const response = await axios.get(`/api/v1/brands/${brand.id}/automation/system-prompts`);
        const data = response.data.data;

        prompt.value = props.type === 'text'
            ? data.text_system_prompt || ''
            : data.image_system_prompt || '';

        variables.value = data.variables || {};
    } catch (error) {
        toast.error(t('postAutomation.systemPrompt.loadError'));
    } finally {
        loading.value = false;
    }
}

async function save() {
    const brand = brandsStore.currentBrand;
    if (!brand) return;

    saving.value = true;
    try {
        const payload = props.type === 'text'
            ? { text_system_prompt: prompt.value }
            : { image_system_prompt: prompt.value };

        await axios.put(`/api/v1/brands/${brand.id}/automation/system-prompts`, payload);
        toast.success(t('postAutomation.systemPrompt.saved'));
        emit('close');
    } catch (error) {
        toast.error(error.response?.data?.message || t('postAutomation.systemPrompt.saveError'));
    } finally {
        saving.value = false;
    }
}

function insertVariable(key) {
    const variable = `{{${key}}}`;
    const textarea = textareaRef.value;

    if (textarea) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = prompt.value;

        prompt.value = text.substring(0, start) + variable + text.substring(end);

        nextTick(() => {
            const newPos = start + variable.length;
            textarea.focus();
            textarea.setSelectionRange(newPos, newPos);
        });
    } else {
        prompt.value += variable;
    }
}

function truncateValue(value, maxLen = 30) {
    if (!value) return '—';
    return value.length > maxLen ? value.substring(0, maxLen) + '...' : value;
}

function formatVariableKey(key) {
    return '{{' + key + '}}';
}

const previewPrompt = computed(() => {
    if (!prompt.value) return '';
    return prompt.value.replace(/\{\{(\w+)\}\}/g, (match, key) => variables.value[key] || match);
});
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ title }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ description }}
                </p>
            </div>

            <div v-if="loading" class="flex items-center justify-center py-8">
                <svg class="animate-spin h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <div v-else class="space-y-4">
                <!-- Variables panel -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                        {{ t('postAutomation.systemPrompt.availableVars') }}
                    </label>
                    <div class="bg-gray-50 rounded-lg p-3 max-h-40 overflow-y-auto">
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="variable in variablesList"
                                :key="variable.key"
                                type="button"
                                @click="insertVariable(variable.key)"
                                class="flex items-center justify-between gap-2 px-2 py-1.5 text-xs bg-white border border-gray-200 rounded-md hover:border-blue-400 hover:bg-blue-50 transition-colors text-left"
                            >
                                <span class="font-mono text-blue-600">{{ formatVariableKey(variable.key) }}</span>
                                <span class="text-gray-400 truncate" :title="variable.value">
                                    {{ truncateValue(variable.value, 15) }}
                                </span>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-400">
                        {{ t('postAutomation.systemPrompt.clickToInsert') }}
                    </p>
                </div>

                <!-- Prompt textarea -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ t('postAutomation.systemPrompt.promptLabel') }}
                    </label>
                    <textarea
                        ref="textareaRef"
                        v-model="prompt"
                        rows="10"
                        :placeholder="t('postAutomation.systemPrompt.promptPlaceholder')"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-mono"
                    />
                    <!-- Example hint -->
                    <details v-if="!prompt" class="mt-2">
                        <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">
                            {{ t('postAutomation.systemPrompt.showExample') }}
                        </summary>
                        <pre class="mt-2 p-2 bg-gray-100 rounded text-xs text-gray-600 whitespace-pre-wrap font-mono">Jesteś ekspertem od marketingu dla marki &#123;&#123;brand_name&#125;&#125; w branży &#123;&#123;industry&#125;&#125;.

Grupa docelowa: &#123;&#123;target_age_range&#125;&#125;, &#123;&#123;target_gender&#125;&#125;.
Zainteresowania: &#123;&#123;interests&#125;&#125;.
Problemy odbiorców: &#123;&#123;pain_points&#125;&#125;.

Ton komunikacji: &#123;&#123;tone&#125;&#125;.
Cechy osobowości: &#123;&#123;personality&#125;&#125;.
Język: &#123;&#123;language&#125;&#125;.</pre>
                    </details>
                </div>

                <!-- Preview -->
                <div v-if="prompt" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <label class="block text-xs font-medium text-amber-700 uppercase tracking-wider mb-1">
                        {{ t('postAutomation.systemPrompt.preview') }}
                    </label>
                    <p class="text-sm text-amber-900 whitespace-pre-wrap">
                        {{ previewPrompt }}
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-4 border-t">
                <button
                    type="button"
                    @click="emit('close')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    {{ t('common.cancel') }}
                </button>
                <button
                    @click="save"
                    :disabled="saving || loading"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ saving ? t('common.loading') : t('common.save') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
