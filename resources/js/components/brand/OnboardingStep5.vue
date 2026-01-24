<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { storeToRefs } from 'pinia';

const { t } = useI18n();
const brandsStore = useBrandsStore();
const { onboardingData } = storeToRefs(brandsStore);

const platformConfigs = [
    {
        key: 'facebook',
        name: 'Facebook',
        icon: 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
        color: '#1877F2',
        maxPostsPerWeek: 21,
    },
    {
        key: 'instagram',
        name: 'Instagram',
        icon: 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z',
        color: '#E4405F',
        maxPostsPerWeek: 21,
    },
    {
        key: 'youtube',
        name: 'YouTube',
        icon: 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z',
        color: '#FF0000',
        maxPostsPerWeek: 7,
    },
];

const timeSlots = [
    '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
    '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
    '18:00', '19:00', '20:00', '21:00', '22:00',
];

const platforms = computed({
    get: () => onboardingData.value.platforms,
    set: (value) => brandsStore.updateOnboardingData({ platforms: value }),
});

const frequency = computed({
    get: () => onboardingData.value.frequency,
    set: (value) => brandsStore.updateOnboardingData({ frequency: value }),
});

const bestTimes = computed({
    get: () => onboardingData.value.bestTimes,
    set: (value) => brandsStore.updateOnboardingData({ bestTimes: value }),
});

const autoSchedule = computed({
    get: () => onboardingData.value.autoSchedule,
    set: (value) => brandsStore.updateOnboardingData({ autoSchedule: value }),
});

// Helper to check if platform is enabled - fixes reactivity
const isPlatformEnabled = (key) => {
    return platforms.value[key]?.enabled === true;
};

// Get list of enabled platforms for iteration
const enabledPlatforms = computed(() => {
    return platformConfigs.filter(p => platforms.value[p.key]?.enabled === true);
});

const togglePlatform = (key) => {
    const newPlatforms = {
        ...platforms.value,
        [key]: {
            ...platforms.value[key],
            enabled: !platforms.value[key]?.enabled,
        },
    };
    brandsStore.updateOnboardingData({ platforms: newPlatforms });
};

const updateFrequency = (key, value) => {
    const newFrequency = {
        ...frequency.value,
        [key]: parseInt(value) || 0,
    };
    brandsStore.updateOnboardingData({ frequency: newFrequency });
};

const toggleTime = (platformKey, time) => {
    const currentTimes = bestTimes.value[platformKey] || [];
    const newTimes = currentTimes.includes(time)
        ? currentTimes.filter(t => t !== time)
        : [...currentTimes, time].sort();

    const newBestTimes = {
        ...bestTimes.value,
        [platformKey]: newTimes,
    };
    brandsStore.updateOnboardingData({ bestTimes: newBestTimes });
};

const hasEnabledPlatforms = computed(() => {
    return enabledPlatforms.value.length > 0;
});

const getFrequencyValue = (key) => {
    return frequency.value[key] || 0;
};

const isTimeSelected = (platformKey, time) => {
    return bestTimes.value[platformKey]?.includes(time) || false;
};
</script>

<template>
    <div>
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
            {{ t('brands.onboarding.step5.title') }}
        </h2>
        <p class="text-gray-600 mb-6">
            {{ t('brands.onboarding.step5.description') }}
        </p>

        <div class="space-y-6">
            <!-- Platform selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">
                    {{ t('brands.onboarding.step5.selectPlatforms') }}
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <button
                        v-for="platform in platformConfigs"
                        :key="platform.key"
                        @click="togglePlatform(platform.key)"
                        class="relative p-4 rounded-xl border-2 transition-all"
                        :class="{
                            'border-blue-500 bg-blue-50': isPlatformEnabled(platform.key),
                            'border-gray-200 bg-white hover:border-gray-300': !isPlatformEnabled(platform.key),
                        }"
                    >
                        <div class="flex items-center gap-3">
                            <svg
                                class="w-8 h-8"
                                :style="{ color: platform.color }"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path :d="platform.icon" />
                            </svg>
                            <span class="font-medium text-gray-900">{{ platform.name }}</span>
                        </div>
                        <div
                            v-if="isPlatformEnabled(platform.key)"
                            class="absolute top-2 right-2"
                        >
                            <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Frequency settings for enabled platforms -->
            <div v-if="hasEnabledPlatforms">
                <label class="block text-sm font-medium text-gray-700 mb-3">
                    {{ t('brands.onboarding.step5.postsPerWeek') }}
                </label>
                <div class="space-y-4">
                    <div
                        v-for="platform in enabledPlatforms"
                        :key="`freq-${platform.key}`"
                        class="flex items-center gap-4"
                    >
                        <div class="flex items-center gap-2 w-32">
                            <svg
                                class="w-5 h-5"
                                :style="{ color: platform.color }"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path :d="platform.icon" />
                            </svg>
                            <span class="text-sm font-medium text-gray-700">{{ platform.name }}</span>
                        </div>
                        <input
                            type="range"
                            :value="getFrequencyValue(platform.key)"
                            @input="updateFrequency(platform.key, $event.target.value)"
                            min="0"
                            :max="platform.maxPostsPerWeek"
                            class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                        />
                        <span class="w-16 text-sm text-gray-600 text-right">
                            {{ getFrequencyValue(platform.key) }} / {{ t('brands.onboarding.step5.week') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Best times -->
            <div v-if="hasEnabledPlatforms">
                <label class="block text-sm font-medium text-gray-700 mb-3">
                    {{ t('brands.onboarding.step5.bestTimes') }}
                </label>
                <div class="space-y-4">
                    <div
                        v-for="platform in enabledPlatforms"
                        :key="`times-${platform.key}`"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <svg
                                class="w-4 h-4"
                                :style="{ color: platform.color }"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path :d="platform.icon" />
                            </svg>
                            <span class="text-sm font-medium text-gray-700">{{ platform.name }}</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="time in timeSlots"
                                :key="time"
                                @click="toggleTime(platform.key, time)"
                                class="px-2 py-1 text-xs font-medium rounded border transition-colors"
                                :class="{
                                    'bg-blue-600 text-white border-blue-600': isTimeSelected(platform.key, time),
                                    'bg-white text-gray-600 border-gray-300 hover:bg-gray-50': !isTimeSelected(platform.key, time),
                                }"
                            >
                                {{ time }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Auto schedule -->
            <div v-if="hasEnabledPlatforms" class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div>
                    <p class="text-sm font-medium text-gray-900">
                        {{ t('brands.onboarding.step5.autoSchedule') }}
                    </p>
                    <p class="text-sm text-gray-500">
                        {{ t('brands.onboarding.step5.autoScheduleDescription') }}
                    </p>
                </div>
                <button
                    @click="autoSchedule = !autoSchedule"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    :class="{ 'bg-blue-600': autoSchedule, 'bg-gray-200': !autoSchedule }"
                >
                    <span
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                        :class="{ 'translate-x-5': autoSchedule, 'translate-x-0': !autoSchedule }"
                    />
                </button>
            </div>

            <!-- Warning if no platform selected -->
            <div v-if="!hasEnabledPlatforms" class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="text-sm text-yellow-700">
                    {{ t('brands.onboarding.step5.selectAtLeastOne') }}
                </p>
            </div>
        </div>
    </div>
</template>
