<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useBoardsStore } from '@/stores/boards';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import Button from '@/components/common/Button.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import CreateBoardModal from '@/components/boards/CreateBoardModal.vue';

const { t } = useI18n();
const router = useRouter();
const boardsStore = useBoardsStore();
const { confirm } = useConfirm();
const toast = useToast();

const loading = ref(true);
const showCreateModal = ref(false);
const searchQuery = ref('');

const filteredBoards = computed(() => {
    if (!searchQuery.value) return boardsStore.boards;
    const query = searchQuery.value.toLowerCase();
    return boardsStore.boards.filter(b =>
        b.name.toLowerCase().includes(query) ||
        (b.description && b.description.toLowerCase().includes(query))
    );
});

const fetchData = async () => {
    loading.value = true;
    try {
        await boardsStore.fetchBoards();
    } catch (error) {
        console.error('Failed to fetch boards:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchData);

const handleBoardCreated = (board) => {
    showCreateModal.value = false;
    toast.success(t('boards.boardCreated'));
    router.push({ name: 'board.view', params: { boardId: board.id } });
};

const handleDelete = async (board, event) => {
    event.stopPropagation();
    const confirmed = await confirm({
        title: t('boards.deleteBoard'),
        message: t('boards.deleteBoardConfirm', { name: board.name }),
        confirmText: t('common.delete'),
        variant: 'danger',
    });

    if (confirmed) {
        try {
            await boardsStore.deleteBoard(board.id);
            toast.success(t('boards.boardDeleted'));
        } catch (error) {
            console.error('Failed to delete board:', error);
        }
    }
};

const openBoard = (board) => {
    router.push({ name: 'board.view', params: { boardId: board.id } });
};
</script>

<template>
    <div class="py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ t('boards.title') }}</h1>
                    <p class="text-gray-500 mt-1">{{ t('boards.subtitle') }}</p>
                </div>
                <Button @click="showCreateModal = true">
                    <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('boards.newBoard') }}
                </Button>
            </div>

            <!-- Search -->
            <div v-if="boardsStore.boards.length > 0" class="mb-6">
                <div class="relative max-w-md">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('boards.searchBoards')"
                        class="block w-full pl-10 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    />
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="flex justify-center py-20">
                <LoadingSpinner />
            </div>

            <!-- Empty state -->
            <div v-else-if="boardsStore.boards.length === 0" class="text-center py-20">
                <div class="mx-auto w-20 h-20 rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center mb-6">
                    <svg class="w-10 h-10 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">{{ t('boards.noBoards') }}</h3>
                <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">{{ t('boards.noBoardsDescription') }}</p>
                <div class="mt-6">
                    <Button @click="showCreateModal = true">
                        <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ t('boards.newBoard') }}
                    </Button>
                </div>
            </div>

            <!-- Boards grid -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                <div
                    v-for="board in filteredBoards"
                    :key="board.id"
                    @click="openBoard(board)"
                    class="relative bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg hover:border-gray-300 transition-all duration-200 cursor-pointer group"
                >
                    <!-- Color header band -->
                    <div class="h-1.5" :style="{ backgroundColor: board.color || '#3B82F6' }" />

                    <div class="p-5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div
                                    class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                    :style="{ backgroundColor: (board.color || '#3B82F6') + '18' }"
                                >
                                    <svg class="w-4 h-4" :style="{ color: board.color || '#3B82F6' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
                                    </svg>
                                </div>
                                <h3 class="font-semibold text-gray-900 truncate group-hover:text-blue-600 transition-colors">
                                    {{ board.name }}
                                </h3>
                            </div>
                            <button
                                @click="handleDelete(board, $event)"
                                class="p-1.5 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 opacity-0 group-hover:opacity-100 transition-all flex-shrink-0"
                                :title="t('boards.deleteBoard')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>

                        <p v-if="board.description" class="mt-2.5 text-sm text-gray-500 line-clamp-2 leading-relaxed">
                            {{ board.description }}
                        </p>

                        <!-- Stats -->
                        <div class="mt-4 pt-3.5 border-t border-gray-100 flex items-center gap-4 text-xs text-gray-400">
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                {{ board.columns_count || 0 }} {{ t('boards.columns') }}
                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                                {{ board.cards_count || 0 }} {{ t('boards.cards') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Add Board Card -->
                <button
                    @click="showCreateModal = true"
                    class="rounded-xl border-2 border-dashed border-gray-200 hover:border-blue-300 hover:bg-blue-50/50 transition-all duration-200 min-h-[160px] flex flex-col items-center justify-center gap-2 text-gray-400 hover:text-blue-500 group"
                >
                    <div class="w-10 h-10 rounded-xl bg-gray-100 group-hover:bg-blue-100 flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <span class="text-sm font-medium">{{ t('boards.newBoard') }}</span>
                </button>
            </div>
        </div>

        <!-- Create Board Modal -->
        <CreateBoardModal
            :show="showCreateModal"
            @close="showCreateModal = false"
            @created="handleBoardCreated"
        />
    </div>
</template>
