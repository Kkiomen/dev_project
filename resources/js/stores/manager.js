import { defineStore } from 'pinia';
import axios from 'axios';
import { useBrandsStore } from '@/stores/brands';

export const useManagerStore = defineStore('manager', {
    state: () => ({
        // UI state
        sidebarCollapsed: false,
        mobileMenuOpen: false,

        // Counts
        pendingApprovalCount: 0,
        unreadInboxCount: 0,
        activeAlerts: [],

        // Accounts
        accounts: [],
        accountsLoading: false,
        accountsError: null,

        // Brand Kit
        brandKit: null,
        brandKitLoading: false,
        brandKitError: null,

        // Design Templates
        designTemplates: [],
        designTemplatesLoading: false,

        // Generated Assets
        generatedAssets: [],
        generatedAssetsLoading: false,

        // Content Templates
        contentTemplates: [],
        contentTemplatesLoading: false,

        // Strategy
        strategy: null,
        strategyLoading: false,

        // Content Plan
        currentPlan: null,
        contentPlans: [],
        contentPlansLoading: false,

        // Scheduled Posts (Publishing)
        scheduledPosts: [],
        scheduledPostsMeta: null,
        scheduledPostsLoading: false,

        // Analytics
        analyticsDashboard: null,
        analyticsSnapshots: [],
        postAnalytics: [],
        performanceScores: [],
        analyticsLoading: false,

        // Weekly Reports
        weeklyReports: [],
        weeklyReportsLoading: false,

        // Comments (Engagement)
        comments: [],
        commentsMeta: null,
        commentsLoading: false,

        // Messages (Engagement)
        messages: [],
        messagesMeta: null,
        messagesLoading: false,

        // Auto Reply Rules
        autoReplyRules: [],
        autoReplyRulesLoading: false,

        // Crisis Alerts
        crisisAlerts: [],
        crisisAlertsLoading: false,

        // Monitored Keywords (Listening)
        monitoredKeywords: [],
        monitoredKeywordsLoading: false,

        // Mentions (Listening)
        mentions: [],
        mentionsMeta: null,
        mentionsLoading: false,

        // Alert Rules (Listening)
        alertRules: [],
        alertRulesLoading: false,

        // Listening Reports
        listeningReports: [],
        listeningReportsLoading: false,
    }),

    getters: {
        hasPendingApprovals: (state) => state.pendingApprovalCount > 0,
        hasUnreadMessages: (state) => state.unreadInboxCount > 0,
        hasActiveAlerts: (state) => state.activeAlerts.length > 0,

        connectedAccounts: (state) => state.accounts.filter(a => a.is_connected),
        disconnectedPlatforms: (state) => {
            const connected = state.accounts.map(a => a.platform);
            const all = ['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube'];
            return all.filter(p => !connected.includes(p));
        },

        getAccountByPlatform: (state) => (platform) => {
            return state.accounts.find(a => a.platform === platform);
        },

        currentBrandId() {
            const brandsStore = useBrandsStore();
            return brandsStore.currentBrand?.id;
        },

        strategyIsActive: (state) => state.strategy?.status === 'active',
        currentPlanCompletion: (state) => state.currentPlan?.completion_percentage ?? 0,

        designTemplatesByType: (state) => (type) => {
            return state.designTemplates.filter(t => t.type === type);
        },

        contentTemplatesByCategory: (state) => (category) => {
            return state.contentTemplates.filter(t => t.category === category);
        },

        pendingScheduledPosts: (state) => state.scheduledPosts.filter(p => p.approval_status === 'pending'),
        approvedScheduledPosts: (state) => state.scheduledPosts.filter(p => p.approval_status === 'approved'),

        unrepliedComments: (state) => state.comments.filter(c => !c.is_replied),
        negativeComments: (state) => state.comments.filter(c => c.sentiment === 'negative'),
        unreadMessages: (state) => state.messages.filter(m => !m.is_read),

        activeKeywords: (state) => state.monitoredKeywords.filter(k => k.is_active),
        activeAlertRules: (state) => state.alertRules.filter(r => r.is_active),
        unresolvedCrisisAlerts: (state) => state.crisisAlerts.filter(a => !a.is_resolved),
    },

    actions: {
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
        },

        setPendingApprovalCount(count) {
            this.pendingApprovalCount = count;
        },

        setUnreadInboxCount(count) {
            this.unreadInboxCount = count;
        },

        // === Accounts ===
        async fetchAccounts() {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            this.accountsLoading = true;
            this.accountsError = null;

            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-accounts`);
                this.accounts = response.data.data;
            } catch (error) {
                this.accountsError = error.response?.data?.message || 'Failed to fetch accounts';
            } finally {
                this.accountsLoading = false;
            }
        },

        async connectAccount(platform, data = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-accounts`, {
                    platform,
                    ...data,
                });
                const account = response.data.data;
                const idx = this.accounts.findIndex(a => a.platform === platform);
                if (idx >= 0) {
                    this.accounts[idx] = account;
                } else {
                    this.accounts.push(account);
                }
                return account;
            } catch (error) {
                throw error;
            }
        },

        async disconnectAccount(platform) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                await axios.delete(`/api/v1/brands/${brandId}/sm-accounts/${platform}`);
                const idx = this.accounts.findIndex(a => a.platform === platform);
                if (idx >= 0) {
                    this.accounts.splice(idx, 1);
                }
            } catch (error) {
                throw error;
            }
        },

        async getAuthUrl(platform) {
            const brandId = this.currentBrandId;
            if (!brandId) return null;

            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-accounts/${platform}/auth-url`);
                return response.data.auth_url;
            } catch (error) {
                throw error;
            }
        },

        // === Brand Kit ===
        async fetchBrandKit() {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            this.brandKitLoading = true;
            this.brandKitError = null;

            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-brand-kit`);
                this.brandKit = response.data.data;
            } catch (error) {
                this.brandKitError = error.response?.data?.message || 'Failed to fetch brand kit';
            } finally {
                this.brandKitLoading = false;
            }
        },

        async updateBrandKit(data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.put(`/api/v1/brands/${brandId}/sm-brand-kit`, data);
                this.brandKit = response.data.data;
                return this.brandKit;
            } catch (error) {
                throw error;
            }
        },

        async uploadLogo(file, variant = 'light') {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            const formData = new FormData();
            formData.append('logo', file);
            formData.append('variant', variant);

            try {
                const response = await axios.post(
                    `/api/v1/brands/${brandId}/sm-brand-kit/logo`,
                    formData,
                    { headers: { 'Content-Type': 'multipart/form-data' } }
                );
                this.brandKit = response.data.data;
                return this.brandKit;
            } catch (error) {
                throw error;
            }
        },

        async deleteLogo(variant = 'light') {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.delete(`/api/v1/brands/${brandId}/sm-brand-kit/logo`, {
                    data: { variant },
                });
                this.brandKit = response.data.data;
                return this.brandKit;
            } catch (error) {
                throw error;
            }
        },

        // === Design Templates ===
        async fetchDesignTemplates(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            this.designTemplatesLoading = true;

            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-design-templates`, { params });
                this.designTemplates = response.data.data;
            } catch (error) {
                throw error;
            } finally {
                this.designTemplatesLoading = false;
            }
        },

        async createDesignTemplate(data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-design-templates`, data);
                this.designTemplates.push(response.data.data);
                return response.data.data;
            } catch (error) {
                throw error;
            }
        },

        async updateDesignTemplate(templateId, data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.put(`/api/v1/brands/${brandId}/sm-design-templates/${templateId}`, data);
                const idx = this.designTemplates.findIndex(t => t.id === templateId);
                if (idx >= 0) this.designTemplates[idx] = response.data.data;
                return response.data.data;
            } catch (error) {
                throw error;
            }
        },

        async deleteDesignTemplate(templateId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                await axios.delete(`/api/v1/brands/${brandId}/sm-design-templates/${templateId}`);
                this.designTemplates = this.designTemplates.filter(t => t.id !== templateId);
            } catch (error) {
                throw error;
            }
        },

        // === Generated Assets ===
        async fetchGeneratedAssets(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            this.generatedAssetsLoading = true;

            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-generated-assets`, { params });
                this.generatedAssets = response.data.data;
            } catch (error) {
                throw error;
            } finally {
                this.generatedAssetsLoading = false;
            }
        },

        async generateAsset(data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-generated-assets`, data);
                this.generatedAssets.unshift(response.data.data);
                return response.data.data;
            } catch (error) {
                throw error;
            }
        },

        async deleteGeneratedAsset(assetId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                await axios.delete(`/api/v1/brands/${brandId}/sm-generated-assets/${assetId}`);
                this.generatedAssets = this.generatedAssets.filter(a => a.id !== assetId);
            } catch (error) {
                throw error;
            }
        },

        // === Content Templates ===
        async fetchContentTemplates(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            this.contentTemplatesLoading = true;

            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-content-templates`, { params });
                this.contentTemplates = response.data.data;
            } catch (error) {
                throw error;
            } finally {
                this.contentTemplatesLoading = false;
            }
        },

        async createContentTemplate(data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-content-templates`, data);
                this.contentTemplates.push(response.data.data);
                return response.data.data;
            } catch (error) {
                throw error;
            }
        },

        async updateContentTemplate(templateId, data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.put(`/api/v1/brands/${brandId}/sm-content-templates/${templateId}`, data);
                const idx = this.contentTemplates.findIndex(t => t.id === templateId);
                if (idx >= 0) this.contentTemplates[idx] = response.data.data;
                return response.data.data;
            } catch (error) {
                throw error;
            }
        },

        async deleteContentTemplate(templateId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                await axios.delete(`/api/v1/brands/${brandId}/sm-content-templates/${templateId}`);
                this.contentTemplates = this.contentTemplates.filter(t => t.id !== templateId);
            } catch (error) {
                throw error;
            }
        },

        // === Strategy ===
        async fetchStrategy() {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            this.strategyLoading = true;

            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-strategy`);
                this.strategy = response.data.data;
            } catch (error) {
                throw error;
            } finally {
                this.strategyLoading = false;
            }
        },

        async updateStrategy(data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.put(`/api/v1/brands/${brandId}/sm-strategy`, data);
                this.strategy = response.data.data;
                return this.strategy;
            } catch (error) {
                throw error;
            }
        },

        async activateStrategy() {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-strategy/activate`);
                this.strategy = response.data.data;
                return this.strategy;
            } catch (error) {
                throw error;
            }
        },

        // === Content Plans ===
        async fetchContentPlans(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            this.contentPlansLoading = true;

            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-content-plans`, { params });
                this.contentPlans = response.data.data;
            } catch (error) {
                throw error;
            } finally {
                this.contentPlansLoading = false;
            }
        },

        async fetchCurrentPlan() {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            this.contentPlansLoading = true;

            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-content-plans/current`);
                this.currentPlan = response.data.data;
            } catch (error) {
                throw error;
            } finally {
                this.contentPlansLoading = false;
            }
        },

        async fetchPlanDetails(planId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-content-plans/${planId}`);
                return response.data.data;
            } catch (error) {
                throw error;
            }
        },

        async addPlanSlot(planId, data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-content-plans/${planId}/slots`, data);
                if (this.currentPlan?.id === planId) {
                    this.currentPlan.slots = [...(this.currentPlan.slots || []), response.data.data];
                    this.currentPlan.total_slots++;
                }
                return response.data.data;
            } catch (error) {
                throw error;
            }
        },

        async updatePlanSlot(planId, slotId, data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.put(`/api/v1/brands/${brandId}/sm-content-plans/${planId}/slots/${slotId}`, data);
                if (this.currentPlan?.id === planId && this.currentPlan.slots) {
                    const idx = this.currentPlan.slots.findIndex(s => s.id === slotId);
                    if (idx >= 0) this.currentPlan.slots[idx] = response.data.data;
                }
                return response.data.data;
            } catch (error) {
                throw error;
            }
        },

        async removePlanSlot(planId, slotId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                await axios.delete(`/api/v1/brands/${brandId}/sm-content-plans/${planId}/slots/${slotId}`);
                if (this.currentPlan?.id === planId && this.currentPlan.slots) {
                    this.currentPlan.slots = this.currentPlan.slots.filter(s => s.id !== slotId);
                    this.currentPlan.total_slots = Math.max(0, this.currentPlan.total_slots - 1);
                }
            } catch (error) {
                throw error;
            }
        },

        async generateSlotContent(planId, slotId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-content-plans/${planId}/slots/${slotId}/generate-content`);
                return response.data;
            } catch (error) {
                throw error;
            }
        },

        async generateAllContent(planId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;

            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-content-plans/${planId}/generate-all-content`);
                return response.data;
            } catch (error) {
                throw error;
            }
        },

        // === Scheduled Posts ===
        async fetchScheduledPosts(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.scheduledPostsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-scheduled-posts`, { params });
                this.scheduledPosts = response.data.data;
                this.scheduledPostsMeta = response.data.meta;
            } catch (error) { throw error; }
            finally { this.scheduledPostsLoading = false; }
        },

        async approveScheduledPost(postId, notes = null) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-scheduled-posts/${postId}/approve`, { approval_notes: notes });
                const idx = this.scheduledPosts.findIndex(p => p.id === postId);
                if (idx >= 0) this.scheduledPosts[idx] = response.data.data;
                this.pendingApprovalCount = Math.max(0, this.pendingApprovalCount - 1);
                return response.data.data;
            } catch (error) { throw error; }
        },

        async rejectScheduledPost(postId, notes) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-scheduled-posts/${postId}/reject`, { approval_notes: notes });
                const idx = this.scheduledPosts.findIndex(p => p.id === postId);
                if (idx >= 0) this.scheduledPosts[idx] = response.data.data;
                this.pendingApprovalCount = Math.max(0, this.pendingApprovalCount - 1);
                return response.data.data;
            } catch (error) { throw error; }
        },

        async deleteScheduledPost(postId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                await axios.delete(`/api/v1/brands/${brandId}/sm-scheduled-posts/${postId}`);
                this.scheduledPosts = this.scheduledPosts.filter(p => p.id !== postId);
            } catch (error) { throw error; }
        },

        // === Analytics ===
        async fetchAnalyticsDashboard() {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.analyticsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-analytics/dashboard`);
                this.analyticsDashboard = response.data;
                this.pendingApprovalCount = response.data.pending_approval_count ?? 0;
            } catch (error) { throw error; }
            finally { this.analyticsLoading = false; }
        },

        async fetchAnalyticsSnapshots(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.analyticsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-analytics/snapshots`, { params });
                this.analyticsSnapshots = response.data.data;
            } catch (error) { throw error; }
            finally { this.analyticsLoading = false; }
        },

        async fetchPostAnalytics(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-analytics/post-analytics`, { params });
                this.postAnalytics = response.data.data;
            } catch (error) { throw error; }
        },

        async fetchPerformanceScores(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-analytics/performance-scores`, { params });
                this.performanceScores = response.data.data;
            } catch (error) { throw error; }
        },

        // === Weekly Reports ===
        async fetchWeeklyReports(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.weeklyReportsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-analytics/weekly-reports`, { params });
                this.weeklyReports = response.data.data;
            } catch (error) { throw error; }
            finally { this.weeklyReportsLoading = false; }
        },

        async fetchWeeklyReport(reportId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-analytics/weekly-reports/${reportId}`);
                return response.data.data;
            } catch (error) { throw error; }
        },

        // === Comments ===
        async fetchComments(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.commentsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-comments`, { params });
                this.comments = response.data.data;
                this.commentsMeta = response.data.meta;
            } catch (error) { throw error; }
            finally { this.commentsLoading = false; }
        },

        async replyToComment(commentId, replyText) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-comments/${commentId}/reply`, { reply_text: replyText });
                const idx = this.comments.findIndex(c => c.id === commentId);
                if (idx >= 0) this.comments[idx] = response.data.data;
                return response.data.data;
            } catch (error) { throw error; }
        },

        async hideComment(commentId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-comments/${commentId}/hide`);
                const idx = this.comments.findIndex(c => c.id === commentId);
                if (idx >= 0) this.comments[idx] = response.data.data;
                return response.data.data;
            } catch (error) { throw error; }
        },

        async flagComment(commentId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-comments/${commentId}/flag`);
                const idx = this.comments.findIndex(c => c.id === commentId);
                if (idx >= 0) this.comments[idx] = response.data.data;
                return response.data.data;
            } catch (error) { throw error; }
        },

        // === Messages ===
        async fetchMessages(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.messagesLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-messages`, { params });
                this.messages = response.data.data;
                this.messagesMeta = response.data.meta;
            } catch (error) { throw error; }
            finally { this.messagesLoading = false; }
        },

        async markMessageAsRead(messageId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-messages/${messageId}/read`);
                const idx = this.messages.findIndex(m => m.id === messageId);
                if (idx >= 0) this.messages[idx] = response.data.data;
                this.unreadInboxCount = Math.max(0, this.unreadInboxCount - 1);
                return response.data.data;
            } catch (error) { throw error; }
        },

        // === Auto Reply Rules ===
        async fetchAutoReplyRules() {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.autoReplyRulesLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-auto-reply-rules`);
                this.autoReplyRules = response.data.data;
            } catch (error) { throw error; }
            finally { this.autoReplyRulesLoading = false; }
        },

        async createAutoReplyRule(data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-auto-reply-rules`, data);
                this.autoReplyRules.push(response.data.data);
                return response.data.data;
            } catch (error) { throw error; }
        },

        async updateAutoReplyRule(ruleId, data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.put(`/api/v1/brands/${brandId}/sm-auto-reply-rules/${ruleId}`, data);
                const idx = this.autoReplyRules.findIndex(r => r.id === ruleId);
                if (idx >= 0) this.autoReplyRules[idx] = response.data.data;
                return response.data.data;
            } catch (error) { throw error; }
        },

        async deleteAutoReplyRule(ruleId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                await axios.delete(`/api/v1/brands/${brandId}/sm-auto-reply-rules/${ruleId}`);
                this.autoReplyRules = this.autoReplyRules.filter(r => r.id !== ruleId);
            } catch (error) { throw error; }
        },

        // === Crisis Alerts ===
        async fetchCrisisAlerts(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.crisisAlertsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-crisis-alerts`, { params });
                this.crisisAlerts = response.data.data;
            } catch (error) { throw error; }
            finally { this.crisisAlertsLoading = false; }
        },

        async resolveCrisisAlert(alertId, notes = null) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-crisis-alerts/${alertId}/resolve`, { resolution_notes: notes });
                const idx = this.crisisAlerts.findIndex(a => a.id === alertId);
                if (idx >= 0) this.crisisAlerts[idx] = response.data.data;
                return response.data.data;
            } catch (error) { throw error; }
        },

        // === Monitored Keywords ===
        async fetchMonitoredKeywords(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.monitoredKeywordsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-keywords`, { params });
                this.monitoredKeywords = response.data.data;
            } catch (error) { throw error; }
            finally { this.monitoredKeywordsLoading = false; }
        },

        async createMonitoredKeyword(data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-keywords`, data);
                this.monitoredKeywords.push(response.data.data);
                return response.data.data;
            } catch (error) { throw error; }
        },

        async updateMonitoredKeyword(keywordId, data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.put(`/api/v1/brands/${brandId}/sm-keywords/${keywordId}`, data);
                const idx = this.monitoredKeywords.findIndex(k => k.id === keywordId);
                if (idx >= 0) this.monitoredKeywords[idx] = response.data.data;
                return response.data.data;
            } catch (error) { throw error; }
        },

        async deleteMonitoredKeyword(keywordId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                await axios.delete(`/api/v1/brands/${brandId}/sm-keywords/${keywordId}`);
                this.monitoredKeywords = this.monitoredKeywords.filter(k => k.id !== keywordId);
            } catch (error) { throw error; }
        },

        // === Mentions ===
        async fetchMentions(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.mentionsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-listening/mentions`, { params });
                this.mentions = response.data.data;
                this.mentionsMeta = response.data.meta;
            } catch (error) { throw error; }
            finally { this.mentionsLoading = false; }
        },

        // === Alert Rules ===
        async fetchAlertRules() {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.alertRulesLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-listening/alert-rules`);
                this.alertRules = response.data.data;
            } catch (error) { throw error; }
            finally { this.alertRulesLoading = false; }
        },

        async createAlertRule(data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-listening/alert-rules`, data);
                this.alertRules.push(response.data.data);
                return response.data.data;
            } catch (error) { throw error; }
        },

        async updateAlertRule(ruleId, data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.put(`/api/v1/brands/${brandId}/sm-listening/alert-rules/${ruleId}`, data);
                const idx = this.alertRules.findIndex(r => r.id === ruleId);
                if (idx >= 0) this.alertRules[idx] = response.data.data;
                return response.data.data;
            } catch (error) { throw error; }
        },

        async deleteAlertRule(ruleId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                await axios.delete(`/api/v1/brands/${brandId}/sm-listening/alert-rules/${ruleId}`);
                this.alertRules = this.alertRules.filter(r => r.id !== ruleId);
            } catch (error) { throw error; }
        },

        // === Listening Reports ===
        async fetchListeningReports(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.listeningReportsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-listening/reports`, { params });
                this.listeningReports = response.data.data;
            } catch (error) { throw error; }
            finally { this.listeningReportsLoading = false; }
        },

        async fetchListeningReport(reportId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-listening/reports/${reportId}`);
                return response.data.data;
            } catch (error) { throw error; }
        },
    },
});
