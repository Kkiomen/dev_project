<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useManagerStore } from '@/stores/manager';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const managerStore = useManagerStore();
const toast = useToast();

const saving = ref(false);
const generating = ref(false);
const activeTab = ref('pillars');

// Local editable state
const contentPillars = ref([]);
const postingFrequency = ref({
    instagram: 5,
    facebook: 3,
    tiktok: 4,
    linkedin: 2,
    x: 5,
    youtube: 1,
});
const targetAudience = ref({
    age_range: '25-45',
    gender: 'all',
    interests: [],
    pain_points: [],
});
const goals = ref([]);
const competitorHandles = ref({});
const contentMix = ref({
    educational: 40,
    entertaining: 25,
    promotional: 20,
    engaging: 15,
});
const optimalTimes = ref({});

const newInterest = ref('');
const newPainPoint = ref('');
const newCompetitor = ref({ platform: 'instagram', handle: '' });

const platforms = [
    { id: 'instagram', name: 'Instagram', icon: 'IG' },
    { id: 'facebook', name: 'Facebook', icon: 'FB' },
    { id: 'tiktok', name: 'TikTok', icon: 'TT' },
    { id: 'linkedin', name: 'LinkedIn', icon: 'LI' },
    { id: 'x', name: 'X', icon: 'X' },
    { id: 'youtube', name: 'YouTube', icon: 'YT' },
];

const tabs = [
    { id: 'pillars', label: 'manager.strategy.tabs.pillars' },
    { id: 'frequency', label: 'manager.strategy.tabs.frequency' },
    { id: 'audience', label: 'manager.strategy.tabs.audience' },
    { id: 'goals', label: 'manager.strategy.tabs.goals' },
    { id: 'competitors', label: 'manager.strategy.tabs.competitors' },
    { id: 'contentMix', label: 'manager.strategy.tabs.contentMix' },
];

const contentMixCategories = ['educational', 'entertaining', 'promotional', 'engaging'];

const totalMixPercentage = computed(() => {
    return Object.values(contentMix.value).reduce((sum, val) => sum + (val || 0), 0);
});

const totalPillarPercentage = computed(() => {
    return contentPillars.value.reduce((sum, p) => sum + (p.percentage || 0), 0);
});

const totalWeeklyPosts = computed(() => {
    return Object.values(postingFrequency.value).reduce((sum, val) => sum + (val || 0), 0);
});

// Sync from store
watch(() => managerStore.strategy, (strategy) => {
    if (!strategy) return;
    if (Array.isArray(strategy.content_pillars)) contentPillars.value = [...strategy.content_pillars];
    if (strategy.posting_frequency && typeof strategy.posting_frequency === 'object') postingFrequency.value = { ...postingFrequency.value, ...strategy.posting_frequency };
    if (strategy.target_audience && typeof strategy.target_audience === 'object') targetAudience.value = { ...targetAudience.value, ...strategy.target_audience };
    if (Array.isArray(strategy.goals)) goals.value = [...strategy.goals];
    if (strategy.competitor_handles && typeof strategy.competitor_handles === 'object') competitorHandles.value = { ...strategy.competitor_handles };
    if (strategy.content_mix && typeof strategy.content_mix === 'object') contentMix.value = { ...contentMix.value, ...strategy.content_mix };
    if (strategy.optimal_times && typeof strategy.optimal_times === 'object') optimalTimes.value = { ...strategy.optimal_times };
}, { immediate: true, deep: true });

const addPillar = () => {
    contentPillars.value.push({ name: '', description: '', percentage: 20 });
};

const removePillar = (idx) => {
    contentPillars.value.splice(idx, 1);
};

const addGoal = () => {
    goals.value.push({ goal: '', metric: '', target_value: '', timeframe: 'monthly' });
};

const removeGoal = (idx) => {
    goals.value.splice(idx, 1);
};

const addInterest = () => {
    if (!newInterest.value.trim()) return;
    if (!targetAudience.value.interests) targetAudience.value.interests = [];
    targetAudience.value.interests.push(newInterest.value.trim());
    newInterest.value = '';
};

const removeInterest = (idx) => {
    targetAudience.value.interests.splice(idx, 1);
};

const addPainPoint = () => {
    if (!newPainPoint.value.trim()) return;
    if (!targetAudience.value.pain_points) targetAudience.value.pain_points = [];
    targetAudience.value.pain_points.push(newPainPoint.value.trim());
    newPainPoint.value = '';
};

const removePainPoint = (idx) => {
    targetAudience.value.pain_points.splice(idx, 1);
};

const addCompetitor = () => {
    if (!newCompetitor.value.handle.trim()) return;
    const platform = newCompetitor.value.platform;
    if (!competitorHandles.value[platform]) competitorHandles.value[platform] = [];
    competitorHandles.value[platform].push(newCompetitor.value.handle.trim());
    newCompetitor.value.handle = '';
};

const removeCompetitor = (platform, idx) => {
    competitorHandles.value[platform].splice(idx, 1);
};

const handleSave = async () => {
    saving.value = true;
    try {
        await managerStore.updateStrategy({
            content_pillars: contentPillars.value,
            posting_frequency: postingFrequency.value,
            target_audience: targetAudience.value,
            goals: goals.value,
            competitor_handles: competitorHandles.value,
            content_mix: contentMix.value,
            optimal_times: optimalTimes.value,
        });
        toast.success(t('manager.strategy.saved'));
    } catch (error) {
        toast.error(t('manager.strategy.saveError'));
    } finally {
        saving.value = false;
    }
};

const handleGenerate = async () => {
    generating.value = true;
    try {
        await managerStore.generateStrategy();
        toast.success(t('manager.strategy.generated'));
    } catch (error) {
        toast.error(t('manager.strategy.generateError'));
    } finally {
        generating.value = false;
    }
};

const handleActivate = async () => {
    try {
        await managerStore.activateStrategy();
        toast.success(t('manager.strategy.activated'));
    } catch (error) {
        toast.error(t('manager.strategy.activateError'));
    }
};

onMounted(() => {
    managerStore.fetchStrategy();
});
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white">{{ t('manager.strategy.title') }}</h1>
                <p class="mt-1 text-sm text-gray-400">{{ t('manager.strategy.subtitle') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span
                    v-if="managerStore.strategy"
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium"
                    :class="managerStore.strategyIsActive ? 'bg-emerald-500/20 text-emerald-400' : 'bg-yellow-500/20 text-yellow-400'"
                >
                    <span class="w-1.5 h-1.5 rounded-full" :class="managerStore.strategyIsActive ? 'bg-emerald-400' : 'bg-yellow-400'" />
                    {{ managerStore.strategyIsActive ? t('manager.strategy.statusActive') : t('manager.strategy.statusDraft') }}
                </span>
                <button
                    v-if="managerStore.strategy && !managerStore.strategyIsActive"
                    @click="handleActivate"
                    class="px-4 py-2 text-sm font-medium rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 transition-colors"
                >
                    {{ t('manager.strategy.activate') }}
                </button>
                <button
                    @click="handleGenerate"
                    :disabled="generating || saving"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-purple-600 text-white hover:bg-purple-500 transition-colors disabled:opacity-50"
                >
                    <svg v-if="generating" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                    </svg>
                    {{ generating ? t('manager.strategy.generating') : t('manager.strategy.generateWithAi') }}
                </button>
                <button
                    @click="handleSave"
                    :disabled="saving || generating"
                    class="px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition-colors disabled:opacity-50"
                >
                    {{ saving ? t('common.saving') : t('common.save') }}
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="managerStore.strategyLoading" class="flex items-center justify-center py-12">
            <LoadingSpinner />
        </div>

        <template v-else>
            <!-- Tabs -->
            <div class="flex gap-1 mb-6 overflow-x-auto pb-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    class="px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap transition-colors"
                    :class="activeTab === tab.id ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
                >
                    {{ t(tab.label) }}
                </button>
            </div>

            <!-- Content Pillars Tab -->
            <div v-if="activeTab === 'pillars'" class="space-y-4">
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-semibold text-white">{{ t('manager.strategy.contentPillars') }}</h3>
                        <div class="flex items-center gap-3">
                            <span class="text-xs" :class="totalPillarPercentage === 100 ? 'text-emerald-400' : 'text-yellow-400'">
                                {{ totalPillarPercentage }}%
                            </span>
                            <button @click="addPillar" class="px-3 py-1.5 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition">
                                {{ t('common.add') }}
                            </button>
                        </div>
                    </div>

                    <div v-if="contentPillars.length === 0" class="py-8 text-center text-gray-500 text-sm">
                        {{ t('manager.strategy.noPillars') }}
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="(pillar, idx) in contentPillars" :key="idx" class="flex items-start gap-3 p-3 rounded-lg bg-gray-800/50">
                            <div class="flex-1 space-y-2">
                                <input
                                    v-model="pillar.name"
                                    type="text"
                                    :placeholder="t('manager.strategy.pillarName')"
                                    class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                />
                                <input
                                    v-model="pillar.description"
                                    type="text"
                                    :placeholder="t('manager.strategy.pillarDescription')"
                                    class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                />
                            </div>
                            <div class="flex items-center gap-2 pt-2">
                                <input
                                    v-model.number="pillar.percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-16 px-2 py-1.5 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white text-center focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                />
                                <span class="text-xs text-gray-500">%</span>
                                <button @click="removePillar(idx)" class="p-1 text-gray-500 hover:text-red-400 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Posting Frequency Tab -->
            <div v-if="activeTab === 'frequency'" class="space-y-4">
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-base font-semibold text-white">{{ t('manager.strategy.postingFrequency') }}</h3>
                        <span class="text-sm text-gray-400">{{ t('manager.strategy.totalWeekly', { count: totalWeeklyPosts }) }}</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="platform in platforms" :key="platform.id" class="flex items-center justify-between p-4 rounded-lg bg-gray-800/50">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-gray-700 flex items-center justify-center text-xs font-bold text-gray-300">
                                    {{ platform.icon }}
                                </div>
                                <span class="text-sm text-white">{{ platform.name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model.number="postingFrequency[platform.id]"
                                    type="number"
                                    min="0"
                                    max="30"
                                    class="w-14 px-2 py-1.5 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white text-center focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                />
                                <span class="text-xs text-gray-500">/ {{ t('manager.strategy.perWeek') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Target Audience Tab -->
            <div v-if="activeTab === 'audience'" class="space-y-4">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <!-- Demographics -->
                    <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                        <h3 class="text-base font-semibold text-white mb-4">{{ t('manager.strategy.demographics') }}</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">{{ t('manager.strategy.ageRange') }}</label>
                                <input
                                    v-model="targetAudience.age_range"
                                    type="text"
                                    placeholder="25-45"
                                    class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">{{ t('manager.strategy.gender') }}</label>
                                <div class="flex gap-2">
                                    <button
                                        v-for="g in ['all', 'male', 'female']"
                                        :key="g"
                                        @click="targetAudience.gender = g"
                                        class="px-3 py-1.5 text-sm rounded-lg border transition-colors"
                                        :class="targetAudience.gender === g ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-gray-700 text-gray-400 hover:border-gray-600'"
                                    >
                                        {{ t(`manager.strategy.genders.${g}`) }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Interests -->
                    <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                        <h3 class="text-base font-semibold text-white mb-4">{{ t('manager.strategy.interests') }}</h3>
                        <div class="flex flex-wrap gap-2 mb-3">
                            <span
                                v-for="(interest, idx) in targetAudience.interests"
                                :key="idx"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-500/20 text-indigo-300"
                            >
                                {{ interest }}
                                <button @click="removeInterest(idx)" class="hover:text-white transition">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <input
                                v-model="newInterest"
                                type="text"
                                :placeholder="t('manager.strategy.addInterest')"
                                class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                @keydown.enter="addInterest"
                            />
                            <button @click="addInterest" class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-400 hover:text-white hover:border-gray-600 transition">
                                {{ t('common.add') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pain Points -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                    <h3 class="text-base font-semibold text-white mb-4">{{ t('manager.strategy.painPoints') }}</h3>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <span
                            v-for="(point, idx) in targetAudience.pain_points"
                            :key="idx"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-300"
                        >
                            {{ point }}
                            <button @click="removePainPoint(idx)" class="hover:text-white transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <input
                            v-model="newPainPoint"
                            type="text"
                            :placeholder="t('manager.strategy.addPainPoint')"
                            class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                            @keydown.enter="addPainPoint"
                        />
                        <button @click="addPainPoint" class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-400 hover:text-white hover:border-gray-600 transition">
                            {{ t('common.add') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Goals Tab -->
            <div v-if="activeTab === 'goals'" class="space-y-4">
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-semibold text-white">{{ t('manager.strategy.goals') }}</h3>
                        <button @click="addGoal" class="px-3 py-1.5 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition">
                            {{ t('common.add') }}
                        </button>
                    </div>

                    <div v-if="goals.length === 0" class="py-8 text-center text-gray-500 text-sm">
                        {{ t('manager.strategy.noGoals') }}
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="(goal, idx) in goals" :key="idx" class="p-4 rounded-lg bg-gray-800/50 space-y-3">
                            <div class="flex gap-3">
                                <input
                                    v-model="goal.goal"
                                    type="text"
                                    :placeholder="t('manager.strategy.goalName')"
                                    class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                />
                                <button @click="removeGoal(idx)" class="p-2 text-gray-500 hover:text-red-400 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <input
                                    v-model="goal.metric"
                                    type="text"
                                    :placeholder="t('manager.strategy.metric')"
                                    class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                />
                                <input
                                    v-model="goal.target_value"
                                    type="text"
                                    :placeholder="t('manager.strategy.targetValue')"
                                    class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                />
                                <select
                                    v-model="goal.timeframe"
                                    class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                >
                                    <option value="weekly">{{ t('manager.strategy.timeframes.weekly') }}</option>
                                    <option value="monthly">{{ t('manager.strategy.timeframes.monthly') }}</option>
                                    <option value="quarterly">{{ t('manager.strategy.timeframes.quarterly') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Competitors Tab -->
            <div v-if="activeTab === 'competitors'" class="space-y-4">
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                    <h3 class="text-base font-semibold text-white mb-4">{{ t('manager.strategy.competitors') }}</h3>

                    <!-- Add competitor -->
                    <div class="flex gap-2 mb-6">
                        <select
                            v-model="newCompetitor.platform"
                            class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                        >
                            <option v-for="p in platforms" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <input
                            v-model="newCompetitor.handle"
                            type="text"
                            placeholder="@handle"
                            class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                            @keydown.enter="addCompetitor"
                        />
                        <button @click="addCompetitor" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-500 transition">
                            {{ t('common.add') }}
                        </button>
                    </div>

                    <!-- Grouped by platform -->
                    <div class="space-y-4">
                        <template v-for="platform in platforms" :key="platform.id">
                            <div v-if="competitorHandles[platform.id]?.length" class="space-y-2">
                                <h4 class="text-xs font-medium text-gray-400 uppercase tracking-wider">{{ platform.name }}</h4>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="(handle, idx) in competitorHandles[platform.id]"
                                        :key="idx"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-800 text-sm text-white"
                                    >
                                        {{ handle }}
                                        <button @click="removeCompetitor(platform.id, idx)" class="text-gray-500 hover:text-red-400 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </template>

                        <div v-if="Object.values(competitorHandles).every(h => !h?.length)" class="py-8 text-center text-gray-500 text-sm">
                            {{ t('manager.strategy.noCompetitors') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Mix Tab -->
            <div v-if="activeTab === 'contentMix'" class="space-y-4">
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-base font-semibold text-white">{{ t('manager.strategy.contentMix') }}</h3>
                        <span class="text-xs" :class="totalMixPercentage === 100 ? 'text-emerald-400' : 'text-yellow-400'">
                            {{ totalMixPercentage }}%
                        </span>
                    </div>

                    <div class="space-y-4">
                        <div v-for="category in contentMixCategories" :key="category" class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-white">{{ t(`manager.strategy.mixCategories.${category}`) }}</span>
                                <span class="text-sm text-gray-400">{{ contentMix[category] || 0 }}%</span>
                            </div>
                            <input
                                v-model.number="contentMix[category]"
                                type="range"
                                min="0"
                                max="100"
                                step="5"
                                class="w-full h-2 bg-gray-800 rounded-lg appearance-none cursor-pointer accent-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Visual bar -->
                    <div class="mt-6 h-4 rounded-full overflow-hidden flex bg-gray-800">
                        <div
                            v-for="(category, idx) in contentMixCategories"
                            :key="category"
                            :style="{ width: `${contentMix[category] || 0}%` }"
                            class="h-full transition-all"
                            :class="[
                                idx === 0 ? 'bg-blue-500' : '',
                                idx === 1 ? 'bg-purple-500' : '',
                                idx === 2 ? 'bg-amber-500' : '',
                                idx === 3 ? 'bg-emerald-500' : '',
                            ]"
                        />
                    </div>
                    <div class="flex gap-4 mt-2">
                        <div v-for="(category, idx) in contentMixCategories" :key="category" class="flex items-center gap-1.5">
                            <div
                                class="w-2.5 h-2.5 rounded-full"
                                :class="[
                                    idx === 0 ? 'bg-blue-500' : '',
                                    idx === 1 ? 'bg-purple-500' : '',
                                    idx === 2 ? 'bg-amber-500' : '',
                                    idx === 3 ? 'bg-emerald-500' : '',
                                ]"
                            />
                            <span class="text-xs text-gray-400">{{ t(`manager.strategy.mixCategories.${category}`) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
