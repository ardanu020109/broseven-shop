import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import http from '@/lib/http';
import { ROLE_HOME } from '@/lib/roles';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const initialized = ref(false);

    const isAuthenticated = computed(() => user.value !== null);
    const role = computed(() => user.value?.role ?? null);
    const homePath = computed(() => ROLE_HOME[role.value] ?? '/login');

    async function fetchUser() {
        try {
            const { data } = await http.get('/auth/me', { skipAuthRedirect: true });
            user.value = data.user;
        } catch {
            user.value = null;
        } finally {
            initialized.value = true;
        }
    }

    async function login(credentials) {
        const { data } = await http.post('/auth/login', credentials, { skipAuthRedirect: true });
        user.value = data.user;
        initialized.value = true;
        return data.user;
    }

    async function logout() {
        try {
            await http.post('/auth/logout', null, { skipAuthRedirect: true });
        } finally {
            user.value = null;
        }
    }

    function clear() {
        user.value = null;
    }

    return { user, initialized, isAuthenticated, role, homePath, fetchUser, login, logout, clear };
});
