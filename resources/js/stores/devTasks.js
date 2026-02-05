import { defineStore } from 'pinia';
import axios from 'axios';

export const useDevTasksStore = defineStore('devTasks', {
    state: () => ({
        tasks: [],
        columns: [
            { id: 'backlog', name: 'Backlog', color: '#6B7280' },
            { id: 'in_progress', name: 'In Progress', color: '#F59E0B' },
            { id: 'review', name: 'Review', color: '#3B82F6' },
            { id: 'done', name: 'Done', color: '#10B981' },
        ],
        projects: [],
        currentTask: null,
        taskLogs: [],
        loading: false,
        logsLoading: false,
        error: null,
        // New state for expanded features
        activeTimer: null,
        savedFilters: [],
        quickFilter: 'all',
        currentUserId: null,
    }),

    getters: {
        tasksByStatus: (state) => (status) =>
            state.tasks
                .filter(t => t.status === status)
                .sort((a, b) => a.position - b.position),

        getTaskById: (state) => (id) => state.tasks.find(t => t.id === id),

        totalTasks: (state) => state.tasks.length,

        taskCountByStatus: (state) => {
            const counts = {};
            state.columns.forEach(col => {
                counts[col.id] = state.tasks.filter(t => t.status === col.id).length;
            });
            return counts;
        },

        // New getters
        allTasks: (state) => state.tasks.sort((a, b) => a.position - b.position),

        overdueTasksCount: (state) =>
            state.tasks.filter(t => t.is_overdue && t.status !== 'done').length,

        dueSoonTasksCount: (state) =>
            state.tasks.filter(t => t.is_due_soon && t.status !== 'done').length,

        myTasks: (state) =>
            state.tasks.filter(t => t.assigned_to?.id === state.currentUserId),

        filteredTasks: (state) => {
            let tasks = state.tasks;

            switch (state.quickFilter) {
                case 'my':
                    tasks = tasks.filter(t => t.assigned_to?.id === state.currentUserId);
                    break;
                case 'overdue':
                    tasks = tasks.filter(t => t.is_overdue);
                    break;
                case 'due_soon':
                    tasks = tasks.filter(t => t.is_due_soon);
                    break;
            }

            return tasks;
        },
    },

    actions: {
        async fetchTasks(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/panel/admin/dev-tasks', { params });
                this.tasks = response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch tasks';
            } finally {
                this.loading = false;
            }
        },

        async fetchProjects() {
            try {
                const response = await axios.get('/api/panel/admin/dev-tasks/projects');
                this.projects = response.data.data;
            } catch (error) {
                console.error('Failed to fetch projects:', error);
            }
        },

        async createProject(data) {
            const response = await axios.post('/api/panel/admin/dev-tasks/projects', data);
            const project = response.data.data;
            this.projects.push(project);
            return project;
        },

        async createTask(data) {
            const response = await axios.post('/api/panel/admin/dev-tasks', data);
            const task = response.data.data;
            this.tasks.push(task);
            return task;
        },

        async updateTask(taskId, data) {
            const response = await axios.put(`/api/panel/admin/dev-tasks/${taskId}`, data);
            const updated = response.data.data;
            const index = this.tasks.findIndex(t => t.id === taskId);
            if (index !== -1) {
                this.tasks[index] = updated;
            }
            if (this.currentTask?.id === taskId) {
                this.currentTask = updated;
            }
            return updated;
        },

        async deleteTask(taskId) {
            await axios.delete(`/api/panel/admin/dev-tasks/${taskId}`);
            this.tasks = this.tasks.filter(t => t.id !== taskId);
            if (this.currentTask?.id === taskId) {
                this.currentTask = null;
            }
        },

        async moveTask(taskId, status, position = null) {
            const response = await axios.put(`/api/panel/admin/dev-tasks/${taskId}/move`, {
                status,
                position,
            });
            const updated = response.data.data;
            const index = this.tasks.findIndex(t => t.id === taskId);
            if (index !== -1) {
                this.tasks[index] = updated;
            }
            return updated;
        },

        moveTaskOptimistic(taskId, fromStatus, toStatus, newPosition) {
            const task = this.tasks.find(t => t.id === taskId);
            if (!task) return;

            const oldStatus = task.status;
            const oldPosition = task.position;

            // Update the moved task
            task.status = toStatus;
            task.position = newPosition;

            // Update positions in the source column
            if (oldStatus !== toStatus) {
                this.tasks
                    .filter(t => t.status === oldStatus && t.position > oldPosition)
                    .forEach(t => t.position--);
            }

            // Update positions in the target column
            this.tasks
                .filter(t => t.id !== taskId && t.status === toStatus && t.position >= newPosition)
                .forEach(t => t.position++);
        },

        async reorderTask(taskId, position) {
            const response = await axios.post(`/api/panel/admin/dev-tasks/${taskId}/reorder`, {
                position,
            });
            return response.data.data;
        },

        async triggerBot(taskId) {
            const response = await axios.post(`/api/panel/admin/dev-tasks/${taskId}/trigger-bot`);
            return response.data;
        },

        async generatePlan(taskId) {
            const response = await axios.post(`/api/panel/admin/dev-tasks/${taskId}/generate-plan`);
            if (response.data.success && response.data.task) {
                const index = this.tasks.findIndex(t => t.id === taskId);
                if (index !== -1) {
                    this.tasks[index] = response.data.task;
                }
                if (this.currentTask?.id === taskId) {
                    this.currentTask = response.data.task;
                }
            }
            return response.data;
        },

        async fetchTaskLogs(taskId, params = {}) {
            this.logsLoading = true;
            try {
                const response = await axios.get(`/api/panel/admin/dev-tasks/${taskId}/logs`, { params });
                this.taskLogs = response.data.data;
                return response.data;
            } catch (error) {
                console.error('Failed to fetch task logs:', error);
                throw error;
            } finally {
                this.logsLoading = false;
            }
        },

        async addComment(taskId, content) {
            const response = await axios.post(`/api/panel/admin/dev-tasks/${taskId}/logs`, {
                type: 'comment',
                content,
            });
            const log = response.data.data;
            this.taskLogs.unshift(log);
            return log;
        },

        setCurrentTask(task) {
            this.currentTask = task;
            this.taskLogs = [];
        },

        clearCurrentTask() {
            this.currentTask = null;
            this.taskLogs = [];
        },

        setCurrentUserId(userId) {
            this.currentUserId = userId;
        },

        // === SUBTASKS ===
        async createSubtask(taskId, title) {
            const response = await axios.post(`/api/panel/admin/dev-tasks/${taskId}/subtasks`, { title });
            const subtask = response.data.data;

            // Update task in store
            const task = this.tasks.find(t => t.id === taskId);
            if (task) {
                if (!task.subtasks) task.subtasks = [];
                task.subtasks.push(subtask);
                task.subtask_progress = this.calculateSubtaskProgress(task.subtasks);
            }

            return subtask;
        },

        async toggleSubtask(taskId, subtaskId) {
            const response = await axios.patch(`/api/panel/admin/dev-tasks/${taskId}/subtasks/${subtaskId}/toggle`);
            const updatedSubtask = response.data.data;

            const task = this.tasks.find(t => t.id === taskId);
            if (task?.subtasks) {
                const index = task.subtasks.findIndex(s => s.id === subtaskId);
                if (index !== -1) {
                    task.subtasks[index] = updatedSubtask;
                    task.subtask_progress = this.calculateSubtaskProgress(task.subtasks);
                }
            }

            return updatedSubtask;
        },

        async updateSubtask(taskId, subtaskId, title) {
            const response = await axios.put(`/api/panel/admin/dev-tasks/${taskId}/subtasks/${subtaskId}`, { title });
            const updatedSubtask = response.data.data;

            const task = this.tasks.find(t => t.id === taskId);
            if (task?.subtasks) {
                const index = task.subtasks.findIndex(s => s.id === subtaskId);
                if (index !== -1) {
                    task.subtasks[index] = updatedSubtask;
                }
            }

            return updatedSubtask;
        },

        async deleteSubtask(taskId, subtaskId) {
            await axios.delete(`/api/panel/admin/dev-tasks/${taskId}/subtasks/${subtaskId}`);

            const task = this.tasks.find(t => t.id === taskId);
            if (task?.subtasks) {
                task.subtasks = task.subtasks.filter(s => s.id !== subtaskId);
                task.subtask_progress = this.calculateSubtaskProgress(task.subtasks);
            }
        },

        async reorderSubtasks(taskId, subtaskIds) {
            const response = await axios.post(`/api/panel/admin/dev-tasks/${taskId}/subtasks/reorder`, {
                subtask_ids: subtaskIds,
            });

            const task = this.tasks.find(t => t.id === taskId);
            if (task) {
                task.subtasks = response.data.data;
            }

            return response.data.data;
        },

        calculateSubtaskProgress(subtasks) {
            if (!subtasks?.length) return { total: 0, completed: 0, percentage: 0 };
            const total = subtasks.length;
            const completed = subtasks.filter(s => s.is_completed).length;
            return {
                total,
                completed,
                percentage: Math.round((completed / total) * 100),
            };
        },

        // === ATTACHMENTS ===
        async uploadAttachment(taskId, file) {
            const formData = new FormData();
            formData.append('file', file);

            const response = await axios.post(`/api/panel/admin/dev-tasks/${taskId}/attachments`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            const attachment = response.data.data;
            const task = this.tasks.find(t => t.id === taskId);
            if (task) {
                if (!task.attachments) task.attachments = [];
                task.attachments.push(attachment);
                task.attachments_count = task.attachments.length;
            }

            return attachment;
        },

        async deleteAttachment(taskId, attachmentId) {
            await axios.delete(`/api/panel/admin/dev-tasks/${taskId}/attachments/${attachmentId}`);

            const task = this.tasks.find(t => t.id === taskId);
            if (task?.attachments) {
                task.attachments = task.attachments.filter(a => a.id !== attachmentId);
                task.attachments_count = task.attachments.length;
            }
        },

        // === TIME TRACKING ===
        async fetchActiveTimer() {
            try {
                const response = await axios.get('/api/panel/admin/dev-tasks/time-entries/active');
                this.activeTimer = response.data.data;
                return this.activeTimer;
            } catch (error) {
                console.error('Failed to fetch active timer:', error);
                return null;
            }
        },

        async startTimer(taskId) {
            const response = await axios.post(`/api/panel/admin/dev-tasks/${taskId}/time-entries/start`);
            this.activeTimer = response.data.data;
            return this.activeTimer;
        },

        async stopTimer(entryId) {
            const taskId = this.activeTimer?.task?.id;
            if (!taskId) return;

            const response = await axios.post(`/api/panel/admin/dev-tasks/${taskId}/time-entries/${entryId}/stop`);
            this.activeTimer = null;

            // Update task total time
            const task = this.tasks.find(t => t.id === taskId);
            if (task && response.data.data.duration_minutes) {
                task.total_time_spent = (task.total_time_spent || 0) + response.data.data.duration_minutes;
            }

            return response.data.data;
        },

        async fetchTimeEntries(taskId) {
            const response = await axios.get(`/api/panel/admin/dev-tasks/${taskId}/time-entries`);
            return response.data.data;
        },

        async deleteTimeEntry(taskId, entryId) {
            await axios.delete(`/api/panel/admin/dev-tasks/${taskId}/time-entries/${entryId}`);
        },

        // === SAVED FILTERS ===
        async fetchSavedFilters() {
            try {
                const response = await axios.get('/api/panel/admin/dev-tasks/filters/saved');
                this.savedFilters = response.data.data;
                return this.savedFilters;
            } catch (error) {
                console.error('Failed to fetch saved filters:', error);
                return [];
            }
        },

        async createSavedFilter(data) {
            const response = await axios.post('/api/panel/admin/dev-tasks/filters/saved', data);
            const filter = response.data.data;
            this.savedFilters.push(filter);
            return filter;
        },

        async deleteSavedFilter(filterId) {
            await axios.delete(`/api/panel/admin/dev-tasks/filters/saved/${filterId}`);
            this.savedFilters = this.savedFilters.filter(f => f.id !== filterId);
        },

        applyQuickFilter(filterType, customFilters = null) {
            this.quickFilter = filterType;
            // Custom filters from saved filters can be applied here if needed
        },
    },
});
