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
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
