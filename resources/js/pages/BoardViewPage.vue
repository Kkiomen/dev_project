<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useBoardsStore } from '@/stores/boards';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';
import BoardKanban from '@/components/boards/BoardKanban.vue';
import CreateBoardModal from '@/components/boards/CreateBoardModal.vue';

const props = defineProps({
    boardId: {
        type: String,
        required: true,
    },
});

const { t } = useI18n();
const router = useRouter();
const boardsStore = useBoardsStore();
const toast = useToast();

const loading = ref(true);
const showEditModal = ref(false);

const fetchBoard = async () => {
    loading.value = true;
    try {
        await boardsStore.fetchBoard(props.boardId);
    } catch (error) {
        console.error('Failed to fetch board:', error);
        router.push({ name: 'boards' });
    } finally {
        loading.value = false;
    }
};

onMounted(fetchBoard);

const handleBoardUpdated = () => {
    showEditModal.value = false;
    toast.success(t('boards.boardUpdated'));
};

const goBack = () => {
    router.push({ name: 'boards' });
};

const totalCards = () => {
    if (!boardsStore.currentBoard?.columns) return 0;
    return boardsStore.currentBoard.columns.reduce((sum, col) => sum + (col.cards?.length || 0), 0);
};
</script>

<template>
    <div class="flex flex-col h-[calc(100vh-4rem)] bg-gray-50">
        <!-- Loading -->
        <div v-if="loading" class="flex justify-center items-center flex-1">
            <LoadingSpinner />
        </div>

        <template v-else-if="boardsStore.currentBoard">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 px-4 sm:px-6 py-3 flex-shrink-0">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <button
                            @click="goBack"
                            class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition flex-shrink-0"
                            :title="t('boards.backToBoards')"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>

                        <div class="flex items-center gap-2.5 min-w-0">
                            <div
                                class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                                :style="{ backgroundColor: (boardsStore.currentBoard.color || '#3B82F6') + '18' }"
                            >
                                <svg class="w-3.5 h-3.5" :style="{ color: boardsStore.currentBoard.color || '#3B82F6' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-base font-semibold text-gray-900 truncate">
                                    {{ boardsStore.currentBoard.name }}
                                </h1>
                            </div>
                        </div>

                        <!-- Stats pills -->
                        <div class="hidden sm:flex items-center gap-2 ml-2">
                            <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                                {{ boardsStore.currentBoard.columns?.length || 0 }} {{ t('boards.columns') }}
                            </span>
                            <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                                {{ totalCards() }} {{ t('boards.cards') }}
                            </span>
                        </div>
                    </div>

                    <Button size="sm" variant="secondary" @click="showEditModal = true">
                        <svg class="w-3.5 h-3.5 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ t('boards.editBoard') }}
                    </Button>
                </div>
            </div>

            <!-- Kanban Board -->
            <div class="flex-1 overflow-hidden">
                <BoardKanban :board="boardsStore.currentBoard" />
            </div>
        </template>

        <!-- Edit Board Modal -->
        <CreateBoardModal
            v-if="boardsStore.currentBoard"
            :show="showEditModal"
            :board="boardsStore.currentBoard"
            @close="showEditModal = false"
            @created="handleBoardUpdated"
        />
    </div>
</template>
