<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useApprovalStore } from '@/stores/approval';
import { useConfirm } from '@/composables/useConfirm';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import ApprovalTokenList from '@/components/approval/ApprovalTokenList.vue';
import ApprovalTokenForm from '@/components/approval/ApprovalTokenForm.vue';

const { t } = useI18n();
const router = useRouter();
const approvalStore = useApprovalStore();
const { confirm } = useConfirm();

const loading = ref(true);
const showCreateModal = ref(false);
const createdToken = ref(null);

const fetchData = async () => {
    loading.value = true;
    try {
        await approvalStore.fetchTokens();
    } catch (error) {
        console.error('Failed to fetch tokens:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchData);

const handleCreate = async (data) => {
    try {
        const token = await approvalStore.createToken(data);
        createdToken.value = token;
    } catch (error) {
        console.error('Failed to create token:', error);
    }
};

const handleRevoke = async (token) => {
    const confirmed = await confirm({
        title: t('approval.revokeToken'),
        message: t('approval.revokeTokenMessage', { name: token.client_name }),
        confirmText: t('approval.revoke'),
        danger: true,
    });

    if (confirmed) {
        try {
            await approvalStore.revokeToken(token.id);
        } catch (error) {
            console.error('Failed to revoke token:', error);
        }
    }
};

const handleRegenerate = async (token) => {
    const confirmed = await confirm({
        title: t('approval.regenerateToken'),
        message: t('approval.regenerateTokenMessage', { name: token.client_name }),
        confirmText: t('approval.regenerate'),
    });

    if (confirmed) {
        try {
            const updatedToken = await approvalStore.regenerateToken(token.id);
            createdToken.value = updatedToken;
        } catch (error) {
            console.error('Failed to regenerate token:', error);
        }
    }
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
};

const closeTokenModal = () => {
    showCreateModal.value = false;
    createdToken.value = null;
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <RouterLink
                        :to="{ name: 'calendar' }"
                        class="text-gray-500 hover:text-gray-700"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </RouterLink>
                    <h1 class="text-xl font-semibold text-gray-900">
                        {{ t('approval.tokens') }}
                    </h1>
                </div>
                <Button @click="showCreateModal = true">
                    {{ t('approval.createToken') }}
                </Button>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            <div v-if="loading" class="flex items-center justify-center py-12">
                <LoadingSpinner size="lg" />
            </div>

            <div
                v-else-if="approvalStore.tokens.length === 0"
                class="text-center py-12"
            >
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">
                    {{ t('approval.noTokens') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('approval.noTokensDescription') }}
                </p>
                <div class="mt-6">
                    <Button @click="showCreateModal = true">
                        {{ t('approval.createToken') }}
                    </Button>
                </div>
            </div>

            <ApprovalTokenList
                v-else
                :tokens="approvalStore.tokens"
                @revoke="handleRevoke"
                @regenerate="handleRegenerate"
                @copy="copyToClipboard"
            />
        </div>

        <!-- Create Modal -->
        <teleport to="body">
            <div
                v-if="showCreateModal"
                class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                @click.self="closeTokenModal"
            >
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                    <template v-if="createdToken">
                        <div class="p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                                {{ t('approval.tokenCreated') }}
                            </h2>
                            <p class="text-sm text-gray-600 mb-4">
                                {{ t('approval.tokenCreatedDescription') }}
                            </p>
                            <div class="bg-gray-100 rounded-lg p-4 mb-4">
                                <p class="text-xs text-gray-500 mb-2">{{ t('approval.approvalLink') }}</p>
                                <div class="flex items-center space-x-2">
                                    <input
                                        type="text"
                                        readonly
                                        :value="createdToken.approval_url"
                                        class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white"
                                    />
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        @click="copyToClipboard(createdToken.approval_url)"
                                    >
                                        {{ t('common.copy') }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-6 py-3 flex justify-end rounded-b-lg">
                            <Button @click="closeTokenModal">
                                {{ t('common.done') }}
                            </Button>
                        </div>
                    </template>
                    <ApprovalTokenForm
                        v-else
                        @submit="handleCreate"
                        @cancel="closeTokenModal"
                    />
                </div>
            </div>
        </teleport>
    </div>
</template>
