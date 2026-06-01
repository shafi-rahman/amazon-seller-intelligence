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
            ],
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: '/dashboard',
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
