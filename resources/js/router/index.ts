import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            redirect: '/dashboard',
        },
        {
            path: '/login',
            component: () => import('@/layouts/AuthLayout.vue'),
            children: [
                {
                    path: '',
                    name: 'login',
                    component: () => import('@/pages/auth/Login.vue'),
                    meta: { guest: true },
                },
            ],
        },
        {
            path: '/register',
            component: () => import('@/layouts/AuthLayout.vue'),
            children: [
                {
                    path: '',
                    name: 'register',
                    component: () => import('@/pages/auth/Register.vue'),
                    meta: { guest: true },
                },
            ],
        },
        {
            path: '/',
            component: () => import('@/layouts/AppLayout.vue'),
            meta: { requiresAuth: true },
            children: [
                {
                    path: 'dashboard',
                    name: 'dashboard',
                    component: () => import('@/pages/Dashboard.vue'),
                },
                {
                    path: 'copilot',
                    name: 'copilot',
                    component: () => import('@/pages/Copilot/CopilotChat.vue'),
                },
                {
                    path: 'reports',
                    name: 'reports',
                    component: () => import('@/pages/Reports/ReportsDashboard.vue'),
                },
                {
                    path: 'imports',
                    name: 'imports',
                    component: () => import('@/pages/Imports/ImportList.vue'),
                },
                {
                    path: 'imports/upload',
                    name: 'imports.upload',
                    component: () => import('@/pages/Imports/ImportUpload.vue'),
                },
                {
                    path: 'imports/html',
                    name: 'imports.html',
                    component: () => import('@/pages/Imports/ImportHtmlPaste.vue'),
                },
                {
                    path: 'imports/:id/progress',
                    name: 'imports.progress',
                    component: () => import('@/pages/Imports/ImportProgress.vue'),
                },
                // Reconciliation
                {
                    path: 'reconciliation',
                    name: 'reconciliation',
                    component: () => import('@/pages/Reconciliation/ReconciliationHistory.vue'),
                },
                {
                    path: 'reconciliation/run',
                    name: 'reconciliation.run',
                    component: () => import('@/pages/Reconciliation/ReconciliationWizard.vue'),
                },
                {
                    path: 'reconciliation/:id',
                    name: 'reconciliation.detail',
                    component: () => import('@/pages/Reconciliation/ReconciliationRunDetail.vue'),
                },
                // Products
                {
                    path: 'products',
                    name: 'products',
                    component: () => import('@/pages/Products/ProductsList.vue'),
                },
                {
                    path: 'products/:id',
                    name: 'products.detail',
                    component: () => import('@/pages/Products/ProductDetail.vue'),
                },
                // Competitor pages
                {
                    path: 'products/:productId/competitors',
                    name: 'competitors',
                    component: () => import('@/pages/Competitors/CompetitorsList.vue'),
                },
                {
                    path: 'products/:productId/keyword-gaps',
                    name: 'keyword-gaps',
                    component: () => import('@/pages/Competitors/KeywordGaps.vue'),
                },
                {
                    path: 'products/:productId/benchmark',
                    name: 'benchmark',
                    component: () => import('@/pages/Competitors/BenchmarkView.vue'),
                },
                // Finance
                {
                    path: 'finance',
                    name: 'finance.dashboard',
                    component: () => import('@/pages/Finance/FinanceDashboard.vue'),
                },
                {
                    path: 'finance/orders',
                    name: 'finance.orders',
                    component: () => import('@/pages/Finance/OrdersList.vue'),
                },
                {
                    path: 'finance/settlements',
                    name: 'finance.settlements',
                    component: () => import('@/pages/Finance/SettlementsList.vue'),
                },
                {
                    path: 'finance/bank',
                    name: 'finance.bank',
                    component: () => import('@/pages/Finance/BankTransactionsList.vue'),
                },
                {
                    path: 'finance/gst',
                    name: 'finance.gst',
                    component: () => import('@/pages/Finance/GstTransactionsList.vue'),
                },
            ],
        },
        // Error pages (accessible to all, no auth required)
        {
            path: '/403',
            name: 'forbidden',
            component: () => import('@/pages/errors/Forbidden.vue'),
        },
        {
            path: '/500',
            name: 'server-error',
            component: () => import('@/pages/errors/ServerError.vue'),
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'not-found',
            component: () => import('@/pages/errors/NotFound.vue'),
        },
    ],
})

router.beforeEach(async (to) => {
    const authStore = useAuthStore()

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return { name: 'login' }
    }

    if (to.meta.guest && authStore.isAuthenticated) {
        return { name: 'dashboard' }
    }
})

export default router
