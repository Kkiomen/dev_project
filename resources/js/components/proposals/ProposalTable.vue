<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ProposalRow from './ProposalRow.vue';

const props = defineProps({
    proposals: { type: Array, required: true },
    generatingId: { type: String, default: null },
    selectedIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['update-field', 'edit', 'delete', 'generate-post', 'toggle-select', 'toggle-select-all']);
const { t } = useI18n();

const allSelected = computed(() => props.proposals.length > 0 && props.selectedIds.length === props.proposals.length);
const someSelected = computed(() => props.selectedIds.length > 0 && !allSelected.value);
</script>

<template>
    <div class="hidden lg:block overflow-x-auto bg-white rounded-xl border border-gray-200">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="w-10 px-3 py-3">
                        <input
                            type="checkbox"
                            :checked="allSelected"
                            :indeterminate="someSelected"
                            @change="emit('toggle-select-all')"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                    </th>
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
                    :selected="selectedIds.includes(proposal.id)"
                    @update-field="emit('update-field', $event)"
                    @edit="emit('edit', $event)"
                    @delete="emit('delete', $event)"
                    @generate-post="emit('generate-post', $event)"
                    @toggle-select="emit('toggle-select', $event)"
                />
            </tbody>
        </table>
    </div>
</template>
