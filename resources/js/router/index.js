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
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
