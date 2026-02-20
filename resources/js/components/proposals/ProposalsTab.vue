<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useProposalsStore } from '@/stores/proposals';
import { useBrandsStore } from '@/stores/brands';
import { useAuthStore } from '@/stores/auth';
import { useToast } from '@/composables/useToast';

import ProposalToolbar from './ProposalToolbar.vue';
import GenerateProposalsModal from './GenerateProposalsModal.vue';
import ProposalTable from './ProposalTable.vue';
import ProposalCard from './ProposalCard.vue';
import ProposalCalendarView from './ProposalCalendarView.vue';
import ProposalFormModal from './ProposalFormModal.vue';
import ProposalBulkBar from './ProposalBulkBar.vue';
import AutomationPagination from '@/components/automation/AutomationPagination.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const emit = defineEmits(['express-process']);

const { t } = useI18n();
const proposalsStore = useProposalsStore();
const brandsStore = useBrandsStore();
const authStore = useAuthStore();
const toast = useToast();

// State
const viewMode = ref('table');
const search = ref('');
const showFormModal = ref(false);
const editingProposal = ref(null);
const prefillDate = ref('');
const generatingId = ref(null);
const showGenerateModal = ref(false);
const selectedIds = ref([]);
const bulkGenerating = ref(false);

// Computed
const proposals = computed(() => proposalsStore.proposals);
const pagination = computed(() => proposalsStore.pagination);
const loading = computed(() => proposalsStore.loading);

// Data fetching
function fetchProposals(page = 1) {
    const params = { page, per_page: 20 };
    if (search.value) params.search = search.value;
    if (brandsStore.currentBrand?.id) params.brand_id = brandsStore.currentBrand.id;
    proposalsStore.fetchProposals(params).catch(() => {
        toast.error(t('postAutomation.proposals.errors.fetchFailed'));
    });
}

function refresh() {
    fetchProposals(pagination.value.currentPage);
}

// Form modal
function openCreateModal() {
    editingProposal.value = null;
    prefillDate.value = '';
    showFormModal.value = true;
}

function openCreateModalWithDate(dateStr) {
    editingProposal.value = null;
    prefillDate.value = dateStr;
    showFormModal.value = true;
}

function openEditModal(proposal) {
    editingProposal.value = proposal;
    prefillDate.value = '';
    showFormModal.value = true;
}

function closeFormModal() {
    showFormModal.value = false;
    editingProposal.value = null;
    prefillDate.value = '';
}

async function handleSave(formData) {
    const data = { ...formData };
    if (brandsStore.currentBrand?.id) {
        data.brand_id = brandsStore.currentBrand.id;
    }

    try {
        if (editingProposal.value) {
            await proposalsStore.updateProposal(editingProposal.value.id, data);
            toast.success(t('postAutomation.proposals.success.updated'));
        } else {
            await proposalsStore.createProposal(data);
            toast.success(t('postAutomation.proposals.success.created'));
        }
        closeFormModal();
        fetchProposals(pagination.value.currentPage);
    } catch (err) {
        const serverError = err?.response?.data?.message;
        toast.error(serverError || t('postAutomation.proposals.errors.saveFailed'), 5000);
    }
}

// Inline editing
async function updateField({ proposalId, field, value }) {
    try {
        await proposalsStore.updateProposal(proposalId, { [field]: value });
    } catch {
        toast.error(t('postAutomation.proposals.errors.saveFailed'));
    }
}

// Delete
async function deleteProposal(proposalId) {
    if (!confirm(t('postAutomation.proposals.confirm.delete'))) return;
    try {
        await proposalsStore.deleteProposal(proposalId);
        toast.success(t('postAutomation.proposals.success.deleted'));
    } catch {
        toast.error(t('postAutomation.proposals.errors.deleteFailed'));
    }
}

// Generate post from proposal
async function handleGeneratePost(proposalId) {
    generatingId.value = proposalId;
    try {
        await proposalsStore.generatePost(proposalId);
        toast.success(t('postAutomation.proposals.success.postGenerated'));
    } catch (err) {
        const errorCode = err?.response?.data?.error_code;
        const errorKey = `postAutomation.proposals.errors.${errorCode}`;
        const translated = errorCode && t(errorKey) !== errorKey ? t(errorKey) : null;
        toast.error(translated || t('postAutomation.proposals.errors.generateFailed'), 5000);
    } finally {
        generatingId.value = null;
    }
}

// Generate batch
const panelLanguage = computed(() => authStore.user?.settings?.language || 'en');
const brandLanguage = computed(() => brandsStore.currentBrand?.voice?.language || 'en');

async function handleGenerateBatch({ days, language, autoProcess }) {
    try {
        const result = await proposalsStore.generateBatch(days, brandsStore.currentBrand?.id, language);
        toast.success(t('postAutomation.proposals.generate.success', { count: result.count }));
        showGenerateModal.value = false;
        fetchProposals(1);

        if (autoProcess) {
            // Select all new proposals and bulk generate posts, then trigger express process
            const allProposals = proposalsStore.proposals;
            const pendingIds = allProposals.filter(p => p.status === 'pending').map(p => p.id);
            if (pendingIds.length) {
                bulkGenerating.value = true;
                try {
                    const bulkResult = await proposalsStore.bulkGeneratePosts(pendingIds);
                    toast.success(t('postAutomation.proposals.success.bulkGenerated', {
                        success: bulkResult.success,
                        total: bulkResult.total,
                    }));
                    const postIds = bulkResult.post_ids || [];
                    emit('express-process', postIds);
                } catch {
                    toast.error(t('postAutomation.proposals.errors.bulkGenerateFailed'));
                } finally {
                    bulkGenerating.value = false;
                }
            }
        }
    } catch (err) {
        const errorCode = err?.response?.data?.error_code;
        const errorKey = `postAutomation.proposals.errors.${errorCode}`;
        const translated = errorCode && t(errorKey) !== errorKey ? t(errorKey) : null;
        toast.error(translated || t('postAutomation.proposals.generate.failed'), 5000);
    }
}

// Selection
function toggleSelect(id) {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(idx, 1);
    }
}

function toggleSelectAll() {
    if (selectedIds.value.length === proposals.value.length) {
        selectedIds.value = [];
    } else {
        selectedIds.value = proposals.value.map(p => p.id);
    }
}

function clearSelection() {
    selectedIds.value = [];
}

async function bulkGeneratePosts() {
    bulkGenerating.value = true;
    try {
        const result = await proposalsStore.bulkGeneratePosts(selectedIds.value);
        toast.success(t('postAutomation.proposals.success.bulkGenerated', { success: result.success, total: result.total }));
        clearSelection();
        fetchProposals(pagination.value.currentPage);
    } catch {
        toast.error(t('postAutomation.proposals.errors.bulkGenerateFailed'));
    } finally {
        bulkGenerating.value = false;
    }
}

async function bulkGenerateAndProcess() {
    bulkGenerating.value = true;
    try {
        const result = await proposalsStore.bulkGeneratePosts(selectedIds.value);
        toast.success(t('postAutomation.proposals.success.bulkGenerated', { success: result.success, total: result.total }));
        const postIds = result.post_ids || [];
        clearSelection();
        fetchProposals(pagination.value.currentPage);
        emit('express-process', postIds);
    } catch {
        toast.error(t('postAutomation.proposals.errors.bulkGenerateFailed'));
    } finally {
        bulkGenerating.value = false;
    }
}

// Page change
function onPageChange(page) {
    clearSelection();
    fetchProposals(page);
}

// Watchers
let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    clearSelection();
    searchTimeout = setTimeout(() => fetchProposals(1), 300);
});

watch(() => brandsStore.currentBrand?.id, () => {
    clearSelection();
    fetchProposals(1);
});

// Computed for modal - when prefillDate is set, create a pseudo-proposal object for the form
const modalProposal = computed(() => {
    if (editingProposal.value) return editingProposal.value;
    if (prefillDate.value) {
        return { scheduled_date: prefillDate.value, title: '', keywords: [], notes: '' };
    }
    return null;
});

onMounted(() => {
    fetchProposals();
});
</script>

<template>
    <div>
        <!-- Subtitle -->
        <p class="text-sm text-gray-500 mb-4">
            {{ t('postAutomation.proposals.subtitle') }}
        </p>

        <!-- Toolbar -->
        <ProposalToolbar
            v-model="search"
            :view-mode="viewMode"
            @update:view-mode="viewMode = $event"
            @add="openCreateModal"
            @generate="showGenerateModal = true"
            @refresh="refresh"
        />

        <!-- Calendar View -->
        <template v-if="viewMode === 'calendar'">
            <ProposalCalendarView
                @add-proposal="openCreateModalWithDate"
                @edit-proposal="openEditModal"
            />
        </template>

        <!-- Table View -->
        <template v-else>
            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-20">
                <LoadingSpinner size="lg" />
            </div>

            <!-- Empty State -->
            <div v-else-if="!proposals.length" class="text-center py-16">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="text-sm font-medium text-gray-900 mb-1">
                    {{ t('postAutomation.proposals.empty.title') }}
                </h3>
                <p class="text-sm text-gray-500 mb-4">
                    {{ t('postAutomation.proposals.empty.description') }}
                </p>
                <button
                    @click="openCreateModal"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                >
                    {{ t('postAutomation.proposals.toolbar.addProposal') }}
                </button>
            </div>

            <!-- Content -->
            <template v-else>
                <!-- Desktop Table -->
                <ProposalTable
                    :proposals="proposals"
                    :generating-id="generatingId"
                    :selected-ids="selectedIds"
                    @update-field="updateField"
                    @edit="openEditModal"
                    @delete="deleteProposal"
                    @generate-post="handleGeneratePost"
                    @toggle-select="toggleSelect"
                    @toggle-select-all="toggleSelectAll"
                />

                <!-- Mobile Cards -->
                <div class="lg:hidden space-y-3">
                    <ProposalCard
                        v-for="proposal in proposals"
                        :key="proposal.id"
                        :proposal="proposal"
                        :generating-id="generatingId"
                        :selected="selectedIds.includes(proposal.id)"
                        @update-field="updateField"
                        @edit="openEditModal"
                        @delete="deleteProposal"
                        @generate-post="handleGeneratePost"
                        @toggle-select="toggleSelect"
                    />
                </div>

                <!-- Bulk Action Bar -->
                <ProposalBulkBar
                    :count="selectedIds.length"
                    :generating="bulkGenerating"
                    @generate="bulkGeneratePosts"
                    @generate-and-process="bulkGenerateAndProcess"
                    @clear="clearSelection"
                />

                <!-- Pagination -->
                <AutomationPagination
                    :current-page="pagination.currentPage"
                    :last-page="pagination.lastPage"
                    :total="pagination.total"
                    showing-key="postAutomation.proposals.pagination.showing"
                    @update:current-page="onPageChange"
                />
            </template>
        </template>

        <!-- Form Modal -->
        <ProposalFormModal
            :show="showFormModal"
            :proposal="modalProposal"
            @close="closeFormModal"
            @save="handleSave"
        />

        <!-- Generate Proposals Modal -->
        <GenerateProposalsModal
            :show="showGenerateModal"
            :loading="proposalsStore.generating"
            :panel-language="panelLanguage"
            :brand-language="brandLanguage"
            @close="showGenerateModal = false"
            @generate="handleGenerateBatch"
        />
    </div>
</template>
