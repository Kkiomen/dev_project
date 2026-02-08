<script setup>
import { useI18n } from 'vue-i18n';

defineProps({
    modelValue: { type: String, default: '' },
    viewMode: { type: String, default: 'table' },
});

const emit = defineEmits(['update:modelValue', 'update:viewMode', 'add', 'generate', 'refresh']);
const { t } = useI18n();
</script>

<template>
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-4">
        <!-- Search -->
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
                :value="modelValue"
                @input="emit('update:modelValue', $event.target.value)"
                type="text"
                :placeholder="t('postAutomation.proposals.toolbar.searchPlaceholder')"
                class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
        </div>

        <div class="flex items-center gap-2">
            <!-- View Toggle -->
            <div class="flex rounded-lg border border-gray-300 overflow-hidden">
                <button
                    @click="emit('update:viewMode', 'table')"
                    :class="[
                        'px-3 py-2 text-sm font-medium',
                        viewMode === 'table'
                            ? 'bg-blue-600 text-white'
                            : 'bg-white text-gray-700 hover:bg-gray-50'
                    ]"
                >
                    <svg class="w-4 h-4 inline-block sm:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    <span class="hidden sm:inline">{{ t('postAutomation.proposals.toolbar.viewTable') }}</span>
                </button>
                <button
                    @click="emit('update:viewMode', 'calendar')"
                    :class="[
                        'px-3 py-2 text-sm font-medium border-l border-gray-300',
                        viewMode === 'calendar'
                            ? 'bg-blue-600 text-white'
                            : 'bg-white text-gray-700 hover:bg-gray-50'
                    ]"
                >
                    <svg class="w-4 h-4 inline-block sm:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="hidden sm:inline">{{ t('postAutomation.proposals.toolbar.viewCalendar') }}</span>
                </button>
            </div>

            <!-- Generate Proposals (AI) -->
            <button
                @click="emit('generate')"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 whitespace-nowrap"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                </svg>
                <span class="hidden sm:inline">{{ t('postAutomation.proposals.toolbar.generate') }}</span>
            </button>

            <!-- Add Proposal -->
            <button
                @click="emit('add')"
                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 whitespace-nowrap"
            >
                <svg class="w-4 h-4 inline-block mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span class="hidden sm:inline">{{ t('postAutomation.proposals.toolbar.addProposal') }}</span>
            </button>

            <!-- Refresh -->
            <button
                @click="emit('refresh')"
                class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                :title="t('postAutomation.proposals.toolbar.refresh')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>
        </div>
    </div>
</template>
