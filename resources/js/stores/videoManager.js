import { defineStore } from 'pinia';
import axios from 'axios';
import { useBrandsStore } from '@/stores/brands';

export const useVideoManagerStore = defineStore('videoManager', {
    state: () => ({
        // UI
        sidebarCollapsed: false,
        mobileMenuOpen: false,

        // Dashboard stats
        stats: null,
        statsLoading: false,

        // Library
        projects: [],
        projectsLoading: false,
        pagination: {
            currentPage: 1,
            lastPage: 1,
            perPage: 20,
            total: 0,
        },

        // Editor
        currentProject: null,
        projectLoading: false,

        // Caption styles
        captionStyles: [],

        // Upload
        uploadQueue: [],
        uploading: false,

        // Bulk actions
        selectedIds: [],

        // Settings
        settings: {
            defaultCaptionStyle: 'clean',
            defaultLanguage: null,
            defaultPosition: 'bottom',
            autoTranscribe: true,
            dictionary: '',
        },
        settingsLoading: false,

        // Service health
        health: {
            transcriber: false,
            video_editor: false,
        },

        // Error
        error: null,
    }),

    getters: {
        processingCount: (state) => state.stats?.processing_count ?? 0,
        completedToday: (state) => state.stats?.completed_today ?? 0,

        processingProjects: (state) => state.projects.filter(p => p.is_processing),

        currentBrandId() {
            const brandsStore = useBrandsStore();
            return brandsStore.currentBrand?.id;
        },

        isHealthy: (state) => state.health.transcriber && state.health.video_editor,
    },

    actions: {
        // === UI ===
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
        },

        // === Dashboard ===
        async fetchStats(params = {}) {
            this.statsLoading = true;
            try {
                const response = await axios.get('/api/v1/video-projects/stats', { params });
                this.stats = response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch stats';
            } finally {
                this.statsLoading = false;
            }
        },

        async fetchHealth() {
            try {
                const response = await axios.get('/api/v1/video-projects/health');
                this.health = response.data;
            } catch {
                this.health = { transcriber: false, video_editor: false };
            }
        },

        // === Library ===
        async fetchProjects(params = {}) {
            this.projectsLoading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/video-projects', { params });
                this.projects = response.data.data;
                if (response.data.meta) {
                    this.pagination = {
                        currentPage: response.data.meta.current_page,
                        lastPage: response.data.meta.last_page,
                        perPage: response.data.meta.per_page,
                        total: response.data.meta.total,
                    };
                }
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch projects';
                throw error;
            } finally {
                this.projectsLoading = false;
            }
        },

        async fetchProject(publicId) {
            this.projectLoading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/v1/video-projects/${publicId}`);
                this.currentProject = response.data.data;
                return this.currentProject;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch project';
                throw error;
            } finally {
                this.projectLoading = false;
            }
        },

        async deleteProject(publicId) {
            this.error = null;
            try {
                await axios.delete(`/api/v1/video-projects/${publicId}`);
                this.projects = this.projects.filter(p => p.id !== publicId);
                if (this.currentProject?.id === publicId) {
                    this.currentProject = null;
                }
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to delete project';
                throw error;
            }
        },

        async bulkDelete(ids) {
            this.error = null;
            try {
                const response = await axios.post('/api/v1/video-projects/bulk-delete', { ids });
                this.projects = this.projects.filter(p => !ids.includes(p.id));
                this.selectedIds = [];
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to bulk delete';
                throw error;
            }
        },

        async bulkRender(ids) {
            this.error = null;
            try {
                const response = await axios.post('/api/v1/video-projects/bulk-render', { ids });
                this.selectedIds = [];
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to bulk render';
                throw error;
            }
        },

        // === Upload ===
        async uploadVideo(formData) {
            this.uploading = true;
            this.error = null;
            try {
                const response = await axios.post('/api/v1/video-projects', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                const project = response.data.data;
                this.projects.unshift(project);
                return project;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to upload video';
                throw error;
            } finally {
                this.uploading = false;
            }
        },

        addToUploadQueue(files) {
            const items = Array.from(files).map(file => ({
                id: crypto.randomUUID(),
                file,
                name: file.name,
                size: file.size,
                progress: 0,
                status: 'pending',
                title: file.name.replace(/\.[^/.]+$/, ''),
                language: this.settings.defaultLanguage,
                captionStyle: this.settings.defaultCaptionStyle,
                error: null,
            }));
            this.uploadQueue.push(...items);
        },

        removeFromUploadQueue(id) {
            this.uploadQueue = this.uploadQueue.filter(item => item.id !== id);
        },

        clearUploadQueue() {
            this.uploadQueue = this.uploadQueue.filter(item => item.status === 'uploading');
        },

        async processUploadQueue(brandId = null) {
            this.uploading = true;
            const pending = this.uploadQueue.filter(item => item.status === 'pending');

            for (const item of pending) {
                item.status = 'uploading';
                try {
                    const formData = new FormData();
                    formData.append('video', item.file);
                    formData.append('title', item.title);
                    if (item.language) formData.append('language', item.language);
                    formData.append('caption_style', item.captionStyle);
                    if (brandId) formData.append('brand_id', brandId);

                    const response = await axios.post('/api/v1/video-projects', formData, {
                        headers: { 'Content-Type': 'multipart/form-data' },
                        onUploadProgress: (e) => {
                            item.progress = Math.round((e.loaded * 100) / e.total);
                        },
                    });

                    item.status = 'completed';
                    this.projects.unshift(response.data.data);
                } catch (error) {
                    item.status = 'failed';
                    item.error = error.response?.data?.message || 'Upload failed';
                }
            }

            this.uploading = false;
        },

        // === Editor ===
        async updateProject(publicId, data) {
            this.error = null;
            try {
                const response = await axios.put(`/api/v1/video-projects/${publicId}`, data);
                const updated = response.data.data;
                this.currentProject = updated;

                const index = this.projects.findIndex(p => p.id === publicId);
                if (index !== -1) {
                    this.projects[index] = updated;
                }

                return updated;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to update project';
                throw error;
            }
        },

        async renderProject(publicId) {
            this.error = null;
            try {
                const response = await axios.post(`/api/v1/video-projects/${publicId}/render`);
                if (response.data.project) {
                    this.currentProject = response.data.project;
                }
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to start rendering';
                throw error;
            }
        },

        async removeSilence(publicId, options = {}) {
            this.error = null;
            try {
                const response = await axios.post(`/api/v1/video-projects/${publicId}/remove-silence`, options);
                if (response.data.project) {
                    this.currentProject = response.data.project;
                }
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to start silence removal';
                throw error;
            }
        },

        // === Caption Styles ===
        async fetchCaptionStyles() {
            try {
                const response = await axios.get('/api/v1/video-projects/caption-styles');
                this.captionStyles = response.data.styles || [];
                return this.captionStyles;
            } catch {
                this.captionStyles = [];
            }
        },

        // === Helpers ===
        getDownloadUrl(publicId) {
            return `/api/v1/video-projects/${publicId}/download`;
        },

        toggleSelection(projectId) {
            const idx = this.selectedIds.indexOf(projectId);
            if (idx === -1) {
                this.selectedIds.push(projectId);
            } else {
                this.selectedIds.splice(idx, 1);
            }
        },

        selectAll() {
            this.selectedIds = this.projects.map(p => p.id);
        },

        clearSelection() {
            this.selectedIds = [];
        },
    },
});
