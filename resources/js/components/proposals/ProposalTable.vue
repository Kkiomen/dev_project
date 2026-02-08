<script setup>
import { useI18n } from 'vue-i18n';
import ProposalRow from './ProposalRow.vue';

defineProps({
    proposals: { type: Array, required: true },
    generatingId: { type: String, default: null },
});

const emit = defineEmits(['update-field', 'edit', 'delete', 'generate-post']);
const { t } = useI18n();
</script>

<template>
    <div class="hidden lg:block overflow-x-auto bg-white rounded-xl border border-gray-200">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                        {{ t('postAutomation.proposals.table.date') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ t('postAutomation.proposals.table.title') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ t('postAutomation.proposals.table.keywords') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider max-w-xs">
                        {{ t('postAutomation.proposals.table.notes') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                        {{ t('postAutomation.proposals.table.status') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                        {{ t('postAutomation.proposals.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <ProposalRow
                    v-for="proposal in proposals"
                    :key="proposal.id"
                    :proposal="proposal"
                    :generating-id="generatingId"
                    @update-field="emit('update-field', $event)"
                    @edit="emit('edit', $event)"
                    @delete="emit('delete', $event)"
                    @generate-post="emit('generate-post', $event)"
                />
            </tbody>
        </table>
    </div>
</template>
