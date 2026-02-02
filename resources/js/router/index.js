import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/',
        redirect: '/dashboard',
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('@/pages/Dashboard.vue'),
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

export default router;
