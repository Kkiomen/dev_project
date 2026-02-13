import { defineStore } from 'pinia';
import axios from 'axios';

export const useVideoProjectsStore = defineStore('videoProjects', {
    state: () => ({
        projects: [],
        currentProject: null,
        captionStyles: [],
        loading: false,
        uploading: false,
        rendering: false,
        error: null,
        pagination: {
            currentPage: 1,
            lastPage: 1,
            perPage: 20,
            total: 0,
        },
    }),

    getters: {
        getProjectById: (state) => (id) => state.projects.find(p => p.id === id),
        processingProjects: (state) => state.projects.filter(p => p.is_processing),
        completedProjects: (state) => state.projects.filter(p => p.status === 'completed'),
    },

    actions: {
        async fetchProjects(params = {}) {
            this.loading = true;
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
                this.loading = false;
            }
        },

        async fetchProject(publicId) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/v1/video-projects/${publicId}`);
                this.currentProject = response.data.data;
                return this.currentProject;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch project';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async uploadVideo(formData) {
            this.uploading = true;
            this.error = null;
            try {
                const response = await axios.post('/api/v1/video-projects', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                const project = response.data.data;
                this.projects.unshift(project);
                this.currentProject = project;
                return project;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to upload video';
                throw error;
            } finally {
                this.uploading = false;
            }
        },

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

        async renderProject(publicId) {
            this.rendering = true;
            this.error = null;
            try {
                const response = await axios.post(`/api/v1/video-projects/${publicId}/render`);
                if (response.data.project?.data) {
                    this.currentProject = response.data.project.data;
                }
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to start rendering';
                throw error;
            } finally {
                this.rendering = false;
            }
        },

        async fetchCaptionStyles() {
            try {
                const response = await axios.get('/api/v1/video-projects/caption-styles');
                this.captionStyles = response.data.styles || [];
                return this.captionStyles;
            } catch (error) {
                this.captionStyles = [];
            }
        },

        getDownloadUrl(publicId) {
            return `/api/v1/video-projects/${publicId}/download`;
        },
    },
});
