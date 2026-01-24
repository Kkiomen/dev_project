import { defineStore } from 'pinia';
import axios from 'axios';

export const usePostsStore = defineStore('posts', {
    state: () => ({
        posts: [],
        currentPost: null,
        calendarPosts: {},
        loading: false,
        saving: false,
        generatingAi: false,
        error: null,
        pagination: {
            currentPage: 1,
            lastPage: 1,
            perPage: 20,
            total: 0,
        },
    }),

    getters: {
        getPostById: (state) => (id) => state.posts.find(p => p.id === id),
        getPostsByDate: (state) => (date) => state.calendarPosts[date] || [],
        draftPosts: (state) => state.posts.filter(p => p.status === 'draft'),
        pendingPosts: (state) => state.posts.filter(p => p.status === 'pending_approval'),
        scheduledPosts: (state) => state.posts.filter(p => ['approved', 'scheduled'].includes(p.status)),
    },

    actions: {
        async fetchPosts(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/posts', { params });
                this.posts = response.data.data;
                if (response.data.meta) {
                    this.pagination = {
                        currentPage: response.data.meta.current_page,
                        lastPage: response.data.meta.last_page,
                        perPage: response.data.meta.per_page,
                        total: response.data.meta.total,
                    };
                }
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch posts';
            } finally {
                this.loading = false;
            }
        },

        async fetchCalendarPosts(start, end) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/posts/calendar', {
                    params: { start, end },
                });

                // Group posts by date
                const grouped = {};
                response.data.data.forEach(post => {
                    if (post.scheduled_date) {
                        if (!grouped[post.scheduled_date]) {
                            grouped[post.scheduled_date] = [];
                        }
                        grouped[post.scheduled_date].push(post);
                    }
                });

                this.calendarPosts = grouped;
                return grouped;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch calendar posts';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchPost(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/v1/posts/${id}`);
                this.currentPost = response.data.data;
                return this.currentPost;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch post';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createPost(data) {
            this.saving = true;
            try {
                const response = await axios.post('/api/v1/posts', data);
                const newPost = response.data.data;
                this.posts.unshift(newPost);
                this.currentPost = newPost;
                return newPost;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async updatePost(id, data) {
            this.saving = true;
            try {
                const response = await axios.put(`/api/v1/posts/${id}`, data);
                const updatedPost = response.data.data;

                const index = this.posts.findIndex(p => p.id === id);
                if (index !== -1) {
                    this.posts[index] = updatedPost;
                }

                if (this.currentPost?.id === id) {
                    this.currentPost = updatedPost;
                }

                return updatedPost;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async deletePost(id) {
            try {
                await axios.delete(`/api/v1/posts/${id}`);
                this.posts = this.posts.filter(p => p.id !== id);

                // Remove from calendar
                Object.keys(this.calendarPosts).forEach(date => {
                    this.calendarPosts[date] = this.calendarPosts[date].filter(p => p.id !== id);
                });

                if (this.currentPost?.id === id) {
                    this.currentPost = null;
                }
            } catch (error) {
                throw error;
            }
        },

        async reschedulePost(id, scheduledAt) {
            this.saving = true;
            try {
                const response = await axios.post(`/api/v1/posts/${id}/reschedule`, {
                    scheduled_at: scheduledAt,
                });
                const updatedPost = response.data.data;

                const index = this.posts.findIndex(p => p.id === id);
                if (index !== -1) {
                    this.posts[index] = updatedPost;
                }

                return updatedPost;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async duplicatePost(id) {
            try {
                const response = await axios.post(`/api/v1/posts/${id}/duplicate`);
                const newPost = response.data.data;
                this.posts.unshift(newPost);
                return newPost;
            } catch (error) {
                throw error;
            }
        },

        async requestApproval(id, tokenId) {
            try {
                const response = await axios.post(`/api/v1/posts/${id}/request-approval`, {
                    token_id: tokenId,
                });

                // Update post status locally
                const index = this.posts.findIndex(p => p.id === id);
                if (index !== -1) {
                    this.posts[index].status = 'pending_approval';
                }

                return response.data;
            } catch (error) {
                throw error;
            }
        },

        // Platform posts
        async updatePlatformPost(postId, platform, data) {
            this.saving = true;
            try {
                const response = await axios.put(`/api/v1/posts/${postId}/platforms/${platform}`, data);

                // Update local state
                if (this.currentPost?.id === postId) {
                    const platformIndex = this.currentPost.platform_posts.findIndex(
                        p => p.platform === platform
                    );
                    if (platformIndex !== -1) {
                        this.currentPost.platform_posts[platformIndex] = response.data.data;
                    }
                }

                return response.data.data;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async syncPlatformPost(postId, platform) {
            try {
                const response = await axios.post(`/api/v1/posts/${postId}/platforms/${platform}/sync`);

                if (this.currentPost?.id === postId) {
                    const platformIndex = this.currentPost.platform_posts.findIndex(
                        p => p.platform === platform
                    );
                    if (platformIndex !== -1) {
                        this.currentPost.platform_posts[platformIndex] = response.data.data;
                    }
                }

                return response.data.data;
            } catch (error) {
                throw error;
            }
        },

        async togglePlatform(postId, platform) {
            try {
                const response = await axios.post(`/api/v1/posts/${postId}/platforms/${platform}/toggle`);

                if (this.currentPost?.id === postId) {
                    const platformIndex = this.currentPost.platform_posts.findIndex(
                        p => p.platform === platform
                    );
                    if (platformIndex !== -1) {
                        this.currentPost.platform_posts[platformIndex].enabled = response.data.enabled;
                    }
                }

                return response.data;
            } catch (error) {
                throw error;
            }
        },

        // Media
        async uploadMedia(postId, file, onProgress = null) {
            const formData = new FormData();
            formData.append('file', file);

            try {
                const response = await axios.post(`/api/v1/posts/${postId}/media`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                    onUploadProgress: onProgress ? (e) => onProgress(Math.round((e.loaded * 100) / e.total)) : undefined,
                });

                if (this.currentPost?.id === postId) {
                    this.currentPost.media.push(response.data.data);
                }

                return response.data.data;
            } catch (error) {
                throw error;
            }
        },

        async deleteMedia(mediaId) {
            try {
                await axios.delete(`/api/v1/media/${mediaId}`);

                if (this.currentPost) {
                    this.currentPost.media = this.currentPost.media.filter(m => m.id !== mediaId);
                }
            } catch (error) {
                throw error;
            }
        },

        async reorderMedia(postId, mediaIds) {
            try {
                const response = await axios.post(`/api/v1/posts/${postId}/media/reorder`, {
                    media_ids: mediaIds,
                });

                if (this.currentPost?.id === postId) {
                    this.currentPost.media = response.data.data;
                }

                return response.data.data;
            } catch (error) {
                throw error;
            }
        },

        clearCurrentPost() {
            this.currentPost = null;
        },

        async generateWithAi(config) {
            this.generatingAi = true;
            try {
                const response = await axios.post('/api/v1/posts/ai/generate', config);
                return response.data.data;
            } finally {
                this.generatingAi = false;
            }
        },

        async publishPost(id, platform) {
            this.saving = true;
            try {
                const response = await axios.post(`/api/v1/posts/${id}/publish`, {
                    platform,
                });

                const updatedPost = response.data.data;

                const index = this.posts.findIndex(p => p.id === id);
                if (index !== -1) {
                    this.posts[index] = updatedPost;
                }

                if (this.currentPost?.id === id) {
                    this.currentPost = updatedPost;
                }

                return response.data;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        reset() {
            this.posts = [];
            this.currentPost = null;
            this.calendarPosts = {};
            this.loading = false;
            this.saving = false;
            this.generatingAi = false;
            this.error = null;
        },
    },
});
