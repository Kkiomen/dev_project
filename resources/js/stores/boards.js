import { defineStore } from 'pinia';
import axios from 'axios';

export const useBoardsStore = defineStore('boards', {
    state: () => ({
        boards: [],
        currentBoard: null,
        loading: false,
        error: null,
    }),

    getters: {
        getBoardById: (state) => (id) => state.boards.find(b => b.id === id),
    },

    actions: {
        async fetchBoards() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/boards');
                this.boards = response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch boards';
            } finally {
                this.loading = false;
            }
        },

        async fetchBoard(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/v1/boards/${id}`);
                this.currentBoard = response.data.data;
                return this.currentBoard;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch board';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createBoard(data) {
            const response = await axios.post('/api/v1/boards', data);
            const newBoard = response.data.data;
            this.boards.unshift(newBoard);
            return newBoard;
        },

        async updateBoard(id, data) {
            const response = await axios.put(`/api/v1/boards/${id}`, data);
            const updated = response.data.data;
            const index = this.boards.findIndex(b => b.id === id);
            if (index !== -1) {
                this.boards[index] = { ...this.boards[index], ...updated };
            }
            if (this.currentBoard?.id === id) {
                this.currentBoard = { ...this.currentBoard, ...updated };
            }
            return updated;
        },

        async deleteBoard(id) {
            await axios.delete(`/api/v1/boards/${id}`);
            this.boards = this.boards.filter(b => b.id !== id);
            if (this.currentBoard?.id === id) {
                this.currentBoard = null;
            }
        },

        // Column actions
        async createColumn(boardId, data) {
            const response = await axios.post(`/api/v1/boards/${boardId}/columns`, data);
            const newColumn = response.data.data;
            if (this.currentBoard?.id === boardId) {
                this.currentBoard.columns = [...(this.currentBoard.columns || []), newColumn];
            }
            return newColumn;
        },

        async updateColumn(columnId, data) {
            const response = await axios.put(`/api/v1/columns/${columnId}`, data);
            const updated = response.data.data;
            if (this.currentBoard) {
                const index = this.currentBoard.columns?.findIndex(c => c.id === columnId);
                if (index !== undefined && index !== -1) {
                    this.currentBoard.columns[index] = { ...this.currentBoard.columns[index], ...updated };
                }
            }
            return updated;
        },

        async deleteColumn(columnId) {
            await axios.delete(`/api/v1/columns/${columnId}`);
            if (this.currentBoard) {
                this.currentBoard.columns = this.currentBoard.columns?.filter(c => c.id !== columnId) || [];
            }
        },

        async reorderColumn(columnId, position) {
            await axios.post(`/api/v1/columns/${columnId}/reorder`, { position });
        },

        // Card actions
        async createCard(columnId, data) {
            const response = await axios.post(`/api/v1/columns/${columnId}/cards`, data);
            const newCard = response.data.data;
            if (this.currentBoard) {
                const column = this.currentBoard.columns?.find(c => c.id === columnId);
                if (column) {
                    column.cards = [...(column.cards || []), newCard];
                }
            }
            return newCard;
        },

        async updateCard(cardId, data) {
            const response = await axios.put(`/api/v1/cards/${cardId}`, data);
            const updated = response.data.data;
            if (this.currentBoard) {
                for (const column of (this.currentBoard.columns || [])) {
                    const index = column.cards?.findIndex(c => c.id === cardId);
                    if (index !== undefined && index !== -1) {
                        column.cards[index] = { ...column.cards[index], ...updated };
                        break;
                    }
                }
            }
            return updated;
        },

        async deleteCard(cardId) {
            await axios.delete(`/api/v1/cards/${cardId}`);
            if (this.currentBoard) {
                for (const column of (this.currentBoard.columns || [])) {
                    column.cards = column.cards?.filter(c => c.id !== cardId) || [];
                }
            }
        },

        async moveCard(cardId, columnId, position) {
            const response = await axios.put(`/api/v1/cards/${cardId}/move`, {
                column_id: columnId,
                position,
            });
            return response.data.data;
        },

        // Optimistic move for drag & drop
        moveCardOptimistic(cardId, fromColumnId, toColumnId, newPosition) {
            if (!this.currentBoard) return;

            const fromColumn = this.currentBoard.columns?.find(c => c.id === fromColumnId);
            const toColumn = this.currentBoard.columns?.find(c => c.id === toColumnId);
            if (!fromColumn || !toColumn) return;

            const cardIndex = fromColumn.cards?.findIndex(c => c.id === cardId);
            if (cardIndex === undefined || cardIndex === -1) return;

            const [card] = fromColumn.cards.splice(cardIndex, 1);
            card.column_id = toColumnId;
            card.position = newPosition;

            if (!toColumn.cards) toColumn.cards = [];
            toColumn.cards.splice(newPosition, 0, card);

            // Recalculate positions
            fromColumn.cards.forEach((c, i) => c.position = i);
            toColumn.cards.forEach((c, i) => c.position = i);
        },
    },
});
