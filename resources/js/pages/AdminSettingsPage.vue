<script setup>
import { onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAdminSettingsStore } from '@/stores/adminSettings';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const { t } = useI18n();
const settingsStore = useAdminSettingsStore();
const toast = useToast();

onMounted(() => settingsStore.fetchSettings());

const toggleSetting = async (key) => {
    try {
        await settingsStore.updateSettings({
            [key]: !settingsStore.settings[key],
        });
        toast.success(t('adminSettings.settingsUpdated'));
    } catch {
        toast.error(t('adminSettings.settingsUpdateError'));
    }
};
</script>

<template>
    <div class="py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900">{{ t('adminSettings.title') }}</h1>
                <p class="text-gray-500 mt-1">{{ t('adminSettings.subtitle') }}</p>
            </div>

            <LoadingSpinner v-if="settingsStore.loading" />

            <div v-else class="space-y-6">
                <!-- Access Control Section -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-6">
                        {{ t('adminSettings.accessControlSection') }}
                    </h2>

                    <div class="space-y-6">
                        <!-- Registration toggle -->
                        <div class="flex items-center justify-between">
                            <div class="flex-1 mr-4">
                                <p class="text-sm font-medium text-gray-700">
                                    {{ settingsStore.settings.registration_enabled
                                        ? t('adminSettings.registrationEnabled')
                                        : t('adminSettings.registrationDisabled')
                                    }}
                                </p>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ t('adminSettings.registrationDescription') }}
                                </p>
                            </div>
                            <button
                                @click="toggleSetting('registration_enabled')"
                                :disabled="settingsStore.saving"
                                :class="[
                                    'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                    settingsStore.settings.registration_enabled ? 'bg-blue-600' : 'bg-gray-200',
                                    settingsStore.saving ? 'opacity-50 cursor-not-allowed' : '',
                                ]"
                                role="switch"
                                :aria-checked="settingsStore.settings.registration_enabled"
                            >
                                <span
                                    :class="[
                                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                        settingsStore.settings.registration_enabled ? 'translate-x-5' : 'translate-x-0',
                                    ]"
                                />
                            </button>
                        </div>

                        <div class="border-t border-gray-100" />

                        <!-- Login toggle -->
                        <div class="flex items-center justify-between">
                            <div class="flex-1 mr-4">
                                <p class="text-sm font-medium text-gray-700">
                                    {{ settingsStore.settings.login_enabled
                                        ? t('adminSettings.loginEnabled')
                                        : t('adminSettings.loginDisabled')
                                    }}
                                </p>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ t('adminSettings.loginDescription') }}
                                </p>
                            </div>
                            <button
                                @click="toggleSetting('login_enabled')"
                                :disabled="settingsStore.saving"
                                :class="[
                                    'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                    settingsStore.settings.login_enabled ? 'bg-blue-600' : 'bg-gray-200',
                                    settingsStore.saving ? 'opacity-50 cursor-not-allowed' : '',
                                ]"
                                role="switch"
                                :aria-checked="settingsStore.settings.login_enabled"
                            >
                                <span
                                    :class="[
                                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                        settingsStore.settings.login_enabled ? 'translate-x-5' : 'translate-x-0',
                                    ]"
                                />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
