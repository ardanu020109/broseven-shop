<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/common/AppIcon.vue';
import { useBranding } from '@/composables/useBranding';
import { useAuthStore } from '@/stores/auth';
import { NAVIGATION } from '@/lib/navigation';
import { ROLE_PROFILE } from '@/lib/roles';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const branding = useBranding();

const collapsed = ref(false);
const mobileOpen = ref(false);
const loggingOut = ref(false);

const company = computed(() => branding.company);
const menu = computed(() => NAVIGATION[auth.role] ?? []);
const breadcrumb = computed(() => route.meta.breadcrumb ?? []);
const profilePath = computed(() => ROLE_PROFILE[auth.role] ?? '/');
const initials = computed(() =>
    (auth.user?.name ?? '?')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join(''),
);

onMounted(() => {
    try {
        collapsed.value = localStorage.getItem('sidebar-collapsed') === '1';
    } catch {
        collapsed.value = false;
    }
});

watch(collapsed, (value) => {
    try {
        localStorage.setItem('sidebar-collapsed', value ? '1' : '0');
    } catch {
        // penyimpanan tidak tersedia, abaikan
    }
});

watch(
    () => route.fullPath,
    () => {
        mobileOpen.value = false;
    },
);

async function logout() {
    loggingOut.value = true;

    try {
        await auth.logout();
    } finally {
        loggingOut.value = false;
        router.replace({ name: 'login' });
    }
}
</script>

<template>
    <div class="shell" :class="{ 'shell--collapsed': collapsed }">
        <div v-if="mobileOpen" class="shell__overlay" @click="mobileOpen = false"></div>

        <aside class="sidebar" :class="{ 'sidebar--open': mobileOpen }">
            <div class="sidebar__brand">
                <span class="sidebar__logo">
                    <img v-if="company.logo_url" :src="company.logo_url" :alt="company.store_name" />
                    <template v-else>B7</template>
                </span>
                <span class="sidebar__name">{{ company.store_name }}</span>
                <button class="sidebar__close" type="button" aria-label="Tutup menu" @click="mobileOpen = false">
                    <AppIcon name="close" />
                </button>
            </div>

            <nav class="sidebar__nav">
                <template v-for="item in menu" :key="item.to ?? item.heading">
                    <span v-if="item.heading" class="sidebar__heading">{{ item.heading }}</span>
                    <RouterLink v-else :to="item.to" class="sidebar__link" :title="item.label">
                        <AppIcon :name="item.icon" />
                        <span class="sidebar__label">{{ item.label }}</span>
                    </RouterLink>
                </template>
            </nav>

            <button
                class="sidebar__toggle"
                type="button"
                :aria-label="collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'"
                @click="collapsed = !collapsed"
            >
                <AppIcon :name="collapsed ? 'chevron-right' : 'chevron-left'" />
                <span class="sidebar__label">Ciutkan</span>
            </button>
        </aside>

        <div class="main">
            <header class="header">
                <button class="header__menu" type="button" aria-label="Buka menu" @click="mobileOpen = true">
                    <AppIcon name="menu" />
                </button>

                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <template v-for="(crumb, index) in breadcrumb" :key="crumb + index">
                        <span v-if="index > 0" class="breadcrumb__sep">/</span>
                        <span :class="{ 'breadcrumb__current': index === breadcrumb.length - 1 }">{{ crumb }}</span>
                    </template>
                </nav>

                <div class="header__user">
                    <RouterLink :to="profilePath" class="header__profile" title="Profil saya">
                        <span class="avatar">
                            <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" :alt="auth.user.name" />
                            <template v-else>{{ initials }}</template>
                        </span>
                        <div class="header__meta">
                            <strong>{{ auth.user?.name }}</strong>
                            <small>{{ auth.user?.role_label }}</small>
                        </div>
                    </RouterLink>
                    <button class="btn btn-outline header__logout" type="button" :disabled="loggingOut" @click="logout">
                        <AppIcon name="logout" :size="18" />
                        <span>Keluar</span>
                    </button>
                </div>
            </header>

            <main class="content">
                <RouterView />
            </main>
        </div>
    </div>
</template>

<style scoped>
.shell {
    --sidebar-width: 260px;
    display: flex;
    min-height: 100vh;
}

.shell--collapsed {
    --sidebar-width: 76px;
}

.shell__overlay {
    position: fixed;
    inset: 0;
    z-index: 40;
    background: rgba(15, 23, 42, 0.45);
}

.sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 50;
    display: flex;
    flex-direction: column;
    width: 260px;
    padding: 20px 14px;
    color: var(--white);
    background: var(--dark);
    transform: translateX(-100%);
    transition: transform 0.25s ease, width 0.25s ease;
}

.sidebar--open {
    transform: translateX(0);
}

.sidebar__brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 8px 20px;
    overflow: hidden;
}

.sidebar__logo {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 40px;
    height: 40px;
    overflow: hidden;
    border-radius: var(--radius-md);
    background: var(--primary);
    color: var(--dark);
    font-weight: 800;
}

.sidebar__logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sidebar__name {
    flex: 1;
    overflow: hidden;
    font-size: 17px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sidebar__close {
    display: grid;
    place-items: center;
    padding: 6px;
    border: 0;
    border-radius: var(--radius-sm);
    background: transparent;
    color: var(--white);
    cursor: pointer;
}

.sidebar__nav {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 4px;
    overflow-y: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.sidebar__nav::-webkit-scrollbar {
    display: none;
}

.sidebar__heading {
    padding: 14px 14px 4px;
    color: rgba(255, 255, 255, 0.5);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    white-space: nowrap;
}

.sidebar__link,
.sidebar__toggle {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 14px;
    border: 0;
    border-radius: var(--radius-md);
    background: transparent;
    color: rgba(255, 255, 255, 0.82);
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
    cursor: pointer;
    transition: background var(--transition), color var(--transition);
}

.sidebar__link:hover,
.sidebar__toggle:hover {
    background: rgba(255, 255, 255, 0.08);
    color: var(--white);
}

.sidebar__link.router-link-active {
    background: var(--primary);
    color: var(--dark);
}

.sidebar__toggle {
    display: none;
}

.main {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.header {
    position: sticky;
    top: 0;
    z-index: 30;
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 20px;
    background: var(--white);
    border-bottom: 1px solid var(--border-color);
}

.header__menu {
    display: grid;
    place-items: center;
    padding: 8px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    background: var(--white);
    color: var(--dark);
    cursor: pointer;
}

.breadcrumb {
    display: flex;
    flex: 1;
    gap: 8px;
    min-width: 0;
    overflow: hidden;
    color: var(--text-secondary);
    font-size: 14px;
    white-space: nowrap;
}

.breadcrumb__current {
    color: var(--dark);
    font-weight: 700;
}

.header__user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header__profile {
    display: flex;
    align-items: center;
    gap: 10px;
    color: inherit;
}

.header__meta {
    display: none;
    flex-direction: column;
    line-height: 1.25;
}

.header__meta small {
    color: var(--text-secondary);
}

.header__logout span {
    display: none;
}

.content {
    flex: 1;
    padding: 24px 20px;
}

@media (min-width: 640px) {
    .header__meta {
        display: flex;
    }

    .header__logout span {
        display: inline;
    }
}

@media (min-width: 992px) {
    .sidebar {
        transform: none;
    }

    .shell--collapsed .sidebar {
        width: 76px;
    }

    .main {
        margin-left: var(--sidebar-width);
        transition: margin-left 0.25s ease;
    }

    .sidebar__close,
    .header__menu,
    .shell__overlay {
        display: none;
    }

    .sidebar__toggle {
        display: flex;
    }

    .shell--collapsed .sidebar__name,
    .shell--collapsed .sidebar__label,
    .shell--collapsed .sidebar__heading {
        display: none;
    }

    .shell--collapsed .sidebar__link,
    .shell--collapsed .sidebar__toggle {
        justify-content: center;
        padding-inline: 0;
    }

    .shell--collapsed .sidebar__brand {
        justify-content: center;
        padding-inline: 0;
    }

    .content {
        padding: 28px;
    }
}
</style>
