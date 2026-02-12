<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const managerStore = useManagerStore();
const toast = useToast();

const activeTab = ref('keywords');
const tabs = ['keywords', 'mentions', 'alertRules'];

// Keywords
const newKeyword = ref({ keyword: '', platform: '', category: '' });
const showAddKeyword = ref(false);
const keywordSubmitting = ref(false);
const deletingKeywordId = ref(null);

// Mentions filters
const mentionPlatformFilter = ref('');
const mentionSentimentFilter = ref('');

// Alert Rules
const newAlertRule = ref({ alert_type: 'mention_spike', threshold: 10, timeframe: '1h', notify_via: ['email'], is_active: true });
const showAddRule = ref(false);
const ruleSubmitting = ref(false);
const deletingRuleId = ref(null);

const platforms = ['', 'instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube'];
const sentiments = ['', 'positive', 'neutral', 'negative'];
const alertTypes = ['mention_spike', 'negative_sentiment', 'competitor_mention'];
const notifyChannels = ['email', 'push', 'slack'];

const keywordsLoading = computed(() => managerStore.monitoredKeywordsLoading);
const mentionsLoading = computed(() => managerStore.mentionsLoading);
const alertRulesLoading = computed(() => managerStore.alertRulesLoading);

const keywords = computed(() => managerStore.monitoredKeywords);
const mentions = computed(() => managerStore.mentions);
const alertRules = computed(() => managerStore.alertRules);

const filteredMentions = computed(() => {
    let result = mentions.value;
    if (mentionPlatformFilter.value) {
        result = result.filter(m => m.platform === mentionPlatformFilter.value);
    }
    if (mentionSentimentFilter.value) {
        result = result.filter(m => m.sentiment === mentionSentimentFilter.value);
    }
    return result;
});

const sentimentColorClass = (sentiment) => {
    const map = {
        positive: 'bg-green-500/10 text-green-400 border-green-500/20',
        neutral: 'bg-gray-500/10 text-gray-400 border-gray-500/20',
        negative: 'bg-red-500/10 text-red-400 border-red-500/20',
    };
    return map[sentiment] || map.neutral;
};

const platformColorClass = (platform) => {
    const map = {
        instagram: 'bg-pink-500/10 text-pink-400',
        facebook: 'bg-blue-500/10 text-blue-400',
        tiktok: 'bg-purple-500/10 text-purple-400',
        linkedin: 'bg-sky-500/10 text-sky-400',
        x: 'bg-gray-500/10 text-gray-300',
        youtube: 'bg-red-500/10 text-red-400',
    };
    return map[platform] || 'bg-gray-500/10 text-gray-400';
};

const resetKeywordForm = () => {
    newKeyword.value = { keyword: '', platform: '', category: '' };
    showAddKeyword.value = false;
};

const resetRuleForm = () => {
    newAlertRule.value = { alert_type: 'mention_spike', threshold: 10, timeframe: '1h', notify_via: ['email'], is_active: true };
    showAddRule.value = false;
};

const addKeyword = async () => {
    if (!newKeyword.value.keyword.trim()) return;
    keywordSubmitting.value = true;
    try {
        await managerStore.createMonitoredKeyword(newKeyword.value);
        toast.success(t('manager.listening.keywordAdded'));
        resetKeywordForm();
    } catch {
        toast.error(t('manager.listening.keywordAddError'));
    } finally {
        keywordSubmitting.value = false;
    }
};

const toggleKeywordActive = async (keyword) => {
    try {
        await managerStore.updateMonitoredKeyword(keyword.id, { is_active: !keyword.is_active });
    } catch {
        toast.error(t('manager.listening.updateError'));
    }
};

const deleteKeyword = async (keyword) => {
    if (!confirm(t('manager.listening.deleteConfirm'))) return;
    deletingKeywordId.value = keyword.id;
    try {
        await managerStore.deleteMonitoredKeyword(keyword.id);
        toast.success(t('manager.listening.keywordDeleted'));
    } catch {
        toast.error(t('manager.listening.deleteError'));
    } finally {
        deletingKeywordId.value = null;
    }
};

const addAlertRule = async () => {
    ruleSubmitting.value = true;
    try {
        await managerStore.createAlertRule(newAlertRule.value);
        toast.success(t('manager.listening.ruleAdded'));
        resetRuleForm();
    } catch {
        toast.error(t('manager.listening.ruleAddError'));
    } finally {
        ruleSubmitting.value = false;
    }
};

const toggleRuleActive = async (rule) => {
    try {
        await managerStore.updateAlertRule(rule.id, { is_active: !rule.is_active });
    } catch {
        toast.error(t('manager.listening.updateError'));
    }
};

const toggleNotifyChannel = (channel) => {
    const idx = newAlertRule.value.notify_via.indexOf(channel);
    if (idx >= 0) {
        newAlertRule.value.notify_via.splice(idx, 1);
    } else {
        newAlertRule.value.notify_via.push(channel);
    }
};

const deleteAlertRule = async (rule) => {
    if (!confirm(t('manager.listening.deleteConfirm'))) return;
    deletingRuleId.value = rule.id;
    try {
        await managerStore.deleteAlertRule(rule.id);
        toast.success(t('manager.listening.ruleDeleted'));
    } catch {
        toast.error(t('manager.listening.deleteError'));
    } finally {
        deletingRuleId.value = null;
    }
};

const formatTime = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const loadTabData = () => {
    if (activeTab.value === 'keywords') {
        managerStore.fetchMonitoredKeywords();
    } else if (activeTab.value === 'mentions') {
        managerStore.fetchMentions();
    } else if (activeTab.value === 'alertRules') {
        managerStore.fetchAlertRules();
    }
};

watch(activeTab, loadTabData);

onMounted(() => {
    loadTabData();
});
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white">{{ t('manager.listening.title') }}</h1>
            <p class="mt-1 text-sm text-gray-400">{{ t('manager.listening.subtitle') }}</p>
        </div>

        <!-- Tabs -->
        <div class="mb-6 border-b border-gray-800">
            <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab"
                    @click="activeTab = tab"
                    class="whitespace-nowrap border-b-2 pb-3 px-1 text-sm font-medium transition-colors"
                    :class="activeTab === tab
                        ? 'border-cyan-500 text-cyan-400'
                        : 'border-transparent text-gray-500 hover:text-gray-300 hover:border-gray-600'"
                >
                    {{ t(`manager.listening.tabs.${tab}`) }}
                </button>
            </nav>
        </div>

        <!-- Keywords Tab -->
        <div v-if="activeTab === 'keywords'">
            <!-- Add keyword button -->
            <div class="mb-4 flex justify-end">
                <button
                    @click="showAddKeyword = !showAddKeyword"
                    class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-500 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ t('manager.listening.addKeyword') }}
                </button>
            </div>

            <!-- Add keyword form -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div v-if="showAddKeyword" class="mb-6 rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-white mb-4">{{ t('manager.listening.addKeyword') }}</h3>
                    <form @submit.prevent="addKeyword" class="flex flex-col sm:flex-row gap-3">
                        <input
                            v-model="newKeyword.keyword"
                            type="text"
                            :placeholder="t('manager.listening.keywordPlaceholder')"
                            class="flex-1 rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 focus:outline-none"
                            required
                        />
                        <select
                            v-model="newKeyword.platform"
                            class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 focus:outline-none"
                        >
                            <option value="">{{ t('manager.listening.platformAll') }}</option>
                            <option v-for="p in platforms.filter(p => p)" :key="p" :value="p">{{ p }}</option>
                        </select>
                        <input
                            v-model="newKeyword.category"
                            type="text"
                            :placeholder="t('manager.listening.category')"
                            class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 focus:outline-none sm:w-40"
                        />
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                :disabled="keywordSubmitting || !newKeyword.keyword.trim()"
                                class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ t('common.add') }}
                            </button>
                            <button
                                type="button"
                                @click="resetKeywordForm"
                                class="inline-flex items-center justify-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 transition-colors"
                            >
                                {{ t('common.cancel') }}
                            </button>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- Loading -->
            <div v-if="keywordsLoading" class="flex justify-center py-12">
                <LoadingSpinner size="lg" />
            </div>

            <!-- Keywords list -->
            <div v-else-if="keywords.length" class="space-y-3">
                <div
                    v-for="kw in keywords"
                    :key="kw.id"
                    class="rounded-xl bg-gray-900 border border-gray-800 p-4 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4"
                >
                    <!-- Keyword text -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ kw.keyword }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <span
                                v-if="kw.platform"
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="platformColorClass(kw.platform)"
                            >
                                {{ kw.platform }}
                            </span>
                            <span
                                v-if="kw.category"
                                class="inline-flex items-center rounded-full bg-gray-700/50 text-gray-300 px-2 py-0.5 text-xs font-medium"
                            >
                                {{ kw.category }}
                            </span>
                        </div>
                    </div>

                    <!-- Mention count -->
                    <div class="text-right sm:text-center sm:w-24 shrink-0">
                        <p class="text-lg font-bold text-white">{{ kw.mention_count ?? 0 }}</p>
                        <p class="text-xs text-gray-500">{{ t('manager.listening.mentionCount') }}</p>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 shrink-0">
                        <!-- Active toggle -->
                        <button
                            @click="toggleKeywordActive(kw)"
                            class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium transition-colors"
                            :class="kw.is_active
                                ? 'bg-green-500/10 text-green-400 hover:bg-green-500/20'
                                : 'bg-gray-700/50 text-gray-500 hover:bg-gray-700'"
                        >
                            {{ kw.is_active ? t('manager.listening.active') : t('manager.listening.inactive') }}
                        </button>

                        <!-- Delete -->
                        <button
                            @click="deleteKeyword(kw)"
                            :disabled="deletingKeywordId === kw.id"
                            class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 hover:text-red-400 hover:bg-red-500/10 transition-colors disabled:opacity-50"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="rounded-xl bg-gray-900 border border-gray-800 p-12 flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 rounded-full bg-cyan-500/10 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5-3.9 19.5m-2.1-19.5-3.9 19.5" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.listening.noKeywords') }}</h3>
                <p class="text-sm text-gray-400 max-w-md">{{ t('manager.listening.noKeywordsDescription') }}</p>
            </div>
        </div>

        <!-- Mentions Tab -->
        <div v-if="activeTab === 'mentions'">
            <!-- Filters -->
            <div class="mb-6 flex flex-col sm:flex-row gap-3">
                <select
                    v-model="mentionPlatformFilter"
                    class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 focus:outline-none"
                >
                    <option value="">{{ t('manager.listening.platformAll') }}</option>
                    <option v-for="p in platforms.filter(p => p)" :key="p" :value="p">{{ p }}</option>
                </select>
                <select
                    v-model="mentionSentimentFilter"
                    class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 focus:outline-none"
                >
                    <option value="">{{ t('manager.listening.sentimentAll') }}</option>
                    <option v-for="s in sentiments.filter(s => s)" :key="s" :value="s">{{ t(`manager.listening.sentiment.${s}`) }}</option>
                </select>
            </div>

            <!-- Loading -->
            <div v-if="mentionsLoading" class="flex justify-center py-12">
                <LoadingSpinner size="lg" />
            </div>

            <!-- Mentions list -->
            <div v-else-if="filteredMentions.length" class="space-y-3">
                <div
                    v-for="mention in filteredMentions"
                    :key="mention.id"
                    class="rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-5"
                >
                    <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                        <!-- Author & content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="text-sm font-semibold text-white">{{ mention.author_handle }}</span>
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="platformColorClass(mention.platform)"
                                >
                                    {{ mention.platform }}
                                </span>
                                <span
                                    class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium"
                                    :class="sentimentColorClass(mention.sentiment)"
                                >
                                    {{ t(`manager.listening.sentiment.${mention.sentiment}`) }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-300 line-clamp-2">{{ mention.text_excerpt }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-gray-500">
                                <span v-if="mention.mentioned_at">{{ formatTime(mention.mentioned_at) }}</span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    {{ t('manager.listening.reach') }}: {{ mention.reach ?? 0 }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                                    </svg>
                                    {{ t('manager.listening.engagement') }}: {{ mention.engagement ?? 0 }}
                                </span>
                            </div>
                        </div>

                        <!-- Source link -->
                        <div v-if="mention.source_url" class="shrink-0">
                            <a
                                :href="mention.source_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition-colors"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                                {{ t('manager.listening.sourceLink') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="rounded-xl bg-gray-900 border border-gray-800 p-12 flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 rounded-full bg-cyan-500/10 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 0 1-.923 1.785A5.969 5.969 0 0 0 6 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337Z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.listening.noMentions') }}</h3>
                <p class="text-sm text-gray-400 max-w-md">{{ t('manager.listening.noMentionsDescription') }}</p>
            </div>
        </div>

        <!-- Alert Rules Tab -->
        <div v-if="activeTab === 'alertRules'">
            <!-- Add rule button -->
            <div class="mb-4 flex justify-end">
                <button
                    @click="showAddRule = !showAddRule"
                    class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-500 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ t('manager.listening.addRule') }}
                </button>
            </div>

            <!-- Add rule form -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div v-if="showAddRule" class="mb-6 rounded-xl bg-gray-900 border border-gray-800 p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-white mb-4">{{ t('manager.listening.addRule') }}</h3>
                    <form @submit.prevent="addAlertRule" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <!-- Alert type -->
                            <div>
                                <label class="block text-xs font-medium text-gray-400 mb-1">{{ t('manager.listening.alertType') }}</label>
                                <select
                                    v-model="newAlertRule.alert_type"
                                    class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 focus:outline-none"
                                >
                                    <option v-for="at in alertTypes" :key="at" :value="at">{{ t(`manager.listening.alertTypes.${at}`) }}</option>
                                </select>
                            </div>

                            <!-- Threshold -->
                            <div>
                                <label class="block text-xs font-medium text-gray-400 mb-1">{{ t('manager.listening.threshold') }}</label>
                                <input
                                    v-model.number="newAlertRule.threshold"
                                    type="number"
                                    min="1"
                                    class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 focus:outline-none"
                                />
                            </div>

                            <!-- Timeframe -->
                            <div>
                                <label class="block text-xs font-medium text-gray-400 mb-1">{{ t('manager.listening.timeframe') }}</label>
                                <input
                                    v-model="newAlertRule.timeframe"
                                    type="text"
                                    placeholder="1h, 24h, 7d"
                                    class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 focus:outline-none"
                                />
                            </div>
                        </div>

                        <!-- Notify via -->
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-2">{{ t('manager.listening.notifyVia') }}</label>
                            <div class="flex flex-wrap gap-3">
                                <label
                                    v-for="ch in notifyChannels"
                                    :key="ch"
                                    class="inline-flex items-center gap-2 cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="newAlertRule.notify_via.includes(ch)"
                                        @change="toggleNotifyChannel(ch)"
                                        class="rounded border-gray-600 bg-gray-800 text-cyan-600 focus:ring-cyan-500 focus:ring-offset-0"
                                    />
                                    <span class="text-sm text-gray-300">{{ ch }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="submit"
                                :disabled="ruleSubmitting"
                                class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ t('common.add') }}
                            </button>
                            <button
                                type="button"
                                @click="resetRuleForm"
                                class="inline-flex items-center justify-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 transition-colors"
                            >
                                {{ t('common.cancel') }}
                            </button>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- Loading -->
            <div v-if="alertRulesLoading" class="flex justify-center py-12">
                <LoadingSpinner size="lg" />
            </div>

            <!-- Alert rules list -->
            <div v-else-if="alertRules.length" class="space-y-3">
                <div
                    v-for="rule in alertRules"
                    :key="rule.id"
                    class="rounded-xl bg-gray-900 border border-gray-800 p-4 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4"
                >
                    <!-- Rule info -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white">{{ t(`manager.listening.alertTypes.${rule.alert_type}`) }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                            <span>{{ t('manager.listening.threshold') }}: <span class="text-gray-300">{{ rule.threshold }}</span></span>
                            <span>{{ t('manager.listening.timeframe') }}: <span class="text-gray-300">{{ rule.timeframe }}</span></span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <span
                                v-for="ch in (rule.notify_via || [])"
                                :key="ch"
                                class="inline-flex items-center rounded-full bg-gray-700/50 text-gray-300 px-2 py-0.5 text-xs font-medium"
                            >
                                {{ ch }}
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            @click="toggleRuleActive(rule)"
                            class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium transition-colors"
                            :class="rule.is_active
                                ? 'bg-green-500/10 text-green-400 hover:bg-green-500/20'
                                : 'bg-gray-700/50 text-gray-500 hover:bg-gray-700'"
                        >
                            {{ rule.is_active ? t('manager.listening.active') : t('manager.listening.inactive') }}
                        </button>

                        <button
                            @click="deleteAlertRule(rule)"
                            :disabled="deletingRuleId === rule.id"
                            class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 hover:text-red-400 hover:bg-red-500/10 transition-colors disabled:opacity-50"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="rounded-xl bg-gray-900 border border-gray-800 p-12 flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 rounded-full bg-cyan-500/10 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ t('manager.listening.noAlertRules') }}</h3>
                <p class="text-sm text-gray-400 max-w-md">{{ t('manager.listening.noAlertRulesDescription') }}</p>
            </div>
        </div>
    </div>
</template>
