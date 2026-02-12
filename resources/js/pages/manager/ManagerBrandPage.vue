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
const logoFileInput = ref(null);

// Local editable state
const colors = ref({
    primary: '#6366F1',
    secondary: '#EC4899',
    accent: '#F59E0B',
    background: '#FFFFFF',
    text: '#111827',
});

const fonts = ref({
    heading: { family: '', weight: '700' },
    body: { family: '', weight: '400' },
});

const toneOfVoice = ref('');
const voiceAttributes = ref([]);
const stylePreset = ref('');
const brandGuidelinesNotes = ref('');
const newAttribute = ref('');

const contentPillars = ref([]);
const hashtagGroups = ref({ branded: [], industry: [] });
const newHashtags = ref({ branded: '', industry: '' });

const toneOptions = ['professional', 'casual', 'friendly', 'authoritative', 'humorous', 'inspirational'];
const styleOptions = ['modern', 'classic', 'bold', 'minimal', 'playful'];

// Sync from store
watch(() => managerStore.brandKit, (kit) => {
    if (!kit) return;
    if (kit.colors && typeof kit.colors === 'object') colors.value = { ...colors.value, ...kit.colors };
    if (kit.fonts && typeof kit.fonts === 'object') fonts.value = { ...fonts.value, ...kit.fonts };
    toneOfVoice.value = kit.tone_of_voice || '';
    voiceAttributes.value = Array.isArray(kit.voice_attributes) ? [...kit.voice_attributes] : [];
    stylePreset.value = kit.style_preset || '';
    brandGuidelinesNotes.value = kit.brand_guidelines_notes || '';
    contentPillars.value = Array.isArray(kit.content_pillars) ? [...kit.content_pillars] : [];
    hashtagGroups.value = kit.hashtag_groups && typeof kit.hashtag_groups === 'object'
        ? { branded: [...(kit.hashtag_groups.branded || [])], industry: [...(kit.hashtag_groups.industry || [])] }
        : { branded: [], industry: [] };
}, { immediate: true, deep: true });

const handleSave = async () => {
    saving.value = true;
    try {
        await managerStore.updateBrandKit({
            colors: colors.value,
            fonts: fonts.value,
            tone_of_voice: toneOfVoice.value || null,
            voice_attributes: voiceAttributes.value,
            style_preset: stylePreset.value || null,
            brand_guidelines_notes: brandGuidelinesNotes.value || null,
            content_pillars: contentPillars.value,
            hashtag_groups: hashtagGroups.value,
        });
        toast.success(t('manager.brand.saved'));
    } catch (error) {
        toast.error(t('manager.brand.saveError'));
    } finally {
        saving.value = false;
    }
};

const handleGenerate = async () => {
    generating.value = true;
    try {
        await managerStore.generateBrandKit();
        toast.success(t('manager.brand.generated'));
    } catch (error) {
        toast.error(t('manager.brand.generateError'));
    } finally {
        generating.value = false;
    }
};

const handleLogoUpload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    try {
        await managerStore.uploadLogo(file, 'light');
        toast.success(t('manager.brand.logoUploaded'));
    } catch (error) {
        toast.error(t('manager.brand.logoUploadError'));
    }
    event.target.value = '';
};

const handleDeleteLogo = async () => {
    try {
        await managerStore.deleteLogo('light');
        toast.success(t('manager.brand.logoDeleted'));
    } catch (error) {
        toast.error(t('manager.brand.logoDeleteError'));
    }
};

const addVoiceAttribute = () => {
    const val = newAttribute.value.trim();
    if (val && !voiceAttributes.value.includes(val)) {
        voiceAttributes.value.push(val);
        newAttribute.value = '';
    }
};

const removeVoiceAttribute = (idx) => {
    voiceAttributes.value.splice(idx, 1);
};

const addPillar = () => {
    contentPillars.value.push({ name: '', percentage: 0, description: '' });
};

const removePillar = (idx) => {
    contentPillars.value.splice(idx, 1);
};

const addHashtag = (group) => {
    let val = newHashtags.value[group].trim();
    if (!val) return;
    if (!val.startsWith('#')) val = '#' + val;
    if (!hashtagGroups.value[group].includes(val)) {
        hashtagGroups.value[group].push(val);
    }
    newHashtags.value[group] = '';
};

const removeHashtag = (group, idx) => {
    hashtagGroups.value[group].splice(idx, 1);
};

onMounted(() => {
    if (managerStore.currentBrandId) {
        managerStore.fetchBrandKit();
    }
});

watch(() => managerStore.currentBrandId, (brandId) => {
    if (brandId) managerStore.fetchBrandKit();
});
</script>

<template>
    <div class="min-h-full bg-gray-950 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white">{{ t('manager.brand.title') }}</h1>
                <p class="mt-1 text-sm text-gray-400">{{ t('manager.brand.subtitle') }}</p>
            </div>
            <div class="flex items-center gap-3">
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
                    {{ generating ? t('manager.brand.generating') : t('manager.brand.generateWithAi') }}
                </button>
                <button
                    @click="handleSave"
                    :disabled="saving || generating"
                    class="px-5 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition-colors disabled:opacity-50"
                >
                    {{ saving ? t('common.loading') : t('common.save') }}
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="managerStore.brandKitLoading" class="flex items-center justify-center py-12">
            <LoadingSpinner />
        </div>

        <div v-else class="space-y-6">
            <!-- Row 1: Colors + Logo -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Colors -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                    <h3 class="text-base font-semibold text-white mb-4">{{ t('manager.brand.colors') }}</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div v-for="(value, key) in colors" :key="key">
                            <label class="block text-xs font-medium text-gray-400 mb-1.5 capitalize">{{ key }}</label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="color"
                                    v-model="colors[key]"
                                    class="w-8 h-8 rounded cursor-pointer border border-gray-700 bg-transparent"
                                />
                                <input
                                    type="text"
                                    v-model="colors[key]"
                                    class="flex-1 px-2.5 py-1.5 bg-gray-800 border border-gray-700 rounded-lg text-xs text-white font-mono focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                    maxlength="7"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logo -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                    <h3 class="text-base font-semibold text-white mb-4">{{ t('manager.brand.logo') }}</h3>
                    <div class="flex flex-col items-center justify-center">
                        <div
                            v-if="managerStore.brandKit?.logo_url"
                            class="relative mb-4"
                        >
                            <img
                                :src="managerStore.brandKit.logo_url"
                                alt="Brand logo"
                                class="w-24 h-24 rounded-xl object-contain bg-gray-800"
                            />
                            <button
                                @click="handleDeleteLogo"
                                class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-red-500 text-white flex items-center justify-center hover:bg-red-400 transition"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div
                            v-else
                            class="w-24 h-24 rounded-xl border-2 border-dashed border-gray-700 flex items-center justify-center mb-4 cursor-pointer hover:border-gray-600 transition"
                            @click="logoFileInput?.click()"
                        >
                            <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </div>
                        <input ref="logoFileInput" type="file" accept="image/*" class="hidden" @change="handleLogoUpload" />
                        <button
                            @click="logoFileInput?.click()"
                            class="text-sm text-indigo-400 hover:text-indigo-300 transition"
                        >
                            {{ managerStore.brandKit?.logo_url ? t('manager.brand.changeLogo') : t('manager.brand.uploadLogo') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Row 2: Fonts + Style -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Fonts -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                    <h3 class="text-base font-semibold text-white mb-4">{{ t('manager.brand.fonts') }}</h3>
                    <div class="space-y-4">
                        <div v-for="(font, key) in fonts" :key="key">
                            <label class="block text-xs font-medium text-gray-400 mb-1.5 capitalize">{{ key }}</label>
                            <input
                                type="text"
                                v-model="fonts[key].family"
                                :placeholder="t('manager.brand.fontPlaceholder')"
                                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Style Preset -->
                <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                    <h3 class="text-base font-semibold text-white mb-4">{{ t('manager.brand.stylePreset') }}</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <button
                            v-for="style in styleOptions"
                            :key="style"
                            @click="stylePreset = stylePreset === style ? '' : style"
                            class="px-3 py-2 text-sm font-medium rounded-lg border transition-colors capitalize"
                            :class="stylePreset === style
                                ? 'border-indigo-500 bg-indigo-500/20 text-indigo-300'
                                : 'border-gray-700 bg-gray-800 text-gray-400 hover:border-gray-600 hover:text-gray-300'"
                        >
                            {{ t(`manager.brand.styles.${style}`) }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Row 3: Tone of Voice -->
            <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                <h3 class="text-base font-semibold text-white mb-4">{{ t('manager.brand.toneOfVoice') }}</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mb-4">
                    <button
                        v-for="tone in toneOptions"
                        :key="tone"
                        @click="toneOfVoice = toneOfVoice === tone ? '' : tone"
                        class="px-3 py-2 text-sm font-medium rounded-lg border transition-colors capitalize"
                        :class="toneOfVoice === tone
                            ? 'border-indigo-500 bg-indigo-500/20 text-indigo-300'
                            : 'border-gray-700 bg-gray-800 text-gray-400 hover:border-gray-600 hover:text-gray-300'"
                    >
                        {{ t(`manager.brand.tones.${tone}`) }}
                    </button>
                </div>

                <!-- Voice attributes -->
                <div class="mt-4">
                    <label class="block text-xs font-medium text-gray-400 mb-2">{{ t('manager.brand.voiceAttributes') }}</label>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <span
                            v-for="(attr, idx) in voiceAttributes"
                            :key="idx"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-indigo-500/20 text-indigo-300 text-xs font-medium"
                        >
                            {{ attr }}
                            <button @click="removeVoiceAttribute(idx)" class="hover:text-white transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <input
                            v-model="newAttribute"
                            type="text"
                            :placeholder="t('manager.brand.addAttribute')"
                            class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                            @keydown.enter="addVoiceAttribute"
                        />
                        <button
                            @click="addVoiceAttribute"
                            class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-400 hover:text-white hover:border-gray-600 transition"
                        >
                            {{ t('common.add') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Row 4: Content Pillars -->
            <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-white">{{ t('manager.brand.contentPillars') }}</h3>
                    <button
                        @click="addPillar"
                        class="text-sm text-indigo-400 hover:text-indigo-300 transition"
                    >
                        + {{ t('common.add') }}
                    </button>
                </div>
                <div v-if="contentPillars.length === 0" class="text-sm text-gray-500 text-center py-4">
                    {{ t('manager.brand.noPillars') }}
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="(pillar, idx) in contentPillars"
                        :key="idx"
                        class="flex items-start gap-3 p-3 rounded-lg bg-gray-800/50"
                    >
                        <div class="flex-1 space-y-2">
                            <input
                                v-model="pillar.name"
                                type="text"
                                :placeholder="t('manager.brand.pillarName')"
                                class="w-full px-3 py-1.5 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                            />
                            <input
                                v-model="pillar.description"
                                type="text"
                                :placeholder="t('manager.brand.pillarDescription')"
                                class="w-full px-3 py-1.5 bg-gray-800 border border-gray-700 rounded-lg text-xs text-gray-300 placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                            />
                        </div>
                        <div class="flex items-center gap-2 shrink-0 pt-1">
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

            <!-- Row 5: Hashtags -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div v-for="group in ['branded', 'industry']" :key="group" class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                    <h3 class="text-base font-semibold text-white mb-4">{{ t(`manager.brand.hashtags.${group}`) }}</h3>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <span
                            v-for="(tag, idx) in hashtagGroups[group]"
                            :key="idx"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium"
                            :class="group === 'branded' ? 'bg-purple-500/20 text-purple-300' : 'bg-cyan-500/20 text-cyan-300'"
                        >
                            {{ tag }}
                            <button @click="removeHashtag(group, idx)" class="hover:text-white transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <input
                            v-model="newHashtags[group]"
                            type="text"
                            :placeholder="t('manager.brand.addHashtag')"
                            class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                            @keydown.enter="addHashtag(group)"
                        />
                        <button
                            @click="addHashtag(group)"
                            class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-400 hover:text-white hover:border-gray-600 transition"
                        >
                            {{ t('common.add') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Row 6: Brand Guidelines Notes -->
            <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                <h3 class="text-base font-semibold text-white mb-4">{{ t('manager.brand.guidelinesNotes') }}</h3>
                <textarea
                    v-model="brandGuidelinesNotes"
                    rows="4"
                    :placeholder="t('manager.brand.guidelinesPlaceholder')"
                    class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none resize-none"
                />
            </div>

            <!-- Bottom save button (mobile) -->
            <div class="lg:hidden pb-4">
                <button
                    @click="handleSave"
                    :disabled="saving"
                    class="w-full px-5 py-3 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition-colors disabled:opacity-50"
                >
                    {{ saving ? t('common.loading') : t('common.save') }}
                </button>
            </div>
        </div>
    </div>
</template>
