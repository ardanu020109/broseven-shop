import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import storeRoutes from './routes/store';
import superadminRoutes from './routes/superadmin';
import kasirRoutes from './routes/kasir';
import adminMasterRoutes from './routes/adminMaster';

const routes = [
    storeRoutes,
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/auth/LoginPage.vue'),
        meta: { guestOnly: true },
    },
    superadminRoutes,
    kasirRoutes,
    adminMasterRoutes,
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/pages/NotFoundPage.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        }

        if (to.path !== from.path) {
            return { top: 0 };
        }

        return false;
    },
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    const requiresAuth = to.matched.some((record) => record.meta.requiresAuth);
    const guestOnly = to.matched.some((record) => record.meta.guestOnly);
    const roles = to.matched.find((record) => record.meta.roles)?.meta.roles;

    if ((requiresAuth || guestOnly) && !auth.initialized) {
        await auth.fetchUser();
    }

    if (requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (guestOnly && auth.isAuthenticated) {
        return auth.homePath;
    }

    if (roles && !roles.includes(auth.role)) {
        return auth.homePath;
    }

    return true;
});

export default router;
