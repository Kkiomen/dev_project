import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes = [
    {
        path: '/',
        redirect: '/dashboard',
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('@/pages/DashboardPage.vue'),
    },
    // Onboarding (after registration)
    {
        path: '/onboarding',
        name: 'onboarding',
        component: () => import('@/pages/OnboardingPage.vue'),
        meta: { hideLayout: true, onboarding: true },
    },
    {
        path: '/data',
        name: 'data',
        component: () => import('@/pages/DataPage.vue'),
    },
    {
        path: '/bases/:baseId',
        name: 'base',
        component: () => import('@/pages/BaseView.vue'),
        props: true,
    },
    {
        path: '/tables/:tableId',
        name: 'table.grid',
        component: () => import('@/pages/TableGridView.vue'),
        props: true,
    },
    {
        path: '/tables/:tableId/kanban',
        name: 'table.kanban',
        component: () => import('@/pages/TableKanbanView.vue'),
        props: true,
    },
    {
        path: '/docs',
        redirect: '/docs/overview',
    },
    {
        path: '/docs/:section',
        name: 'docs',
        component: () => import('@/pages/DocsPage.vue'),
        props: true,
    },
    {
        path: '/templates',
        name: 'templates',
        component: () => import('@/pages/TemplatesPage.vue'),
    },
    {
        path: '/templates/:templateId/edit',
        name: 'template.editor',
        component: () => import('@/pages/GraphicsEditorPage.vue'),
        props: true,
    },
    // RSS Feeds
    {
        path: '/rss-feeds/today',
        name: 'rss-feeds-today',
        component: () => import('@/pages/TodayArticlesPage.vue'),
    },
    {
        path: '/rss-feeds',
        name: 'rss-feeds',
        component: () => import('@/pages/RssFeedsPage.vue'),
    },
    // Boards (Kanban)
    {
        path: '/boards',
        name: 'boards',
        component: () => import('@/pages/BoardsPage.vue'),
    },
    {
        path: '/boards/:boardId',
        name: 'board.view',
        component: () => import('@/pages/BoardViewPage.vue'),
        props: true,
    },
    // Calendar & Social Posts
    {
        path: '/calendar',
        name: 'calendar',
        component: () => import('@/pages/CalendarPage.vue'),
    },
    {
        path: '/posts/new',
        name: 'post.create',
        component: () => import('@/pages/PostEditorPage.vue'),
    },
    {
        path: '/posts/automation',
        name: 'post.automation',
        component: () => import('@/pages/PostAutomationPage.vue'),
    },
    {
        path: '/posts/:postId/edit',
        name: 'post.edit',
        component: () => import('@/pages/PostEditorPage.vue'),
        props: true,
    },
    {
        path: '/posts/verify',
        name: 'post.verify',
        component: () => import('@/pages/PostVerificationPage.vue'),
    },
    {
        path: '/approval-tokens',
        name: 'approval-tokens',
        component: () => import('@/pages/ApprovalTokensPage.vue'),
    },
    {
        path: '/approval-dashboard',
        name: 'approval-dashboard',
        component: () => import('@/pages/ApprovalDashboardPage.vue'),
    },
    // Brands management
    {
        path: '/brands',
        name: 'brands',
        component: () => import('@/pages/BrandsPage.vue'),
    },
    {
        path: '/brands/new',
        name: 'brand.create',
        component: () => import('@/pages/BrandCreatePage.vue'),
        meta: { allowDuringOnboarding: true },
    },
    {
        path: '/brands/:brandId/edit',
        name: 'brand.edit',
        component: () => import('@/pages/BrandEditPage.vue'),
        props: true,
    },
    // Settings
    {
        path: '/settings',
        name: 'settings',
        component: () => import('@/pages/SettingsPage.vue'),
    },
    // Admin
    {
        path: '/admin/users',
        name: 'admin.users',
        component: () => import('@/pages/AdminUsersPage.vue'),
        meta: { requiresAdmin: true },
    },
    {
        path: '/admin/dev-tasks',
        name: 'admin.dev-tasks',
        component: () => import('@/pages/AdminDevTasksPage.vue'),
        meta: { requiresAdmin: true },
    },
    // PSD Editor (admin only)
    {
        path: '/psd-editor',
        name: 'psd-editor',
        component: () => import('@/pages/PsdEditorPage.vue'),
        meta: { requiresAdmin: true },
    },
    // Public client approval (no auth)
    {
        path: '/approve/:token',
        name: 'client-approval',
        component: () => import('@/pages/ClientApprovalPage.vue'),
        props: true,
        meta: { public: true },
    },
    // Render preview (for template-renderer service, no auth)
    {
        path: '/render-preview',
        name: 'render-preview',
        component: () => import('@/pages/RenderPreviewPage.vue'),
        meta: { public: true, hideLayout: true },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from) => {
    const authStore = useAuthStore();

    // Skip guard for public routes
    if (to.meta?.public) return true;

    // Skip guard if user data not loaded yet
    if (!authStore.user) return true;

    const isOnboarded = authStore.isOnboarded;

    // User not onboarded → redirect to /onboarding (except allowed routes)
    if (!isOnboarded && !to.meta?.onboarding && !to.meta?.allowDuringOnboarding) {
        return { name: 'onboarding' };
    }

    // User already onboarded → don't allow visiting /onboarding
    if (isOnboarded && to.meta?.onboarding) {
        return { name: 'dashboard' };
    }

    return true;
});

export default router;
