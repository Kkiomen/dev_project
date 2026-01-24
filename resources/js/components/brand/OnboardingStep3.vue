<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { storeToRefs } from 'pinia';

const { t } = useI18n();
const brandsStore = useBrandsStore();
const { onboardingData } = storeToRefs(brandsStore);

const tones = ['professional', 'casual', 'playful', 'formal', 'friendly', 'authoritative'];
const personalities = ['expert', 'friendly', 'motivational', 'humorous', 'empathetic', 'bold', 'educational', 'inspirational'];
const emojiOptions = ['often', 'sometimes', 'rarely', 'never'];

const tone = computed({
    get: () => onboardingData.value.tone,
    set: (value) => brandsStore.updateOnboardingData({ tone: value }),
});

const personality = computed({
    get: () => onboardingData.value.personality,
    set: (value) => brandsStore.updateOnboardingData({ personality: value }),
});

const emojiUsage = computed({
    get: () => onboardingData.value.emojiUsage,
    set: (value) => brandsStore.updateOnboardingData({ emojiUsage: value }),
});

const togglePersonality = (trait) => {
    if (personality.value.includes(trait)) {
        personality.value = personality.value.filter(p => p !== trait);
    } else {
        personality.value = [...personality.value, trait];
    }
};
</script>

<template>
    <div>
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
            {{ t('brands.onboarding.step3.title') }}
        </h2>
        <p class="text-gray-600 mb-6">
            {{ t('brands.onboarding.step3.description') }}
        </p>

        <div class="space-y-6">
            <!-- Tone -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ t('brands.onboarding.step3.tone') }}
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <button
                        v-for="t in tones"
                        :key="t"
                        @click="tone = t"
                        class="px-4 py-3 text-sm font-medium rounded-lg border transition-colors text-left"
                        :class="{
                            'bg-blue-600 text-white border-blue-600': tone === t,
                            'bg-white text-gray-700 border-gray-300 hover:bg-gray-50': tone !== t,
                        }"
                    >
                        {{ $t(`brands.tones.${t}`) }}
                    </button>
                </div>
            </div>

            <!-- Personality traits -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ t('brands.onboarding.step3.personality') }}
                    <span class="text-gray-400 font-normal">({{ t('brands.onboarding.step3.selectMultiple') }})</span>
                </label>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="trait in personalities"
                        :key="trait"
                        @click="togglePersonality(trait)"
                        class="px-4 py-2 text-sm font-medium rounded-full border transition-colors"
                        :class="{
                            'bg-blue-600 text-white border-blue-600': personality.includes(trait),
                            'bg-white text-gray-700 border-gray-300 hover:bg-gray-50': !personality.includes(trait),
                        }"
                    >
                        {{ $t(`brands.personalities.${trait}`) }}
                    </button>
                </div>
            </div>

            <!-- Emoji usage -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ t('brands.onboarding.step3.emojiUsage') }}
                </label>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in emojiOptions"
                        :key="option"
                        @click="emojiUsage = option"
                        class="px-4 py-2 text-sm font-medium rounded-lg border transition-colors"
                        :class="{
                            'bg-blue-600 text-white border-blue-600': emojiUsage === option,
                            'bg-white text-gray-700 border-gray-300 hover:bg-gray-50': emojiUsage !== option,
                        }"
                    >
                        {{ $t(`brands.emojiUsage.${option}`) }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
